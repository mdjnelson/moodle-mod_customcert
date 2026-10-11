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
 * @copyright  2013 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use coding_exception;
use InvalidArgumentException;
use mod_customcert\element\layout_element_interface;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\raw_data_element_interface;
use mod_customcert\element\stylable_element_interface;
use mod_customcert\element_helper;
use MoodleQuickForm;
use stdClass;

/**
 * Class element
 *
 * All customcert element plugins are based on this class.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class element implements
    form_element_interface,
    layout_element_interface,
    raw_data_element_interface,
    stylable_element_interface {
    /*
     * Note: this base class intentionally does NOT implement renderable_element_interface.
     * Native v2 elements implement that interface themselves with the strict typed
     * render()/render_html() contract. Keeping the strict abstract methods on this base
     * would force every subclass onto that contract even when it only needs form/layout
     * behaviour for tests or internal helpers.
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
     * @var bool $showposxy Show position XY form elements?
     */
    protected bool $showposxy;

    /**
     * @var edit_element_form Element edit form instance.
     */
    private ?edit_element_form $editelementform = null;

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

        // Mixed data payload.
        $this->data = $element->data ?? null;

        // Optional fields (preserve NULL when unset or empty string).
        $this->posx = $optional($element->posx ?? null, 'intval');
        $this->posy = $optional($element->posy ?? null, 'intval');
        $this->refpoint = $optional($element->refpoint ?? null, 'intval');

        $this->showposxy = (bool) ($showposxy ?? false);
        $this->set_alignment($element->alignment ?? self::ALIGN_LEFT);
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
     * Returns the data payload as stored for the current element runtime.
     *
     * This is the current persistence representation (typically a JSON object string).
     * It does not unwrap historical scalar migration wrappers.
     *
     * @return mixed
     */
    public function get_data(): mixed {
        return $this->data;
    }

    /**
     * Returns the raw persistence representation of the element data.
     *
     * For this base class, get_data() already returns the exact persistence
     * representation, so this simply mirrors it. Subclasses whose get_data() returns a
     * representation tailored for rendering or form handling should override this method
     * to return the untouched value that must actually be persisted.
     *
     * @return mixed
     */
    public function get_raw_data(): mixed {
        return $this->data;
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
     * Add fields to the edit form (v2 form_element_interface entry point).
     *
     * Default implementation renders the common style/layout fields. Concrete elements
     * override this when they need additional controls.
     *
     * @param MoodleQuickForm $mform the edit_form instance.
     */
    public function build_form(MoodleQuickForm $mform): void {
        element_helper::render_common_form_elements($mform, $this->showposxy);
    }
}
