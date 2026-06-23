# Element authoring guide
> **Status**: introduced in 5.3.0 (#819)

This guide is for third-party developers who want to create a custom certificate
element plugin. It explains which interfaces are required, which are optional, and
provides examples and sketches for the most common element types.

---

## Quick-start: the three required interfaces

Every element **must** implement these three interfaces (enforced at registration time):

| Interface | What it provides |
|---|---|
| `element_interface` | Identity: `get_id()`, `get_pageid()`, `get_name()`, `get_data()`, `get_type()` |
| `form_element_interface` | Edit-form wiring: `build_form()`, `set_edit_element_form()`, `has_save_and_continue()` |
| `renderable_element_interface` | Output: `render()` (PDF) and `render_html()` (drag-and-drop preview) |

In practice you extend the bundled `mod_customcert\element` base class, which already
satisfies `element_interface` and the non-abstract parts of `form_element_interface`.
Your concrete class only needs to implement `build_form()`, `render()`, and
`render_html()`.

---

## Interface decision table

Use this table to decide which additional interfaces to add:

| Element need | Interface to implement | Required? |
|---|---|---|
| Basic identity and payload | `element_interface` | **Yes** (via base class) |
| Edit-form support | `form_element_interface` | **Yes** |
| PDF / HTML output | `renderable_element_interface` | **Yes** |
| Pre-populate form fields from stored data | `preparable_form_interface` | Optional |
| Font / colour / size behaviour | `stylable_element_interface` | Optional |
| Positioning / layout behaviour | `layout_element_interface` | Optional |
| Custom save / normalise behaviour | `persistable_element_interface` | Optional |
| Custom form validation | `validatable_element_interface` | Optional |
| Backup / restore handling | `restorable_element_interface` | Optional |
| Copy behaviour | `copyable_element_interface` | Optional |

---

## Common element recipes

### 1. Minimal static element

Renders a fixed string on the certificate. No form fields beyond the standard
position/style controls, no custom persistence.

```php
<?php
declare(strict_types=1);

namespace customcertelement_staticlabel;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

class element extends base_element implements
    form_element_interface,
    renderable_element_interface
{
    public function build_form(MoodleQuickForm $mform): void {
        // Add only the standard position/style controls.
        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        element_helper::render_content($pdf, $this, get_string('pluginname', 'customcertelement_staticlabel'));
    }

    public function render_html(?element_renderer $renderer = null): string {
        return element_helper::render_html_content($this, get_string('pluginname', 'customcertelement_staticlabel'));
    }
}
```

**Interfaces used:** `element_interface` (base class), `form_element_interface`,
`renderable_element_interface`.

---

### 2. Text-like stylable element (e.g. course name, user field)

Renders dynamic text with font/colour/size controls. Uses a typed payload class and
pre-populates the form on edit.

```php
<?php
declare(strict_types=1);

namespace customcertelement_mytext;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\renderable_element_interface;
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
        $raw = $this->get_data();
        if ($raw) {
            $data = json_decode($raw, true) ?? [];
            $mform->setDefault('myfield', $data['myfield'] ?? '');
        }
    }

    public function validate(array $data): array {
        $errors = [];
        if (empty($data['myfield'])) {
            $errors['myfield'] = get_string('required');
        }
        return $errors;
    }

    public function normalise_data(stdClass $formdata): array {
        return [
            'myfield' => clean_param($formdata->myfield ?? '', PARAM_TEXT),
        ];
    }

    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        $text = $this->resolve_text($user);
        element_helper::render_content($pdf, $this, $text);
    }

    public function render_html(?element_renderer $renderer = null): string {
        return element_helper::render_html_content($this, $this->resolve_text(null));
    }

    private function resolve_text(?stdClass $user): string {
        $raw = $this->get_data();
        if (!$raw) {
            return '';
        }
        $data = json_decode($raw, true) ?? [];
        return $data['myfield'] ?? '';
    }
}
```

**Interfaces used:** `element_interface` (base class), `form_element_interface`,
`persistable_element_interface`, `preparable_form_interface`,
`renderable_element_interface`, `validatable_element_interface`.

> **Tip:** For elements that also need font/colour/size controls, compose
> `stylable_payload` in your payload class. See `element/coursename` for the
> canonical reference implementation and `docs/element_payload_interface.md` for
> the full typed-payload guide.

---

### 3. Image-like element (conceptual sketch)

The following is a conceptual sketch of an image element (e.g. a signature or logo).
It illustrates the interfaces and structure involved, but file handling details
(draft areas, URL generation, context selection) will vary for your use case.
Typically needs custom persistence to store a file reference, and backup/restore support.

```php
<?php
declare(strict_types=1);

namespace customcertelement_myimage;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\restorable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use backup_customcert_activity_task;
use restore_customcert_activity_task;
use MoodleQuickForm;
use pdf;
use stdClass;

class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    restorable_element_interface
{
    public function build_form(MoodleQuickForm $mform): void {
        $mform->addElement('filemanager', 'myimage', get_string('myimage', 'customcertelement_myimage'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ]);
        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    public function prepare_form(MoodleQuickForm $mform): void {
        // Restore the draft file area for editing.
        $raw = $this->get_data();
        if ($raw) {
            $data = json_decode($raw, true) ?? [];
            $draftitemid = file_get_submitted_draft_itemid('myimage');
            file_prepare_draft_area($draftitemid, \context_system::instance()->id,
                'customcertelement_myimage', 'myimage', $this->get_id());
            $mform->setDefault('myimage', $draftitemid);
        }
    }

    public function normalise_data(stdClass $formdata): array {
        // Save the file from the draft area and store the item ID.
        file_save_draft_area_files($formdata->myimage, \context_system::instance()->id,
            'customcertelement_myimage', 'myimage', $this->get_id());
        return ['myimage' => $this->get_id()];
    }

    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        // Retrieve the stored file and render it.
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id,
            'customcertelement_myimage', 'myimage', $this->get_id(), '', false);
        $file = reset($files);
        if ($file) {
            $path = $file->copy_content_to_temp();
            $pdf->Image($path, $this->get_posx(), $this->get_posy(), $this->get_width());
        }
    }

    public function render_html(?element_renderer $renderer = null): string {
        // Return an <img> tag pointing to the stored file's URL.
        // Exact URL retrieval depends on your file area and context setup.
        return '<img src="" />';
    }

    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        // Re-map file references after a backup restore if needed.
    }
}
```

**Interfaces used:** `element_interface` (base class), `form_element_interface`,
`persistable_element_interface`, `preparable_form_interface`,
`renderable_element_interface`, `restorable_element_interface`.

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
        $raw = $this->get_data();
        if ($raw) {
            $data = json_decode($raw, true) ?? [];
            $mform->setDefault('label', $data['label'] ?? '');
        }
    }

    public function normalise_data(stdClass $formdata): array {
        return ['label' => clean_param($formdata->label ?? '', PARAM_TEXT)];
    }

    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        $raw = $this->get_data();
        $data = $raw ? (json_decode($raw, true) ?? []) : [];
        element_helper::render_content($pdf, $this, $data['label'] ?? '');
    }

    public function render_html(?element_renderer $renderer = null): string {
        $raw = $this->get_data();
        $data = $raw ? (json_decode($raw, true) ?? []) : [];
        return element_helper::render_html_content($this, $data['label'] ?? '');
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

**Interfaces used:** `element_interface` (base class), `form_element_interface`,
`persistable_element_interface`, `preparable_form_interface`,
`renderable_element_interface`, `copyable_element_interface`.

---

## Required vs optional: summary

```
element_interface          ← always required (satisfied by base class)
form_element_interface     ← always required
renderable_element_interface ← always required

preparable_form_interface  ← add when you need to pre-populate form fields on edit
persistable_element_interface ← add when you need custom save/normalise logic
validatable_element_interface ← add when you need custom form validation
stylable_element_interface ← add when your element exposes font/colour/size getters
layout_element_interface   ← add when your element exposes position/alignment getters
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
