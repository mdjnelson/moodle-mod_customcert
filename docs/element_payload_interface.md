# Typed element payloads (`element_payload_interface`)

> **Status**: introduced in 5.3.0 (#815)

## Background

Element payload data is stored as JSON in `customcert_elements.data`. Before 5.3 the
PHP layer worked with raw associative arrays, which made it hard to know which keys
were valid, which were required, what types values should be, and whether data had
already been normalised.

`element_payload_interface` introduces a lightweight typed-payload pattern. The
database still stores JSON; the interface governs the PHP layer only.

---

## The interface

```php
namespace mod_customcert\element;

interface element_payload_interface {
    public static function from_array(array $data): static;
    public function to_array(): array;
    public function validate(): void;
}
```

| Method | Responsibility |
|---|---|
| `from_array(array $data)` | Deserialise from a decoded JSON array. Apply safe defaults; cast to canonical types. |
| `to_array()` | Serialise back to an array suitable for `json_encode()`. Must round-trip cleanly. |
| `validate()` | Assert internal consistency. Throw `coding_exception` or `invalid_parameter_exception` on failure. |

---

## When to use it

Implement `element_payload_interface` on a dedicated payload class only when the
element has a genuine invariant, default, or conditional-serialization rule to
enforce — an enum-membership check, an all-or-none field grouping, omitting a key
when it's null, etc. Field count alone is not the deciding factor: several bundled
elements have two or three payload fields with no constraints and do not warrant a
dedicated class — see
[Simple elements: skip the wrapper class](#simple-elements-skip-the-wrapper-class)
below for what to do instead. When a payload class *is* warranted, use it inside:

- `normalise_data(stdClass $formdata): array` — construct the payload from form data, call `to_array()`.
- `prepare_form(MoodleQuickForm $mform): void` — decode stored JSON via `from_array()`, read typed properties.
- `render(...)` / `render_html(...)` — decode stored JSON via `from_array()`, read typed properties.

---

## Bundled element payload classes

Only elements with a genuine invariant or conditional-serialization rule ship a
dedicated payload class: `coursename` (enum-membership check on
`coursenamedisplay`), and `image`, `bgimage`, `digitalsignature` (all-or-none
file-metadata field grouping, with null groups omitted from the serialized
array). Simple elements — those that store nothing but the four
`stylable_payload` fields, optionally plus one or more unconstrained scalars —
compose `stylable_payload` directly in `normalise_data()` instead of wrapping it
in an element-specific class; see
[Simple elements: skip the wrapper class](#simple-elements-skip-the-wrapper-class)
below. The `coursename` element serves as the reference implementation for
elements that *do* warrant a dedicated payload class.

**File**: `element/coursename/classes/coursename_payload.php`

```php
namespace customcertelement_coursename;

use mod_customcert\element\element_payload_interface;
use mod_customcert\element\stylable_payload;

final class coursename_payload implements element_payload_interface {
    public function __construct(
        /** @var int One of element::COURSE_FULL_NAME or element::COURSE_SHORT_NAME. */
        public readonly int $coursenamedisplay,
        /** @var stylable_payload The four standard visual style fields. */
        public readonly stylable_payload $style,
    ) {}

    public static function from_array(array $data): static {
        return new static(
            coursenamedisplay: (int)($data['coursenamedisplay'] ?? element::COURSE_FULL_NAME),
            style: stylable_payload::from_array($data),
        );
    }

    public function to_array(): array {
        return array_merge(
            ['coursenamedisplay' => $this->coursenamedisplay],
            $this->style->to_array(),
        );
    }

    public function validate(): void {
        $valid = [element::COURSE_FULL_NAME, element::COURSE_SHORT_NAME];
        if (!in_array($this->coursenamedisplay, $valid, true)) {
            throw new \coding_exception(
                'coursename_payload: coursenamedisplay must be one of ' .
                implode(', ', $valid) . '; got ' . $this->coursenamedisplay
            );
        }
    }
}
```

### Usage inside `normalise_data()`

```php
public function normalise_data(stdClass $formdata): array {
    $payload = new coursename_payload(
        coursenamedisplay: (int)($formdata->coursenamedisplay ?? element::COURSE_FULL_NAME),
        style: stylable_payload::from_form($formdata),
    );
    $payload->validate();
    return $payload->to_array();
}
```

### Usage inside `prepare_form()` / `render()`

```php
$raw     = $this->get_data();
$decoded = $raw ? json_decode($raw, true) : [];
$payload = coursename_payload::from_array($decoded ?? []);

// Now use typed properties:
$mform->setDefault('coursenamedisplay', $payload->coursenamedisplay);
$mform->setDefault('font', $payload->style->font);
```

---

## Simple elements: skip the wrapper class

If an element stores only the four `stylable_payload` fields, or those fields
plus one or more unconstrained scalars, do not create a dedicated payload class
for it. There is nothing for `from_array()`/`to_array()`/`validate()` to do
beyond what `stylable_payload` and a plain type cast already provide, and a
class with a no-op `validate()` that's never read back as an object outside
`normalise_data()` is pure ceremony. Compose `stylable_payload` inline instead:

```php
public function normalise_data(stdClass $formdata): array {
    return array_merge(
        ['coursefield' => (string)($formdata->coursefield ?? '')],
        stylable_payload::from_form($formdata)->to_array(),
    );
}
```

For an element with no fields beyond the four style fields:

```php
public function normalise_data(stdClass $formdata): array {
    return stylable_payload::from_form($formdata)->to_array();
}
```

For an element with no style fields at all (e.g. two plain integers):

```php
public function normalise_data(stdClass $formdata): array {
    return [
        'width' => isset($formdata->width) ? (int)$formdata->width : 0,
        'height' => isset($formdata->height) ? (int)$formdata->height : 0,
    ];
}
```

Reach for a dedicated payload class only once the element has something worth
protecting — an enum-membership check, an all-or-none field grouping,
conditional key omission, etc. — the way `coursename_payload`, `image_payload`,
`bgimage_payload`, and `digitalsignature_payload` do.

---

## Writing your own payload class

1. Create `element/<type>/classes/<type>_payload.php` in your element plugin.
2. Declare `namespace customcertelement_<type>;` and `implement element_payload_interface`.
3. Add one `public readonly` constructor parameter per payload field.
4. In `from_array()`, apply safe defaults for every key and cast to the canonical type.
5. In `to_array()`, return exactly the same keys in a stable order.
6. In `validate()`, check invariants and throw `coding_exception` on failure.
7. Use the payload class in `normalise_data()`, `prepare_form()`, and `render()`.

### Skeleton

```php
<?php
declare(strict_types=1);
namespace customcertelement_myelement;

use coding_exception;
use mod_customcert\element\element_payload_interface;

final class myelement_payload implements element_payload_interface {
    public function __construct(
        public readonly string $myfield,
        // ... add more fields here
    ) {}

    public static function from_array(array $data): static {
        return new static(
            myfield: (string)($data['myfield'] ?? ''),
        );
    }

    public function to_array(): array {
        return [
            'myfield' => $this->myfield,
        ];
    }

    public function validate(): void {
        if ($this->myfield === '') {
            throw new coding_exception('myelement_payload: myfield must not be empty');
        }
    }
}
```

---

## Element authoring safety guidelines

- Store element-specific data in the JSON payload only. Do not duplicate layout fields such as `posx`, `posy`, `refpoint`, `alignment`, `sequence`, or `pageid` inside payload data.
- Use `stylable_payload` for the standard visual fields: `font`, `fontsize`, `colour`, and `width`.
- Do not load or mutate elements by raw `elementid` in request-facing code. Resolve the owning template/page first and use scoped repository helpers such as `get_for_template_or_fail()`.
- Treat `elementid`, `pageid`, and `templateid` request parameters as untrusted. Always verify ownership before editing, moving, deleting, or rendering private/admin-only data.
- Legacy element hooks are migration-only. New elements should implement the Element System v2 interfaces described above.

---

## Backward compatibility

- Existing JSON stored in `customcert_elements.data` is unaffected. `from_array()` is
  designed to accept legacy arrays with missing keys by applying safe defaults.
- The interface is **opt-in**. Existing elements that do not implement it continue to
  work unchanged.
- Third-party elements are not required to adopt this pattern, but it is strongly
  recommended for any new element.

---

## Design notes

- `validate()` is for **developer-facing invariant checks**, not user-facing form
  validation. User-facing validation belongs in `validatable_element_interface`.
- `validate()` is **not called automatically** by the framework. Call it explicitly
  in `normalise_data()` if you want invalid data to fail loudly at save time. Omitting
  the call is acceptable when the form layer already guarantees valid values.
- Don't create a payload class just to give a no-op `validate()` a home — if an
  element has no invariant to check, skip the class entirely and compose
  `stylable_payload` inline (see
  [Simple elements: skip the wrapper class](#simple-elements-skip-the-wrapper-class)).
  `validate()` is on the interface so callers of payload classes that *do* have
  invariants can invoke it without an `instanceof` check, not to justify
  manufacturing a class around zero constraints.
- The default for `coursenamedisplay` was changed from `0` (the old raw-array fallback)
  to `element::COURSE_FULL_NAME`. `0` was never a valid display value; `COURSE_FULL_NAME`
  is the correct sentinel for "not explicitly set".
- Payload classes should be `final` to prevent accidental inheritance.
- Use PHP 8.x constructor property promotion (`public readonly`) to keep payload
  classes concise and immutable.
- The payload class lives in the element sub-plugin namespace, not in `mod_customcert`,
  so it can reference element-specific constants (e.g. `element::COURSE_FULL_NAME`).
