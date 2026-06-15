# Migrating customcert element plugins to Element System v2 (5.3)

## Overview

Moodle 5.2 introduced **Element System v2** — a set of explicit PHP interfaces that replace the
legacy hook methods that lived on the `mod_customcert\element` base class.

**In 5.3 the legacy compatibility layer has been removed.**  
Third-party element plugins that still override the old hook methods must migrate to the v2
interfaces before they will work with customcert 5.3.

---

## What was removed in 5.3

The following methods were removed from the `mod_customcert\element` base class:

| Removed method | Replacement interface / method |
|---|---|
| `render_form_elements(MoodleQuickForm $mform)` | `form_element_interface::build_form()` |
| `definition_after_data(MoodleQuickForm $mform)` | `preparable_form_interface::prepare_form()` |
| `validate_form_elements(array $data, array $files, array $element)` | `validatable_element_interface::validate()` |
| `save_form_elements(stdClass $data)` | `persistable_element_interface::normalise_data()` |
| `save_unique_data(string $data)` | `persistable_element_interface::normalise_data()` |
| `render(pdf $pdf, bool $preview, stdClass $user)` *(legacy untyped)* | `renderable_element_interface` — implement typed `render()` |
| `render_html()` *(legacy untyped)* | `renderable_element_interface` — implement typed `render_html()` |
| `after_restore(int $newitemid, stdClass $data, stdClass $task)` | `restorable_element_interface::after_restore_from_backup()` |
| `copy_element(stdClass $oldelement)` | `copyable_element_interface::copy_from()` |
| `get_data()` migration wrapper | Use `$this->get_data()` directly — returns raw JSON string |
| `is_generic_migration_wrapper()` | Removed — no replacement needed |
| `BUNDLED_ELEMENT_TYPES` constant | Removed |
| `MIGRATION_VISUAL_KEYS` constant | Removed |

The `legacy_element_adapter` wrapper class has also been removed entirely.

---

## Required interfaces

Every registered element class **must** implement `element_interface`.  
The registry will throw a `coding_exception` at registration time if it does not.

Additional interfaces are opt-in depending on what the element does:

| Interface | When to implement |
|---|---|
| `element_interface` | **Always** — core identity/payload contract |
| `form_element_interface` | Element has an edit form (`build_form()`) |
| `preparable_form_interface` | Element needs to pre-populate form fields from stored data |
| `validatable_element_interface` | Element validates submitted form data |
| `persistable_element_interface` | Element normalises form data into a JSON payload |
| `renderable_element_interface` | Element renders to PDF and/or HTML |
| `stylable_element_interface` | Element uses standard font/colour/width styling |
| `layout_element_interface` | Element exposes repository-managed layout values (posx, posy, etc.) |
| `restorable_element_interface` | Element remaps internal references after backup restore |
| `copyable_element_interface` | Element needs custom logic when copied (e.g. file copying) |

All interfaces live under `mod_customcert\element\`.

---

## Minimal v2 element

A read-only element that renders text and has no edit form:

```php
namespace customcertelement_myelement;

use mod_customcert\element as base_element;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\layout_element_interface;
use mod_customcert\element\stylable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use pdf;
use stdClass;

class element extends base_element implements
    renderable_element_interface,
    stylable_element_interface,
    layout_element_interface
{
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        element_helper::render_content($pdf, $this, get_string('pluginname', 'customcertelement_myelement'));
    }

    public function render_html(?element_renderer $renderer = null): string {
        return element_helper::render_html_content($this, get_string('pluginname', 'customcertelement_myelement'));
    }
}
```

> `element_helper::render_content()` and `render_html_content()` require both
> `stylable_element_interface` and `layout_element_interface`. Elements with fully custom
> rendering may implement `renderable_element_interface` directly without these two.

---

## Form-editable element

An element that adds fields to the edit form, validates them, and persists them as JSON:

```php
namespace customcertelement_myelement;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\stylable_element_interface;
use mod_customcert\element\layout_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

class element extends base_element implements
    form_element_interface,
    preparable_form_interface,
    validatable_element_interface,
    persistable_element_interface,
    renderable_element_interface,
    stylable_element_interface,
    layout_element_interface
{
    /**
     * Add element-specific fields to the edit form.
     */
    public function build_form(MoodleQuickForm $mform): void {
        $mform->addElement('text', 'myfield', get_string('myfield', 'customcertelement_myelement'));
        $mform->setType('myfield', PARAM_TEXT);
        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    /**
     * Pre-populate form fields from stored payload.
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        $raw = $this->get_data();
        if ($raw !== null && $raw !== '') {
            $payload = json_decode($raw, true);
            if (is_array($payload) && isset($payload['myfield'])) {
                $mform->getElement('myfield')->setValue($payload['myfield']);
            }
        }
    }

    /**
     * Validate submitted form data.
     *
     * @param array $data
     * @return array<string,string> field => error message
     */
    public function validate(array $data): array {
        $errors = [];
        if (empty($data['myfield'])) {
            $errors['myfield'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Normalise submitted form data into a JSON-serialisable payload array.
     *
     * @param stdClass $formdata
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        return [
            'myfield' => (string)($formdata->myfield ?? ''),
            'font'     => (string)($formdata->font ?? ''),
            'fontsize' => (int)($formdata->fontsize ?? 0),
            'colour'   => (string)($formdata->colour ?? ''),
            'width'    => (int)($formdata->width ?? 0),
        ];
    }

    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        $payload = json_decode($this->get_data() ?? '{}', true);
        element_helper::render_content($pdf, $this, (string)($payload['myfield'] ?? ''));
    }

    public function render_html(?element_renderer $renderer = null): string {
        $payload = json_decode($this->get_data() ?? '{}', true);
        return element_helper::render_html_content($this, (string)($payload['myfield'] ?? ''));
    }
}
```

---

## Restore guidance

If your element stores internal Moodle IDs (file itemids, course module IDs, etc.) in its JSON
payload, implement `restorable_element_interface` to remap them after restore:

```php
use mod_customcert\element\restorable_element_interface;

class element extends base_element implements restorable_element_interface, /* ... */
{
    public function after_restore_from_backup(int $newitemid, stdClass $data, object $task): bool {
        $payload = json_decode($this->get_data() ?? '{}', true);

        // Remap a stored file itemid using the restore mapping.
        $oldfileid = (int)($payload['fileid'] ?? 0);
        if ($oldfileid) {
            $newfileid = $task->get_mappingid('files', $oldfileid);
            if ($newfileid) {
                $payload['fileid'] = $newfileid;
                // Persist the updated payload via the element repository.
                // (Use the injected repository/service rather than direct DB calls.)
            }
        }
        return true;
    }
}
```

---

## Copy guidance

If your element needs to copy associated files or other resources when a certificate template is
copied, implement `copyable_element_interface`:

```php
use mod_customcert\element\copyable_element_interface;

class element extends base_element implements copyable_element_interface, /* ... */
{
    public function copy_from(stdClass $source): bool {
        // $source is the raw DB record of the original element.
        // Copy files from the source context to the current element context here.
        return true;
    }
}
```

---

## Payload vs layout

**Payload** (stored in `customcert_elements.data` as JSON) is element-specific data managed by
the element class itself via `persistable_element_interface::normalise_data()`.  
Examples: text content, a selected option, a file ID, font/colour/width styling values.

**Layout** (posx, posy, refpoint, alignment) is managed by the element repository and layout
service. Element classes should **not** write layout values directly to the database.  
Implement `layout_element_interface` to expose these values for rendering helpers.

---

## Security: scoped element and page lookups

Always use the scoped repository/service methods when loading elements or pages.  
Never load an element by ID alone — always verify it belongs to the expected template/page:

```php
// Correct — verifies element belongs to the given page and template.
$element = $elementrepository->get_element_for_page($elementid, $pageid, $templateid);

// Incorrect — no ownership check.
$element = $DB->get_record('customcert_elements', ['id' => $elementid]);
```

The service layer enforces these checks. Bypassing them is a security risk.

---

## Further reading

- `CHANGES.md` — 5.3 breaking-change entry with full migration table
- `classes/element/` — all v2 interface definitions
- Bundled elements (e.g. `element/coursename`, `element/text`, `element/date`) — real-world v2
  implementations to use as reference
