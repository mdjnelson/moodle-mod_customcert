# Element System v2 (current API, Moodle 6.0)

## Overview

Moodle 5.2 introduced **Element System v2** — a set of explicit PHP interfaces that replace the
historical hook methods that used to live on the `mod_customcert\element` base class. Moodle 5.3
kept a deprecated compatibility bridge so a genuine Moodle 4.5-era third-party element could still
run during the upgrade window.

**As of the Moodle 6.0-compatible release, that compatibility bridge has been removed** (#993,
#994, #995, #996, #997). `legacy_element_adapter`, `legacy_compatibility_diagnostic`, the legacy
hook methods on `mod_customcert\element` (`save_form_elements()`, `copy_element()`, `delete()`
shims, legacy property access, legacy scalar unwrapping), the static `element_factory` shim, the
`certificate` facade, and the `template` forwarding methods no longer exist. A third-party element
that only implements the historical hooks and does not implement the Element System v2 interfaces
directly **will no longer be accepted for runtime use**: the element registry rejects it with a
`coding_exception` at registration time because it fails the native v2 contract. This does not
imply the class necessarily fails PHP class loading — loading is distinct from registry
acceptance.

This removal is a **runtime API** change only. Historical database-upgrade (`db/upgrade.php`) and
backup/restore migration logic for old persisted element data is a separate, independent concern
and is retained — upgrading or restoring historical data continues to work. See
`docs/element_authoring_guide.md` for the current authoring reference and
`CHANGES.md` for the dated history of each deprecation and removal.

---

## Required interfaces

Registered element classes must implement `form_element_interface` and
`renderable_element_interface`; `form_element_interface` already extends `element_interface`. The
registry throws a `coding_exception` at registration time if a class does not satisfy this native
v2 contract — there is no fallback compatibility path.

### Native registration requirements

| Interface | What it provides |
|---|---|
| `form_element_interface` | Edit-form wiring (`build_form()`); extends `element_interface` |
| `renderable_element_interface` | Rendering to PDF and/or HTML |

### Optional capability interfaces

Add these based on what the element actually does:

| Interface | When to implement |
|---|---|
| `preparable_form_interface` | Element needs to pre-populate form fields from stored data |
| `validatable_element_interface` | Element validates submitted form data |
| `persistable_element_interface` | Element normalises form/editable values into the JSON payload — add when form/editable values need to be stored there, including the standard style fields `font`, `fontsize`, `colour`, and `width` when exposed via `element_helper::render_common_form_elements()`. `posx`, `posy`, `refpoint`, and `alignment` are layout data and are persisted separately by the repository/service layer, not via `normalise_data()`. |
| `stylable_element_interface` | Element uses standard font/colour/width styling (already inherited by subclasses of `mod_customcert\element`) |
| `layout_element_interface` | Element exposes repository-managed layout values (posx, posy, etc.) (already inherited by subclasses of `mod_customcert\element`) |
| `restorable_element_interface` | Element remaps internal references after backup restore |
| `copyable_element_interface` | Element needs custom logic when copied (e.g. file copying) |
| `raw_data_element_interface` | Element's `get_data()` representation differs from what should be persisted (e.g. a derived/formatted view); `get_raw_data()` returns the exact value to store |

All interfaces live under `mod_customcert\element\`.

---

## Former legacy hooks and their Element System v2 replacements

These historical `mod_customcert\element` hooks no longer exist. They are listed here only so
that code or documentation still referring to them can be mapped to the current replacement:

| Removed legacy hook | Element System v2 replacement |
|---|---|
| `render_form_elements()` | `form_element_interface::build_form()` |
| `definition_after_data()` | `preparable_form_interface::prepare_form()` |
| `validate_form_elements()` | `validatable_element_interface::validate()` |
| `save_form_elements()` / `save_unique_data()` | `persistable_element_interface::normalise_data()` |
| `render()` / `render_html()` (untyped legacy signature) | `renderable_element_interface` |
| `after_restore()` | `restorable_element_interface::after_restore_from_backup()` |
| `copy_element()` (instance hook) | `copyable_element_interface::copy_from()` |
| `delete()` (instance hook) | `element_repository::delete()` |
| static `element_factory::get_element_instance()` | `service\element_factory::build_with_defaults()->create_from_record()` |
| `certificate` facade methods | `form_service`, `element_helper`, `certificate_time_service`, `certificate_download_service`, `issue_repository`, `certificate_repository`, `certificate_issue_service` |
| `template` forwarding methods | `template_service` and `pdf_generation_service` |

`service\element_factory::create_from_legacy_record()` is **not** a removed hook — it remains a
supported public API for constructing an element instance from a historical record shape; prefer
`create_from_record()` for new/internal code.

---

## Minimal v2 element

A native v2 element that renders a fixed string and has no element-specific configuration
fields. It still participates in the normal edit-form lifecycle and satisfies the native
registration contract (`form_element_interface` + `renderable_element_interface`) — it just has
nothing of its own to add to the form:

```php
namespace customcertelement_myelement;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\layout_element_interface;
use mod_customcert\element\stylable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

class element extends base_element implements
    form_element_interface,
    renderable_element_interface,
    stylable_element_interface,
    layout_element_interface
{
    public function build_form(MoodleQuickForm $mform): void {
        // No element-specific fields to add.
    }

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
> rendering may implement `renderable_element_interface` directly without these two. A subclass of
> `mod_customcert\element` already inherits both interfaces even when not declared explicitly;
> this example declares them for clarity.

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
use mod_customcert\element\stylable_payload;
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
        $payload = $this->get_payload();
        if (isset($payload['myfield'])) {
            $mform->getElement('myfield')->setValue($payload['myfield']);
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
        return array_merge(
            ['myfield' => (string)($formdata->myfield ?? '')],
            stylable_payload::from_form($formdata)->to_array(),
        );
    }

    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        element_helper::render_content($pdf, $this, (string)($this->get_payload()['myfield'] ?? ''));
    }

    public function render_html(?element_renderer $renderer = null): string {
        return element_helper::render_html_content($this, (string)($this->get_payload()['myfield'] ?? ''));
    }
}
```

---

## Restore guidance

If your element stores internal Moodle IDs (file itemids, course module IDs, etc.) in its JSON
payload, implement `restorable_element_interface` to remap them after restore. The restore task
calls the hook as `$instance->after_restore_from_backup($this)`:

```php
use mod_customcert\element\restorable_element_interface;
use restore_customcert_activity_task;

class element extends base_element implements restorable_element_interface, /* ... */
{
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        global $DB;

        $payload = $this->get_payload();
        $oldid = (int)($payload['coursemoduleid'] ?? 0);
        if (!$oldid) {
            return;
        }

        // Remap the stored course module id using the restore task's mapping API.
        $newid = $restore->get_mappingid('course_module', $oldid);
        if ($newid) {
            $payload['coursemoduleid'] = $newid;
            $DB->set_field('customcert_elements', 'data', json_encode($payload), ['id' => $this->get_id()]);
        }
    }
}
```

See `element/date/classes/element.php` and `element/image/classes/element.php` for the full
production restore implementations this sketch is based on. Historical database-upgrade
(`db/upgrade.php`) logic that normalises old persisted data shapes (e.g. scalar-key mappings,
border thickness, digital-signature normalization) is independent of this runtime interface and is
retained for as long as needed to safely upgrade historical data.

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

`element_repository::copy_element()` is the current service-layer entry point that invokes
`copy_from()`; it is unrelated to the removed `element::copy_element()` instance hook of the same
name.

---

## Payload vs layout

**Payload** (stored in `customcert_elements.data` as JSON) is element-specific data managed by
the element class itself via `persistable_element_interface::normalise_data()`.
Examples: text content, a selected option, a file ID, font/colour/width styling values.

**Layout** (posx, posy, refpoint, alignment) is managed by the element repository and layout
service. Element classes should **not** write layout values directly to the database.
Implement `layout_element_interface` to expose these values for rendering helpers.

`raw_data_element_interface::get_raw_data()` is a separate, optional contract for the persistence
boundary: it lets an element whose `get_data()` return value differs from what should be stored
(for example a derived/formatted view) tell `element_repository` exactly what to persist. It has
no connection to legacy scalar unwrapping, which no longer exists.

---

## Security: scoped element and page lookups

Always use the scoped repository/service methods when loading elements or pages.
Never load an element by ID alone — always verify it belongs to the authorised template:

```php
// Correct — verifies the element belongs to the authorised template.
$element = $elementrepository->get_for_template_or_fail($templateid, $elementid);

// Incorrect — no ownership check.
$element = $DB->get_record('customcert_elements', ['id' => $elementid]);
```

`element_repository::get_for_template_or_fail()` verifies the element belongs to the authorised
template (it joins through the owning page to check this); it does not itself verify a
caller-supplied page id. If a caller-supplied page id also needs to be verified, check it
separately with `page_repository::get_for_template_or_fail($templateid, $pageid)`:

```php
// Also verify a caller-supplied page id belongs to the authorised template.
$page = $pagerepository->get_for_template_or_fail($templateid, $pageid);
```

The service layer enforces these checks. Bypassing them is a security risk.

---

## Further reading

- `CHANGES.md` — full dated history of each deprecation and removal (#993–#997 under
  `[Unreleased] → Removed`)
- `classes/element/` — all v2 interface definitions
- Bundled elements (e.g. `element/coursename`, `element/text`, `element/date`) — real-world v2
  implementations to use as reference
