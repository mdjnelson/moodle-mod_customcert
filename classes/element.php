<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The base class for the customcert elements.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use coding_exception;
use InvalidArgumentException;
use mod_customcert\element\layout_element_interface;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\stylable_element_interface;
use MoodleQuickForm;
use stdClass;

/**
 * Class element
 *
 * All customcert element plugins are based on this class.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class element implements
    form_element_interface,
    layout_element_interface,
    stylable_element_interface {
    /*
     * Note: this base class intentionally does NOT implement renderable_element_interface.
     * Native v2 elements implement that interface themselves with the strict typed
     * render()/render_html() contract. Genuine Moodle 4.5-era third-party elements
     * declare untyped historical render() signatures; keeping the strict abstract
     * methods on this base would make those subclasses unloadable (PHP fatal).
     * The factory wraps non-renderable legacy instances with legacy_element_adapter.
     */
    /**
     * @var string The left alignment constant.
     */
    public const string ALIGN_LEFT = 'L';

    /**
     * @var string The centered alignment constant.
     */
    public const string ALIGN_CENTER = 'C';

    /**
     * @var string The right alignment constant.
     */
    public const string ALIGN_RIGHT = 'R';

    /**
     * @var int The id.
     */
    protected int $id;

    /**
     * @var int The page id.
     */
    protected int $pageid;

    /**
     * @var string The name.
     */
    protected string $name;

    /**
     * @var mixed The data.
     */
    protected mixed $data;

    /**
     * @var int The position x.
     */
    protected ?int $posx;

    /**
     * @var int The position y.
     */
    protected ?int $posy;

    /**
     * @var int The refpoint.
     */
    protected ?int $refpoint;

    /**
     * @var string The alignment.
     */
    protected string $alignment;

    /**
     * @var string The element type (plugin name, e.g. 'text', 'code').
     */
    private string $customcertelementtype;

    /**
     * @var bool $showposxy Show position XY form elements?
     */
    protected bool $showposxy;

    /**
     * @var edit_element_form Element edit form instance.
     */
    private ?edit_element_form $editelementform = null;

    /**
     * Clone of the raw element DB record for legacy property access.
     *
     * Historical (Moodle 4.5-era) elements often read `$this->element->...` directly.
     *
     * @var stdClass
     * @deprecated since Moodle 5.2 - Use the typed getters instead.
     */
    protected $element;

    /**
     * Legacy font name property.
     *
     * @var string|null
     * @deprecated since Moodle 5.2 - Use get_font() instead. Backed by JSON data.
     */
    protected $font;

    /**
     * Legacy font size property.
     *
     * @var int|string|null
     * @deprecated since Moodle 5.2 - Use get_fontsize() instead. Backed by JSON data.
     */
    protected $fontsize;

    /**
     * Legacy colour property.
     *
     * @var string|null
     * @deprecated since Moodle 5.2 - Use get_colour() instead. Backed by JSON data.
     */
    protected $colour;

    /**
     * Legacy width property.
     *
     * @var int|string|null
     * @deprecated since Moodle 5.2 - Use get_width() instead. Backed by JSON data.
     */
    protected $width;


    /**
     * Constructor.
     *
     * @param stdClass $element the element data
     */
    public function __construct(stdClass $element) {
        $showposxy = get_config('customcert', 'showposxy');

        // Normalise types defensively — DB/fixtures may provide strings for numeric fields.
        // Helper: return null if unset or empty string, otherwise cast.
        $optional = static function ($value, callable $cast) {
            return (isset($value) && $value !== '') ? $cast($value) : null;
        };

        // Required scalars.
        $this->id = isset($element->id) ? (int) $element->id : 0;
        $this->pageid = isset($element->pageid) ? (int) $element->pageid : 0;
        $this->name = isset($element->name) ? (string) $element->name : '';

        // Element type (plugin name).
        $this->customcertelementtype = isset($element->element) ? (string) $element->element : '';

        // Mixed data payload.
        $this->data = $element->data ?? null;

        // Optional fields (preserve NULL when unset or empty string).
        $this->posx = $optional($element->posx ?? null, 'intval');
        $this->posy = $optional($element->posy ?? null, 'intval');
        $this->refpoint = $optional($element->refpoint ?? null, 'intval');

        $this->showposxy = (bool) ($showposxy ?? false);
        $this->set_alignment($element->alignment ?? self::ALIGN_LEFT);

        // One compatibility path for historical protected state (4.5-era plugins).
        $this->initialise_legacy_state($element);
    }

    /**
     * Returns the id.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Returns the page id.
     *
     * @return int
     */
    public function get_pageid(): int {
        return $this->pageid;
    }

    /**
     * Returns the name.
     *
     * @return int
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Returns the data.
     *
     * For legacy backwards-compatibility: if the stored data is a generic migration wrapper
     * (a JSON object with a 'value' key and only migration visual/layout metadata keys),
     * AND the element type is not a known bundled element type, this method unwraps and
     * returns the scalar value directly so that legacy third-party elements extending this
     * class continue to receive the original scalar they stored.
     *
     * Bundled element types always receive the raw stored data, even if the JSON object
     * contains a 'value' key, because their save/load code expects the full JSON payload.
     *
     * @return mixed
     */
    public function get_data(): mixed {
        if (
            is_string($this->data)
            && $this->should_unwrap_generic_migration_wrapper()
            && self::is_generic_migration_wrapper($this->data)
        ) {
            $decoded = json_decode($this->data, true);
            return $decoded['value'];
        }
        return $this->data;
    }

    /**
     * Return true if this element instance should unwrap a generic migration wrapper in get_data().
     *
     * Unwrapping is only applied to unknown/third-party element types. Bundled element types
     * use structured JSON payloads and must not be unwrapped.
     *
     * @return bool
     */
    private function should_unwrap_generic_migration_wrapper(): bool {
        if ($this->customcertelementtype === '') {
            return false;
        }
        return !in_array($this->customcertelementtype, self::BUNDLED_ELEMENT_TYPES, true);
    }

    /**
     * The set of JSON keys that the upgrade migration adds as visual/layout metadata.
     * A JSON object is only considered a generic migration wrapper if all its keys
     * are either 'value' or one of these migration-only keys.
     *
     * @var string[]
     */
    private const MIGRATION_VISUAL_KEYS = ['width', 'height', 'font', 'fontsize', 'colour', 'alphachannel'];

    /**
     * Bundled/core customcert element types that use structured JSON payloads.
     * These element types must never have their data unwrapped by get_data(),
     * even if the JSON object looks like a generic migration wrapper.
     *
     * @var string[]
     */
    private const BUNDLED_ELEMENT_TYPES = [
        'bgimage',
        'border',
        'categoryname',
        'code',
        'coursefield',
        'coursename',
        'date',
        'digitalsignature',
        'expiry',
        'grade',
        'gradeitemname',
        'image',
        'qrcode',
        'studentname',
        'teachername',
        'text',
        'userfield',
        'userpicture',
    ];

    /**
     * Return true if the given JSON string is a generic migration scalar wrapper.
     *
     * A generic migration wrapper is a JSON object that:
     *  - Has a 'value' key.
     *  - Has no keys other than 'value' and the known migration visual/layout metadata keys
     *    (width, height, font, fontsize, colour, alphachannel).
     *
     * This is used by get_data() to provide backwards-compatibility for legacy third-party
     * elements that call get_data() and expect the original scalar value.
     *
     * @param string $data The raw JSON string from the data column.
     * @return bool
     */
    public static function is_generic_migration_wrapper(string $data): bool {
        $decoded = json_decode($data, true);
        if (!is_array($decoded) || !array_key_exists('value', $decoded)) {
            return false;
        }
        $value = $decoded['value'];
        if (
            $value !== null
            && !is_scalar($value)
            && !(is_array($value) && array_is_list($value))
        ) {
            return false;
        }
        $allowed = array_merge(['value'], self::MIGRATION_VISUAL_KEYS);
        foreach (array_keys($decoded) as $key) {
            if (!in_array($key, $allowed, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the font name.
     *
     * @return string|null
     */
    public function get_font(): ?string {
        return $this->get_string_from_data_key('font');
    }

    /**
     * Returns the font size.
     *
     * @return int|null
     */
    public function get_fontsize(): ?int {
        return $this->get_int_from_data_key('fontsize');
    }

    /**
     * Returns the font colour.
     *
     * @return string|null
     */
    public function get_colour(): ?string {
        return $this->get_string_from_data_key('colour');
    }

    /**
     * Return the decoded JSON payload stored in 'data' or an empty array when not valid JSON.
     *
     * This helper is intended for element implementations that store structured data
     * in the JSON 'data' column. It never throws; invalid or non-JSON values produce [].
     *
     * @return array<string,mixed>
     */
    public function get_payload(): array {
        $raw = $this->data;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    /**
     * Return a scalar value for simple elements.
     *
     * For JSON payloads containing {"value": <scalar>}, this returns that scalar cast to string.
     * For structured payloads without a single 'value', returns null.
     *
     * @return string|null The scalar value or null if not applicable.
     */
    public function get_value(): ?string {
        $raw = $this->data;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && array_key_exists('value', $decoded)) {
                return is_scalar($decoded['value']) ? (string)$decoded['value'] : null;
            }
        }
        return null;
    }

    /**
     * Returns the position x.
     *
     * @return int|null
     */
    public function get_posx(): ?int {
        return $this->posx ?? null;
    }

    /**
     * Returns the position y.
     *
     * @return int|null
     */
    public function get_posy(): ?int {
        return $this->posy ?? null;
    }

    /**
     * Returns the width.
     *
     * @return int|null
     */
    public function get_width(): ?int {
        return $this->get_int_from_data_key('width');
    }

    /**
     * Returns the refpoint.
     *
     * @return int
     */
    public function get_refpoint(): ?int {
        return $this->refpoint ?? null;
    }

    /**
     * Returns the alignment.
     *
     * @return string The current alignment value.
     */
    public function get_alignment(): ?string {
        return $this->alignment ?? self::ALIGN_LEFT;
    }

    /**
     * Returns the type of the element.
     *
     * @return string
     */
    public function get_type(): string {
        $classname = get_class($this);
        $parts = explode('\\', $classname);
        $pluginname = reset($parts);

        return str_replace('customcertelement_', '', $pluginname);
    }

    /**
     * Sets the alignment.
     *
     * @param string $alignment The new alignment.
     *
     * @throws InvalidArgumentException if the provided new alignment is not valid.
     */
    protected function set_alignment(string $alignment): void {
        $validvalues = [self::ALIGN_LEFT, self::ALIGN_CENTER, self::ALIGN_RIGHT];
        if (!in_array($alignment, $validvalues)) {
            throw new InvalidArgumentException("'$alignment' is not a valid alignment value. It has to be one of " .
                implode(', ', $validvalues));
        }
        $this->alignment = $alignment;
    }

    /**
     * Helper to extract an integer value from the JSON-encoded data by key.
     * Returns null when the key is missing or empty; preserves 0 as meaningful.
     *
     * @param string $key
     * @return int|null
     */
    private function get_int_from_data_key(string $key): ?int {
        $payload = $this->get_payload();
        if (array_key_exists($key, $payload)) {
            $value = $payload[$key];
            return ($value === '' || $value === null) ? null : (int)$value;
        }
        return null;
    }

    /**
     * Helper to extract a string value from the JSON-encoded data by key.
     * Returns null when the key is missing or empty.
     *
     * @param string $key
     * @return string|null
     */
    private function get_string_from_data_key(string $key): ?string {
        $payload = $this->get_payload();
        if (array_key_exists($key, $payload)) {
            $value = $payload[$key];
            return ($value === '' || $value === null) ? null : (string)$value;
        }
        return null;
    }

    /**
     * This defines if an element plugin can be added to a certificate.
     * Can be overridden if an element plugin wants to take over the control.
     *
     * @return bool returns true if the element can be added, false otherwise
     */
    public static function can_add(): bool {
        return true;
    }


    /**
     * Set edit form instance for the custom cert element.
     *
     * @param edit_element_form $editelementform
     */
    public function set_edit_element_form(edit_element_form $editelementform): void {
        $this->editelementform = $editelementform;
    }

    /**
     * Get edit form instance for the custom cert element.
     *
     * @return edit_element_form
     */
    public function get_edit_element_form(): edit_element_form {
        if (empty($this->editelementform)) {
            throw new coding_exception('Edit element form instance is not set.');
        }

        return $this->editelementform;
    }

    /**
     * This defines if an element plugin need to add the "Save and continue" button.
     * Can be overridden if an element plugin wants to take over the control.
     *
     * @return bool returns true if the element need to add the "Save and continue" button, false otherwise
     */
    public function has_save_and_continue(): bool {
        return false;
    }

    /**
     * Initialise historical protected state used by Moodle 4.5-era element plugins.
     *
     * Translates the current record (and JSON data column when style fields have migrated)
     * into the legacy `$element` record view plus `$font`/`$fontsize`/`$colour`/`$width`.
     *
     * @param stdClass $element Raw element record
     * @return void
     */
    protected function initialise_legacy_state(stdClass $element): void {
        // Keeping this for legacy reasons so we do not break third-party elements.
        $this->element = clone($element);

        // Mirror the get_data() migration-wrapper unwrapping onto the legacy record view, so
        // legacy third-party code that reads $this->element->data directly sees the same
        // historical scalar as get_data() rather than the migrated JSON wrapper.
        if (isset($this->element->data) && is_string($this->element->data)) {
            $this->element->data = $this->get_data();
        }

        // Prefer explicit record fields (genuine 4.5 DB shape), else JSON-backed getters.
        $this->font = isset($element->font) && $element->font !== ''
            ? (string) $element->font
            : $this->get_font();
        $this->fontsize = isset($element->fontsize) && $element->fontsize !== ''
            ? $element->fontsize
            : $this->get_fontsize();
        $this->colour = isset($element->colour) && $element->colour !== ''
            ? (string) $element->colour
            : $this->get_colour();
        $this->width = isset($element->width) && $element->width !== ''
            ? $element->width
            : $this->get_width();

        // Mirror resolved values onto the legacy record view when missing.
        if (!isset($this->element->font)) {
            $this->element->font = $this->font;
        }
        if (!isset($this->element->fontsize)) {
            $this->element->fontsize = $this->fontsize;
        }
        if (!isset($this->element->colour)) {
            $this->element->colour = $this->colour;
        }
        if (!isset($this->element->width)) {
            $this->element->width = $this->width;
        }
    }

    /**
     * Add fields to the edit form (v2 form_element_interface entry point).
     *
     * Bridges to the historical render_form_elements() hook for legacy plugins.
     *
     * @param MoodleQuickForm $mform the edit_form instance.
     */
    public function build_form(MoodleQuickForm $mform): void {
        $this->render_form_elements($mform);
    }

    /**
     * Renders common form elements (font, colour, position, width, refpoint, alignment).
     *
     * @deprecated since Moodle 5.2
     * @param MoodleQuickForm $mform the edit_form instance.
     */
    public function render_form_elements($mform) {
        debugging(
            'render_form_elements() is deprecated since Moodle 5.2. '
            . 'Use element_helper::render_common_form_elements() instead.',
            DEBUG_DEVELOPER
        );
        // Render the common elements.
        element_helper::render_form_element_font($mform);
        element_helper::render_form_element_colour($mform);
        if ($this->showposxy) {
            element_helper::render_form_element_position($mform);
        }
        element_helper::render_form_element_width($mform);
        element_helper::render_form_element_refpoint($mform);
        element_helper::render_form_element_alignment($mform);
    }

    /**
     * Sets the data on the form when editing an element.
     * Can be overridden if more functionality is needed.
     *
     * @param MoodleQuickForm $mform the edit_form instance
     * @deprecated since Moodle 5.2
     */
    public function definition_after_data($mform) {
        // Set the common form elements data.
        element_helper::set_data_on_form_element_font($this, $mform);
        element_helper::set_data_on_form_element_colour($this, $mform);
        if ($this->showposxy) {
            element_helper::set_data_on_form_element_position($this, $mform);
        }
        element_helper::set_data_on_form_element_width($this, $mform);
        element_helper::set_data_on_form_element_refpoint($this, $mform);
        element_helper::set_data_on_form_element_alignment($this, $mform);
    }

    /**
     * Performs validation on the element values.
     * Can be overridden if more functionality is needed.
     *
     * @param array $data the form data
     * @param array $files the form files
     * @return array any errors from validation
     * @deprecated since Moodle 5.2
     */
    public function validate_form_elements($data, $files) {
        // Validate the common form elements.
        $errors = [];
        $errors += element_helper::validate_form_element_colour($data);
        if ($this->showposxy) {
            $errors += element_helper::validate_form_element_position($data);
        }
        $errors += element_helper::validate_form_element_width($data);
        return $errors;
    }

    /**
     * This will handle saving data that has been entered into the form.
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $data the form data
     * @return string the unique data to store
     * @deprecated since Moodle 5.2
     */
    public function save_unique_data($data) {
        return '';
    }

    /**
     * Handles any extra processing needed when an element is restored from a backup.
     * Can be overridden if more functionality is needed.
     *
     * @deprecated since Moodle 5.2 — implement restorable_element_interface::after_restore_from_backup() instead.
     * @param mixed $restore the restore task
     */
    public function after_restore($restore) {
    }
}
