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
| `persistable_element_interface` | The element has editable values to store in the JSON payload, including the standard `font`, `fontsize`, `colour` and `width` fields. `normalise_data()` must return them, otherwise nothing is saved (`{}`). `posx`, `posy`, `refpoint` and `alignment` are layout data and are saved separately. |
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
