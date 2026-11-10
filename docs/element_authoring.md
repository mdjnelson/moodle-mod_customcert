# Element authoring notes (Moodle 5.2)

Short guide for third-party `customcertelement_*` plugin authors on the released Moodle 5.2
Element System v2 contract. It describes only what exists in 5.2.

## Required versus optional interfaces

`mod_customcert\element` implements `form_element_interface` (which extends `element_interface`),
`layout_element_interface`, `stylable_element_interface` and `raw_data_element_interface`. It does
**not** implement `renderable_element_interface` and declares no `render()` or `render_html()`, so
historical plugins with untyped signatures still load (see [Legacy compatibility](#legacy-compatibility)).

A native v2 element must declare `renderable_element_interface` itself and implement its typed
`render()` and `render_html()` methods. The element registry accepts a class that implements
`form_element_interface` and `renderable_element_interface`, or a historical subclass of
`mod_customcert\element`. Override `build_form()`; the inherited one only bridges to the deprecated
`render_form_elements()` hook.

Add the others only when the element needs them:

| Interface | Add when |
|---|---|
| `persistable_element_interface` | The element has editable values to store in the JSON payload, including the standard `font`, `fontsize`, `colour` and `width` fields. `normalise_data()` must return the standard fields the element uses; omitted fields are not persisted, while any other returned fields are still saved. `posx`, `posy`, `refpoint` and `alignment` are layout data and are saved separately. |
| `preparable_form_interface` | Form fields must be pre-populated from stored data on edit. |
| `validatable_element_interface` | The element has validation rules of its own (see below). |
| `restorable_element_interface` | Stored references need remapping after backup restore. |
| `copyable_element_interface` | Duplicating a template needs custom handling (e.g. files). |

Elements without `persistable_element_interface` are saved through the deprecated legacy
`save_unique_data()` hook when they override it, and otherwise store `{}`. This exists for
compatibility with historical plugins, not as a pattern for new ones.

## Minimal example

Renders a fixed string using the standard position and style controls:

```php
namespace customcertelement_staticlabel;

use mod_customcert\element as base_element;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
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
        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    public function normalise_data(stdClass $formdata): array {
        return [
            'font' => (string)($formdata->font ?? ''),
            'fontsize' => (int)($formdata->fontsize ?? 0),
            'colour' => (string)($formdata->colour ?? ''),
            'width' => (int)($formdata->width ?? 0),
        ];
    }

    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        element_helper::render_content($pdf, $this, get_string('pluginname', 'customcertelement_staticlabel'));
    }

    public function render_html(?element_renderer $renderer = null): string {
        return element_helper::render_html_content($this, get_string('pluginname', 'customcertelement_staticlabel'));
    }
}
```

`form_element_interface` and the style and layout interfaces are inherited from the base class, so they
are not listed; `renderable_element_interface` must always be declared. The element has no `validate()`
because it has no rules of its own.

## Validation

- **Common fields** — `validation_service` always validates `colour`, `posx`/`posy` (when position
  fields are enabled) and `width` when they are submitted.
- **Element-specific rules** — implement `validatable_element_interface::validate()` only when you
  have rules of your own. Do not add it just to return `[]`; `validation_service` only calls it
  when present. Bundled elements without element-specific rules do not implement
  `validatable_element_interface`.
- **`validate_form_elements()`** — deprecated; still called for historical subclasses that override
  it, solely for compatibility.

## `get_data()` versus `get_raw_data()`

`get_data()` is the view used by rendering and form code. For a historical element (one with neither
`persistable_element_interface` nor `renderable_element_interface`) it may return a compatibility view: a
scalar unwrapped from a generic migration JSON wrapper. It is not always the stored value.

`raw_data_element_interface::get_raw_data()` exposes the unmodified element
data intended for persistence. The persistence layer uses it so migrated fields hidden by the
compatibility view (`font`, `fontsize`, `colour`, `width`, ...) are not lost on save.

For a persistable or native v2 element, `get_data()` does not apply legacy migration-wrapper unwrapping. Use
`get_payload()` for structured reads.

## Renderer contract

`render()` and `render_html()` receive an optional `element_renderer`. The published interface
declares both `render_pdf()` and `render_html()`, but the implementations are not interchangeable:
`html_renderer::render_pdf()` intentionally does nothing and `pdf_renderer::render_html()`
intentionally returns an empty string. Use the renderer that matches the output you need.

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
wrapped by `legacy_element_adapter` (see [Legacy compatibility](#legacy-compatibility)) need one as well.

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
restored course context. Historical elements that still override the deprecated `after_restore()` are
still called, with a developer debugging notice.

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

## Legacy compatibility

Two generations of existing third-party elements remain supported without any change by their authors.
Both extend `mod_customcert\element` and neither declares `renderable_element_interface`, so
`element_factory` wraps them in `legacy_element_adapter`:

- **Historical (Moodle 4.5-5.1)** elements declare untyped `render($pdf, $preview, $user)` and
  `render_html()` methods. The adapter calls them with only the arguments the method declares and treats a
  `null` `render_html()` result as an empty string.
- **Released 5.2** elements declare the typed v2 `render()` and `render_html()` signatures and relied on
  inheriting `renderable_element_interface` from the base class, which no longer provides it. The adapter
  passes them the renderer.

Historical elements can still read the protected `$this->element`, `$this->font`, `$this->fontsize`,
`$this->colour` and `$this->width` properties, which are filled from the element record or its stored JSON.

In both cases the compatibility layer preserves the optional v2 lifecycle hooks the element implements
(`persistable_element_interface`, `validatable_element_interface`, `preparable_form_interface`,
`restorable_element_interface` and `copyable_element_interface`). The relevant services (for example
`persistence_helper`, `validation_service` and `form_service`) dispatch to the wrapped element where
appropriate, so `normalise_data()`, `validate()`, `prepare_form()`, `after_restore_from_backup()` and
`copy_from()` still run.

This is a compatibility layer, not an API to target. New elements should declare
`renderable_element_interface` explicitly, as in the minimal example above; native v2 elements are never
wrapped.
