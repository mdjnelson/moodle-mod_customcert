# Migrating customcert element plugins to Element System v2 (5.3)

## Overview

Moodle 5.2 introduced **Element System v2** — a set of explicit PHP interfaces that replace the
legacy hook methods that lived on the `mod_customcert\element` base class — and deprecated the
historical element runtime API.

Moodle 5.3 retains a deprecated compatibility bridge so a genuine Moodle 4.5-era third-party
element, and a plugin already migrated to the released Moodle 5.2 contract, can both continue to
run across a direct Moodle 4.5 LTS → 5.3 LTS upgrade. Native Element System v2 elements are
unaffected and never go through this bridge.

**Moodle 5.3 is the final release supporting the legacy runtime element API.** The compatibility
bridge — the legacy hook methods on `mod_customcert\element` and the `legacy_element_adapter`
that wraps legacy elements — is removed in the Moodle 6.0-compatible release. Plugin authors
should migrate to Element System v2 now rather than relying on the bridge.

Historical database-upgrade and backup/restore migration logic for old persisted element data is
a separate, independent concern from this runtime API removal, and may remain for as long as
required to safely upgrade or restore historical data.

Until `MOODLE_503_STABLE` is created, references in this guide to "Moodle 5.3" mean the current
`main` branch.

### The released Moodle 5.2 compatibility boundary

The released Moodle 5.2 compatibility surface — including its requirement that third-party
`render()`/`render_html()` overrides adopt the 5.2 typed declarations — remains the normal
compatibility baseline, and a plugin that already migrated for 5.2 continues to work unchanged on
5.3. Moodle 5.3 adds only narrow additional corrections needed for a genuine Moodle 4.5-era
element (one that never ran through 5.2) to cross directly to 5.3 — for example, accepting the
genuine 4.5-era untyped `render()`/`render_html()` shape through the compatibility bridge. This
does not retroactively change the released 5.2 contract, and is not a general licence to recreate
older declarations: the old one-record `mod_customcert\template` constructor, for instance, was
not restored. `MOODLE_502_STABLE` does not automatically receive this bridge's class-loading
changes; independent 5.2 bug fixes remain eligible for normal stable maintenance as usual.

---

## Identifying legacy element usage

A plugin still depends on the legacy compatibility bridge if it overrides any of the historical
hooks listed in the table below instead of implementing the corresponding Element System v2
interface — for example `render_form_elements()`, `definition_after_data()`,
`validate_form_elements()`, `save_form_elements()`, `save_unique_data()`, `after_restore()`, or
`copy_element()`.

A running site also surfaces this at runtime: with `DEBUG_DEVELOPER` enabled, a legacy element
triggers a notice similar to:

> Legacy custom certificate element `customcertelement_foo` is using the deprecated
> mod_customcert legacy element API. Migrate the plugin to Element System v2 interfaces.

You do not need to inspect `legacy_element_adapter` or any other internal implementation detail to
answer this. The migration test is conceptually: implement the appropriate Element System v2
interfaces directly, rather than depending on the compatibility bridge. See
`docs/element_authoring_guide.md` for the current authoring reference.

---

## Legacy hooks and their Element System v2 replacements

The following historical `mod_customcert\element` methods are deprecated since Moodle 5.2. They
remain callable through Moodle 5.3 via the compatibility bridge, but new and migrated code should
use the Element System v2 interfaces instead:

| Legacy hook | Element System v2 replacement |
|---|---|
| `render_form_elements()` | `form_element_interface::build_form()` |
| `definition_after_data()` | `preparable_form_interface::prepare_form()` |
| `validate_form_elements()` | `validatable_element_interface::validate()` |
| `save_form_elements()` / `save_unique_data()` | `persistable_element_interface::normalise_data()` |
| `render()` / `render_html()` | `renderable_element_interface` |
| `after_restore()` | `restorable_element_interface::after_restore_from_backup()` |
| `copy_element()` | `copyable_element_interface::copy_from()` |
| `delete()` | `element_repository::delete()` |

`legacy_element_adapter` is the internal mechanism that bridges these hooks to the Element System
v2 interfaces at runtime. It is a deprecated implementation detail, not a public API — plugin
authors should not instantiate it or depend on it directly, and native v2 elements never go
through it. It is removed together with the rest of the legacy runtime bridge in the Moodle
6.0-compatible release.

---

## Required interfaces

For native Element System v2 elements, registered classes must implement
`form_element_interface` and `renderable_element_interface`; `form_element_interface` already
extends `element_interface`. The registry throws a `coding_exception` at registration time if a
class satisfies neither that native v2 contract nor the legacy compatibility path below.

Historical classes extending `mod_customcert\element` without implementing these v2 interfaces
are not rejected — they are instead accepted through the deprecated legacy compatibility path and
wrapped by `legacy_element_adapter`.

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

All interfaces live under `mod_customcert\element\`.

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
> this example declares them for clarity. Note that this example provides its own `build_form()`
> rather than relying on the inherited one, which only bridges to the deprecated legacy
> `render_form_elements()` hook.

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
production restore implementations this sketch is based on.

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

- `CHANGES.md` — 5.3 deprecated/compatibility entry with full migration table
- `classes/element/` — all v2 interface definitions
- Bundled elements (e.g. `element/coursename`, `element/text`, `element/date`) — real-world v2
  implementations to use as reference
