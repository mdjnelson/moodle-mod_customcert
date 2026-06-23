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

Implement `element_payload_interface` on a dedicated payload class for any new element
with non-trivial data (more than one or two fields, or fields with meaningful
constraints). Simple elements with no real invariants can skip it. Use the payload
class inside:

- `normalise_data(stdClass $formdata): array` — construct the payload from form data, call `to_array()`.
- `prepare_form(MoodleQuickForm $mform): void` — decode stored JSON via `from_array()`, read typed properties.
- `render(...)` / `render_html(...)` — decode stored JSON via `from_array()`, read typed properties.

---

## Prototype: `coursename_payload`

The `coursename` element ships as the reference implementation.

**File**: `element/coursename/classes/coursename_payload.php`

```php
namespace customcertelement_coursename;

use mod_customcert\element\element_payload_interface;

final class coursename_payload implements element_payload_interface {
    public function __construct(
        public readonly int    $coursenamedisplay,
        public readonly string $font,
        public readonly int    $fontsize,
        public readonly string $colour,
        public readonly int    $width,
    ) {}

    public static function from_array(array $data): static {
        return new static(
            coursenamedisplay: (int)($data['coursenamedisplay'] ?? element::COURSE_FULL_NAME),
            font:              (string)($data['font'] ?? ''),
            fontsize:          (int)($data['fontsize'] ?? 0),
            colour:            (string)($data['colour'] ?? ''),
            width:             (int)($data['width'] ?? 0),
        );
    }

    public function to_array(): array {
        return [
            'coursenamedisplay' => $this->coursenamedisplay,
            'font'              => $this->font,
            'fontsize'          => $this->fontsize,
            'colour'            => $this->colour,
            'width'             => $this->width,
        ];
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
        font:              (string)($formdata->font ?? ''),
        fontsize:          (int)($formdata->fontsize ?? 0),
        colour:            (string)($formdata->colour ?? ''),
        width:             (int)($formdata->width ?? 0),
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
```

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
- Simple payloads with no meaningful invariants may implement `validate()` as a no-op.
  The method is on the interface so callers can always invoke it without an `instanceof`
  check, not because every payload must have constraints.
- The default for `coursenamedisplay` was changed from `0` (the old raw-array fallback)
  to `element::COURSE_FULL_NAME`. `0` was never a valid display value; `COURSE_FULL_NAME`
  is the correct sentinel for "not explicitly set".
- Payload classes should be `final` to prevent accidental inheritance.
- Use PHP 8.x constructor property promotion (`public readonly`) to keep payload
  classes concise and immutable.
- The payload class lives in the element sub-plugin namespace, not in `mod_customcert`,
  so it can reference element-specific constants (e.g. `element::COURSE_FULL_NAME`).
