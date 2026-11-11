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
exists only to support two populations of existing third-party elements during the 5.3
compatibility bridge: genuine Moodle 4.5-5.1-era elements with untyped `render()` /
`render_html()` methods, and plugins updated for the originally released Moodle 5.2 API that
relied on inheriting `renderable_element_interface` (compatibility for both was also restored
in `mod_customcert` 5.2.13, after the regression introduced in 5.2.0). See [element_migration_v2.md](element_migration_v2.md) — new
elements should not rely on it.

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
> its `normalise_data()` must return those values (omitted fields are not persisted, while
> any other returned fields are still saved). Persistence runs through
> `persistence_helper::to_json_data()`, which calls `normalise_data()` for a
> `persistable_element_interface` element; a current (non-legacy) element with no
> genuine legacy `save_unique_data()` override otherwise falls back to `{}`.
> `posx`, `posy`, `refpoint`, and `alignment` are layout data and are persisted
> separately by the repository/service layer, not via `normalise_data()`.

---

An element type is available in the picker by default. Implement a public static `can_add()`
hook if the plugin needs to hide the type under particular conditions. Elements without a
styling capability have no configured width.

## Validation

Validation has three layers; a new element normally needs only the first:

1. **Common fields — `validation_service`.** Always runs. It validates `colour`, `posx`/`posy`
   (when position fields are enabled) and `width` whenever those fields are submitted, so
   elements using `element_helper::render_common_form_elements()` get this for free.
2. **Element-specific rules — `validatable_element_interface::validate()`.** Optional. Implement
   it only when the element has rules of its own, and return field => message errors. Do not
   implement it just to return an empty array: that adds nothing, because `validation_service`
   only calls it when present. Bundled elements without element-specific rules do not
   implement `validatable_element_interface`.
3. **Deprecated `validate_form_elements()`.** Still called for historical subclasses that
   override it, with a developer-debugging notice, solely for compatibility. New code should
   not use it.

---

## `get_data()` versus `get_raw_data()`

- `element_interface::get_data()` is the view used by rendering and form code. For genuine
  legacy elements — those implementing neither `persistable_element_interface` nor
  `renderable_element_interface` — it may return a compatibility view: a scalar unwrapped from
  a generic migration JSON wrapper. It is **not** always the stored value.
- `raw_data_element_interface::get_raw_data()` exposes the unmodified element
  data intended for persistence. `mod_customcert\element` implements it, and the persistence
  layer uses it when saving so that migrated fields hidden by the legacy view (`font`,
  `fontsize`, `colour`, `width`, ...) are not lost.

For a current (native) element, `get_data()` does not apply legacy migration-wrapper unwrapping,
so you do not normally need to call `get_raw_data()`. Prefer `$this->get_payload()` for structured reads
rather than `json_decode($this->get_data(), true)`; use `get_value()` where a simple scalar
`value` payload is genuinely appropriate. Do not override `get_data()` to reshape stored data.

---

## Renderer contract

`render()` and `render_html()` receive an optional `element_renderer`. The published
`element_renderer` interface declares both `render_pdf()` and `render_html()`, but its two
implementations are not interchangeable:

- `html_renderer::render_pdf()` intentionally does nothing.
- `pdf_renderer::render_html()` intentionally returns an empty string.

Use the renderer that matches the output you need, and do not rely on the other operation
doing anything. Inside an element, `element_helper::render_content()` (PDF) and
`element_helper::render_html_content()` (HTML) are the usual way to output text.

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
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\stylable_payload;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

class element extends base_element implements
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

**Declared:** `persistable_element_interface`, `renderable_element_interface`. The base class
already provides `form_element_interface` (and through it `element_interface`).

---

### 2. Text-like stylable element (e.g. course name, user field)

Renders dynamic text with font/colour/size controls. Stores structured JSON and
composes the shared `stylable_payload` directly, and pre-populates the form on edit.

```php
<?php
declare(strict_types=1);

namespace customcertelement_mytext;

use mod_customcert\element as base_element;
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

**Declared** (beyond those inherited from `mod_customcert\element`):
`persistable_element_interface`, `preparable_form_interface`,
`renderable_element_interface`, `validatable_element_interface`.

> **Tip:** `myfield` has no invariant worth protecting, so this recipe composes
> `stylable_payload` directly rather than introducing a dedicated `mytext_payload`
> class. See [element_payload_interface.md](element_payload_interface.md) for when a dedicated payload class
> *is* warranted, and `element/coursename` for the canonical reference
> implementation of one.

---

### 3. Image-like element (capability skeleton)

File-backed elements (e.g. a signature or logo) commonly combine these interfaces:

- `renderable_element_interface` — required for native registration (`form_element_interface` is inherited)
- `persistable_element_interface` — store file metadata (context, filearea, itemid,
  filepath, filename) and any size/style fields in the JSON payload
- `preparable_form_interface` — restore the draft file area for editing
- `restorable_element_interface` — remap the file's context after backup restore
- `copyable_element_interface` — copy associated files when a template is duplicated

The following is an illustrative capability skeleton, not a complete PHP implementation.
File-area lifecycle and method bodies are intentionally omitted; use the bundled
`element/image/classes/element.php` implementation as the working reference.

```text
Declares (form_element_interface is inherited from the base class):
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

**Declared** (beyond those inherited from `mod_customcert\element`):
`persistable_element_interface`, `preparable_form_interface`,
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

**Declared** (beyond those inherited from `mod_customcert\element`):
`persistable_element_interface`, `preparable_form_interface`,
`renderable_element_interface`, `copyable_element_interface`.

---

## Template portability and resource handling

Satisfying the native registration contract does not make an element portable. Runtime
persistence, template export/import, Moodle backup/restore, and copying are separate
contracts, and each is opted into separately.

| Operation | Contract |
|---|---|
| Edit and save | `persistable_element_interface::normalise_data()` produces the JSON payload stored in `customcert_elements.data`. |
| Copy a page or template | The element row, including its stored `data`, is copied as-is. Implement `copyable_element_interface::copy_from()` to adjust anything the destination needs, such as files. |
| Moodle activity backup and restore | The backup stores element rows with their raw `data`. Implement `restorable_element_interface::after_restore_from_backup()` to remap references afterwards. |
| Template export and import (file) | Provide an `exporter` class. Without one, element-specific data is not exported and the element is not imported. |

`restorable_element_interface` has no effect on template export/import, and an exporter has no
effect on backup/restore. The exporter is looked up by element type only, so historical elements
wrapped by `legacy_element_adapter` need one as well.

### Template export and import

Create `classes/exporter.php` declaring `customcertelement_<type>\exporter`, extending
`mod_customcert\export\subplugin_exportable` (or `subplugin_text_exportable` for text-like elements).
`mod_customcert\export\element` instantiates it with the element type, a logger and the appendix
file manager, so inherit the constructor rather than overriding it. The only method you must
implement is `protected function get_fields(): array`, returning the field types from
`mod_customcert\export\datatypes` keyed by the payload keys you store.

The [text exporter](../element/text/classes/exporter.php) is a minimal working example: it extends
`subplugin_text_exportable`, which already defines `font`, `fontsize`, `colour` and `width`, and
adds a `string_field` for `text`:

```php
namespace customcertelement_text;

use mod_customcert\export\datatypes\string_field;
use mod_customcert\export\subplugin_text_exportable;

class exporter extends subplugin_text_exportable {
    protected function get_fields(): array {
        return parent::get_fields() + [
            'text' => new string_field(true),
        ];
    }
}
```

Only the fields returned by `get_fields()` are exported, so anything left out is not portable.
For example, the [digital signature exporter](../element/digitalsignature/classes/exporter.php)
does not list the signing password. On import, a field that is missing or not an array makes
the element's data invalid and the element is skipped with a warning; a field value that fails its
own validation logs a warning and uses the field's fallback.

Files are described with the `file_field` instance inherited as `$this->filefield`, not by
exporting site-local file IDs. A key containing `$`, as in `'$' => $this->filefield` in the
[image exporter](../element/image/classes/exporter.php), stands for the six metadata keys
(`contextid`, `component`, `filearea`, `itemid`, `filepath`, `filename`) of the stored file.
Export adds the referenced files to the template appendix and writes a `file_ref` identifier; import
stores the appendix files in the destination context and rewrites the payload with the new
location. Imported files are accepted only in the `mod_customcert` component, `image` file area,
item ID `0` and path `/` (see
[`template_appendix_manager`](../classes/export/template_appendix_manager.php)).
Any other manifest entry makes the import throw `import_exception`: the whole archive is rejected
rather than the file being skipped, files already stored by that import are removed, no template data
is created, and the import form shows the error. Export applies no such check, so an archive can be
created that cannot be imported. The bundled digital signature exporter is affected, because it also
exports the signature file, which is stored in the `signature` file area. A template containing a
digital signature element with a signature file therefore exports but cannot be imported; one
without a signature file imports normally. An element keeping files elsewhere cannot round-trip them
through template export/import.

If the exporter class is missing, or does not extend `subplugin_exportable`,
[`element_null_exporter`](../classes/export/element_null_exporter.php) is used. Export then writes
the element with an empty `data` array (and prints a notice when run from the CLI). Import logs
`Couldn't import element of type <type>` and skips the element rather than creating it. The element
is therefore not preserved by a template export/import round trip.

### Backup, restore and copying

Backup and restore do not use exporters. The backup stores each element row and its `data`
verbatim, along with files from the `mod_customcert` `image` area. After restore,
[`restore_customcert_activity_task`](../backup/moodle2/restore_customcert_activity_task.class.php)
calls `after_restore_from_backup()` on elements implementing `restorable_element_interface`. The
[image element](../element/image/classes/element.php) uses it to point the stored `contextid` at the
restored course context. Elements that still override the deprecated `after_restore()` are
supported through the legacy adapter, with a developer debugging notice.

The backup annotates only the `intro` and `image` file areas, so files in any other area, such as the
digital signature element's `signature` area, are not included, and that element defines no
`after_restore_from_backup()` to adjust its stored file references. A restored digital signature
element does not receive its signature file from the backup.

Copying clones the element row, then calls `copy_from(stdClass $source)` on the new element when it
implements `copyable_element_interface`; `$source` is the original element record. Returning `false`
removes the new element. The image element copies a system-context file into the course context
and rewrites its payload. A deprecated `copy_element()` override is still called the same way, with a
developer debugging notice.

### File preparation and deletion

Use Moodle draft areas for form uploads. The image element's `prepare_form()` prepares the draft area
and its `normalise_data()` calls `form_service::upload_files()` to save the draft into the
`mod_customcert` `image` file area (site context on the site, otherwise the course context) before
storing the file's context, area, item ID, path and name in the payload.

Deleting an element, page or template goes through `element_repository::delete()`, which deletes the
record and fires the `element_deleted` event. An element whose type cannot be resolved is deleted
without the event. The deprecated `element::delete()` only delegates to the repository and is not called
by these paths, so overriding it does not release resources. No per-element cleanup hook exists, and
files referenced by the payload are not removed. Do not delete a shared file from an event observer
unless you know no other element refers to it.

---

## Required vs optional: summary

Native registration requirements:

```
form_element_interface       ← required (extends element_interface); inherited from
                               mod_customcert\element, so not redeclared
renderable_element_interface ← required; must be declared on your class
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

- [element_payload_interface.md](element_payload_interface.md) — typed payload pattern, skeleton, and examples.
- [element_migration_v2.md](element_migration_v2.md) — migrating legacy (pre-5.2) elements to the v2 API.
- `element/coursename/` — canonical reference implementation for a stylable text element.
