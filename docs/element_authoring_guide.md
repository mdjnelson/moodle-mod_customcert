# Element authoring guide
> **Status**: introduced in 5.3.0 (#819)

This guide is for third-party developers who want to create a custom certificate
element plugin. It explains which interfaces are required, which are optional, and
provides examples and sketches for the most common element types.

---

## Quick-start: the native v2 registration contract

Every native Element System v2 element must satisfy `form_element_interface` and
`renderable_element_interface`; `form_element_interface` already extends
`element_interface`, so a native element gets the identity/payload contract through
it rather than declaring `element_interface` separately. The registry (`element_registry`)
enforces this pairing at registration time.

| Interface | What it provides |
|---|---|
| `element_interface` | Identity: `get_id()`, `get_pageid()`, `get_name()`, `get_data()`, `get_type()` (extended by `form_element_interface`) |
| `form_element_interface` | Edit-form wiring: `build_form()`, `set_edit_element_form()`, `has_save_and_continue()` |
| `renderable_element_interface` | Output: `render()` (PDF) and `render_html()` (drag-and-drop preview) |

This is distinct from the temporary Moodle 5.3 legacy compatibility path: the registry
also accepts historical subclasses of `mod_customcert\element` that do not implement
`renderable_element_interface`, wrapping them via `legacy_element_adapter`. That path
exists only to support genuine Moodle 4.5-era third-party elements during the 5.3
compatibility bridge (see `docs/element_migration_v2.md`) — new elements should not
rely on it.

New element plugins will normally extend `mod_customcert\element`, which supplies the
identity/form plumbing and the common style/layout getters (it already implements
`form_element_interface`, `layout_element_interface`, and `stylable_element_interface`).
Native v2 elements should implement `renderable_element_interface` explicitly and
override `build_form()` with their current form definition, rather than relying on the
inherited `build_form()`, which only bridges to the deprecated legacy
`render_form_elements()` hook.

---

## Interface decision table

Use this table to decide which additional interfaces to add:

| Element need | Interface to implement | Required? |
|---|---|---|
| Basic identity and payload | `element_interface` | **Yes** (via `form_element_interface`) |
| Edit-form support | `form_element_interface` | **Yes** |
| PDF / HTML output | `renderable_element_interface` | **Yes** |
| Pre-populate form fields from stored data | `preparable_form_interface` | Optional |
| Font / colour / size behaviour | `stylable_element_interface` | Optional — already inherited by subclasses of `mod_customcert\element` |
| Positioning / layout behaviour | `layout_element_interface` | Optional — already inherited by subclasses of `mod_customcert\element` |
| Custom save / normalise behaviour | `persistable_element_interface` | Optional — add when form/editable values need to be stored in the JSON payload, including the standard style fields |
| Custom form validation | `validatable_element_interface` | Optional |
| Backup / restore handling | `restorable_element_interface` | Optional |
| Copy behaviour | `copyable_element_interface` | Optional |

The optional labels above describe the v2 capability model in the abstract: a class is
free to implement only the interfaces it needs. In practice, a subclass of the bundled
`mod_customcert\element` base already inherits the standard style and layout interfaces,
so for most elements the open questions are persistence, validation, restore, and copy
behaviour.

> **`persistable_element_interface` is optional, with a caveat.** It is optional when
> the element genuinely has no element-data payload to save. It must not be read as
> "an element can display editable data/style controls without defining how they are
> persisted": if an element exposes editable fields whose values belong in
> `customcert_elements.data` — including the standard visual fields `font`, `fontsize`,
> `colour`, and `width` when `element_helper::render_common_form_elements()` is used —
> its `normalise_data()` must return those values. Persistence runs through
> `persistence_helper::to_json_data()`, which calls `normalise_data()` for a
> `persistable_element_interface` element; a current (non-legacy) element with no
> genuine legacy `save_unique_data()` override otherwise falls back to `{}`.
> `posx`, `posy`, `refpoint`, and `alignment` are layout data and are persisted
> separately by the repository/service layer, not via `normalise_data()`.

> **`get_data()` and structured JSON:** `get_data()` only unwraps a generic migration
> wrapper for genuine legacy elements — those implementing neither
> `persistable_element_interface` nor `renderable_element_interface`. Any current element,
> including one with no custom save/normalise behaviour, always receives its raw JSON.
> Prefer `$this->get_payload()` for ordinary structured-data reads rather than manually
> calling `json_decode($this->get_data(), true)`; use `get_value()` where a simple
> scalar `value` payload is genuinely appropriate.

---

## Common element recipes

### 1. Minimal static element

Renders a fixed string on the certificate. No form fields beyond the standard
position/style controls, no element-specific payload.

```php
<?php
declare(strict_types=1);

namespace customcertelement_staticlabel;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\stylable_payload;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    renderable_element_interface
{
    public function build_form(MoodleQuickForm $mform): void {
        // Add only the standard position/style controls.
        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    public function normalise_data(stdClass $formdata): array {
        return stylable_payload::from_form($formdata)->to_array();
    }

    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        element_helper::render_content($pdf, $this, get_string('pluginname', 'customcertelement_staticlabel'));
    }

    public function render_html(?element_renderer $renderer = null): string {
        return element_helper::render_html_content($this, get_string('pluginname', 'customcertelement_staticlabel'));
    }
}
```

The standard style controls (`font`, `fontsize`, `colour`, `width`) live in the JSON
payload, not in dedicated DB columns, so an element that renders them via
`render_common_form_elements()` must implement `persistable_element_interface` and
return those values from `normalise_data()` — otherwise they are silently dropped on
save. `stylable_payload::from_form()` composes exactly those four fields; no
dedicated payload class is needed here since this element has no element-specific
payload beyond them.

**Interfaces used:** `element_interface` (via `form_element_interface`),
`form_element_interface`, `persistable_element_interface`, `renderable_element_interface`.

---

### 2. Text-like stylable element (e.g. course name, user field)

Renders dynamic text with font/colour/size controls. Stores structured JSON and
composes the shared `stylable_payload` directly, and pre-populates the form on edit.

```php
<?php
declare(strict_types=1);

namespace customcertelement_mytext;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\stylable_payload;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    validatable_element_interface
{
    public function build_form(MoodleQuickForm $mform): void {
        // Add your custom fields here, then the standard controls.
        $mform->addElement('text', 'myfield', get_string('myfield', 'customcertelement_mytext'));
        $mform->setType('myfield', PARAM_TEXT);

        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    public function prepare_form(MoodleQuickForm $mform): void {
        // Pre-populate fields from stored JSON when editing an existing element.
        $payload = $this->get_payload();
        $mform->setDefault('myfield', $payload['myfield'] ?? '');
    }

    public function validate(array $data): array {
        $errors = [];
        if (empty($data['myfield'])) {
            $errors['myfield'] = get_string('required');
        }
        return $errors;
    }

    public function normalise_data(stdClass $formdata): array {
        return array_merge(
            ['myfield' => clean_param($formdata->myfield ?? '', PARAM_TEXT)],
            stylable_payload::from_form($formdata)->to_array(),
        );
    }

    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        element_helper::render_content($pdf, $this, $this->resolve_text());
    }

    public function render_html(?element_renderer $renderer = null): string {
        return element_helper::render_html_content($this, $this->resolve_text());
    }

    private function resolve_text(): string {
        return $this->get_payload()['myfield'] ?? '';
    }
}
```

**Interfaces used:** `element_interface` (via `form_element_interface`),
`form_element_interface`, `persistable_element_interface`, `preparable_form_interface`,
`renderable_element_interface`, `validatable_element_interface`.

> **Tip:** `myfield` has no invariant worth protecting, so this recipe composes
> `stylable_payload` directly rather than introducing a dedicated `mytext_payload`
> class. See `docs/element_payload_interface.md` for when a dedicated payload class
> *is* warranted, and `element/coursename` for the canonical reference
> implementation of one.

---

### 3. Image-like element (capability skeleton)

File-backed elements (e.g. a signature or logo) commonly combine these interfaces:

- `form_element_interface`, `renderable_element_interface` — the native registration pair
- `persistable_element_interface` — store file metadata (context, filearea, itemid,
  filepath, filename) and any size/style fields in the JSON payload
- `preparable_form_interface` — restore the draft file area for editing
- `restorable_element_interface` — remap the file's context after backup restore
- `copyable_element_interface` — copy associated files when a template is duplicated

The following is an illustrative capability skeleton, not a complete PHP implementation.
File-area lifecycle and method bodies are intentionally omitted; use the bundled
`element/image/classes/element.php` implementation as the working reference.

```text
Implements:
- form_element_interface
- persistable_element_interface
- preparable_form_interface
- renderable_element_interface
- restorable_element_interface
- copyable_element_interface

Provides:
- build_form(MoodleQuickForm $mform): void
- prepare_form(MoodleQuickForm $mform): void
- normalise_data(stdClass $formdata): array
- render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void
- render_html(?element_renderer $renderer = null): string
- after_restore_from_backup(restore_customcert_activity_task $restore): void
- copy_from(stdClass $source): bool
```

File-area lifecycle is context-sensitive and intentionally omitted here: the correct
context (system vs. course), draft-area handling, and URL generation depend on your
plugin's own file-storage conventions. See `element/image/classes/element.php` for the
full supported file lifecycle, including `prepare_form()`'s draft-area restore,
`normalise_data()`'s file save and payload assembly, `render()`/`render_html()`'s
stored-file lookup, and `after_restore_from_backup()`'s context remapping.

**Interfaces used:** `element_interface` (via `form_element_interface`),
`form_element_interface`, `persistable_element_interface`, `preparable_form_interface`,
`renderable_element_interface`, `restorable_element_interface`,
`copyable_element_interface`.

---

### 4. Form-editable element with copy support

An element that stores structured data and needs special handling when the template
is duplicated (e.g. copying file attachments or resetting IDs).

```php
<?php
declare(strict_types=1);

namespace customcertelement_myeditable;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\copyable_element_interface;
use mod_customcert\element\stylable_payload;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    copyable_element_interface
{
    public function build_form(MoodleQuickForm $mform): void {
        $mform->addElement('text', 'label', get_string('label', 'customcertelement_myeditable'));
        $mform->setType('label', PARAM_TEXT);
        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    public function prepare_form(MoodleQuickForm $mform): void {
        $mform->setDefault('label', $this->get_payload()['label'] ?? '');
    }

    public function normalise_data(stdClass $formdata): array {
        return array_merge(
            ['label' => clean_param($formdata->label ?? '', PARAM_TEXT)],
            stylable_payload::from_form($formdata)->to_array(),
        );
    }

    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        element_helper::render_content($pdf, $this, $this->get_payload()['label'] ?? '');
    }

    public function render_html(?element_renderer $renderer = null): string {
        return element_helper::render_html_content($this, $this->get_payload()['label'] ?? '');
    }

    public function copy_from(stdClass $source): bool {
        // $source is the raw DB record of the original element.
        // Perform any custom copy logic here, such as duplicating associated files.
        // Return true when the copied element should be kept.
        // Return false only when the copy failed and the new element should be removed.
        return true;
    }
}
```

**Interfaces used:** `element_interface` (via `form_element_interface`),
`form_element_interface`, `persistable_element_interface`, `preparable_form_interface`,
`renderable_element_interface`, `copyable_element_interface`.

---

## Required vs optional: summary

Native registration requirements:

```
form_element_interface       ← always required (extends element_interface)
renderable_element_interface ← always required
```

Optional capability interfaces, added based on behaviour:

```
preparable_form_interface  ← add when you need to pre-populate form fields on edit
persistable_element_interface ← add when form/editable values need to be stored in
                                 the JSON payload, including the standard style fields
validatable_element_interface ← add when you need custom form validation
stylable_element_interface ← add when your element exposes font/colour/size getters
                              (already inherited by subclasses of mod_customcert\element)
layout_element_interface   ← add when your element exposes position/alignment getters
                              (already inherited by subclasses of mod_customcert\element)
restorable_element_interface ← add when you store files or external references
copyable_element_interface ← add when template duplication needs special handling
```

---

## Plugin file layout

A minimal element sub-plugin lives under `element/<type>/` inside the `customcert`
directory:

```
element/
  mytype/
    classes/
      element.php          ← your element class (extends mod_customcert\element)
      mytype_payload.php   ← optional typed payload (implements element_payload_interface)
    lang/
      en/
        customcertelement_mytype.php
    version.php
```

The plugin component name follows the pattern `customcertelement_<type>`.

---

## Further reading

- `docs/element_payload_interface.md` — typed payload pattern, skeleton, and examples.
- `docs/element_migration_v2.md` — migrating legacy (pre-5.2) elements to the v2 API.
- `element/coursename/` — canonical reference implementation for a stylable text element.
