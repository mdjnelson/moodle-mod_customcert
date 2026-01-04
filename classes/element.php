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
use mod_customcert\event\element_created;
use mod_customcert\event\element_deleted;
use mod_customcert\event\element_updated;
use mod_customcert\service\element_renderer;
use mod_customcert\service\persistence_helper;
use MoodleQuickForm;
use pdf;
use restore_customcert_activity_task;
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
abstract class element {
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
     * Returns the data.
     *
     * @return mixed
     */
    public function get_data(): mixed {
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
        $raw = $this->get_data();
        if (is_string($raw)) {
            if (json_validate($raw)) {
                $decoded = json_decode($raw, true);
                return is_array($decoded) ? $decoded : [];
            }
        }
        return [];
    }

    /**
     * Return a best-effort scalar value for legacy/simple elements.
     *
     * For historical elements that stored a plain scalar in 'data', this returns the raw string.
     * For JSON payloads containing {"value": <scalar>}, this returns that scalar cast to string.
     * For structured payloads without a single 'value', returns null.
     *
     * @return string|null The scalar value or null if not applicable.
     */
    public function get_value(): ?string {
        $raw = $this->get_data();
        if (is_string($raw)) {
            if (json_validate($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && array_key_exists('value', $decoded)) {
                    return is_scalar($decoded['value']) ? (string)$decoded['value'] : null;
                }
                return null;
            }
            // Historical scalar storage.
            return $raw;
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
    public function get_alignment(): string {
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
     * Define the configuration fields for this element.
     *
     * @return array
     */
    public function get_form_fields(): array {
        return [];
    }

    /**
     * This function renders the form elements when adding a customcert element.
     * Can be overridden if more functionality is needed.
     *
     * @param MoodleQuickForm $mform the edit_form instance.
     * @deprecated since Moodle 5.2
     */
    public function render_form_elements($mform) {
        debugging(
            'render_form_elements() is deprecated since Moodle 5.2. '
            . 'Implement mod_customcert\\element\\form_definable_interface::get_form_fields() instead.',
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
     * @param edit_element_form $mform the edit_form instance
     * @deprecated since Moodle 5.2
     */
    public function definition_after_data($mform) {
        debugging(
            'definition_after_data() is deprecated since Moodle 5.2. '
            . 'Implement mod_customcert\\element\\preparable_form_interface::prepare_form() instead.',
            DEBUG_DEVELOPER
        );
        // Loop through the properties of the element and set the values
        // of the corresponding form element, if it exists.
        $properties = [
            'name' => $this->name,
            'font' => $this->get_font(),
            'fontsize' => $this->get_fontsize(),
            'colour' => $this->get_colour(),
            'posx' => $this->posx,
            'posy' => $this->posy,
            'width' => $this->get_width(),
            'refpoint' => $this->refpoint,
            'alignment' => $this->get_alignment(),
        ];
        foreach ($properties as $property => $value) {
            if (!is_null($value) && $mform->elementExists($property)) {
                $element = $mform->getElement($property);
                $element->setValue($value);
            }
        }
    }

    /**
     * Performs validation on the element values.
     * Can be overridden if more functionality is needed.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array the validation errors
     * @deprecated since Moodle 5.2
     */
    public function validate_form_elements($data, $files) {
        debugging(
            'validate_form_elements() is deprecated since Moodle 5.2. '
            . 'Implement mod_customcert\\element\\validatable_element_interface::validate() instead.',
            DEBUG_DEVELOPER
        );
        // Array to return the errors.
        $errors = [];

        // Common validation methods.
        $errors += element_helper::validate_form_element_colour($data);
        if ($this->showposxy) {
            $errors += element_helper::validate_form_element_position($data);
        }
        $errors += element_helper::validate_form_element_width($data);

        return $errors;
    }

    /**
     * Handles saving the form elements created by this element.
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $data the form data
     * @return int|bool true if updated was a success, id of the new element otherwise.
     * @deprecated since Moodle 5.2
     */
    public function save_form_elements($data) {
        debugging(
            'save_form_elements() is deprecated since Moodle 5.2. '
            . 'Implement mod_customcert\\element\\persistable_element_interface::normalise_data() and '
            . 'use element_repository for persistence.',
            DEBUG_DEVELOPER
        );
        global $DB;

        // Get the data from the form.
        $element = new stdClass();
        $element->name = $data->name;
        // Persist element data as JSON using a single helper policy.
        $element->data = persistence_helper::to_json_data($this, $data);
        // Visual attributes are stored within JSON 'data', not as separate columns.
        if ($this->showposxy) {
            $element->posx = $data->posx ?? null;
            $element->posy = $data->posy ?? null;
        }
        // Merge width into JSON data rather than a (now removed) DB column.
        if (isset($data->width) && $data->width !== '') {
            $current = $element->data;
            $merged = null;
            if ($current === null || $current === '') {
                $merged = json_encode(['width' => (int)$data->width]);
            } else {
                $decoded = json_decode($current, true);
                if (is_array($decoded)) {
                    $decoded['width'] = (int)$data->width;
                    $merged = json_encode($decoded);
                } else if (ctype_digit(trim((string)$current))) {
                    // Historical case: scalar width stored as string in data.
                    $merged = json_encode(['width' => (int)$data->width]);
                } else {
                    // Non-JSON text – start a JSON envelope with width only.
                    $merged = json_encode(['width' => (int)$data->width]);
                }
            }
            $element->data = $merged;
        }
        $element->refpoint = $data->refpoint ?? null;
        $element->alignment = $data->alignment ?? self::ALIGN_LEFT;
        $element->timemodified = time();

        // Check if we are updating, or inserting a new element.
        if (!empty($this->id)) { // Must be updating a record in the database.
            $element->id = $this->id;
            $return = $DB->update_record('customcert_elements', $element);

            element_updated::create_from_element($this)->trigger();

            return $return;
        } else { // Must be adding a new one.
            $element->element = $data->element;
            $element->pageid = $data->pageid;
            $element->sequence = element_helper::get_element_sequence($element->pageid);
            $element->timecreated = time();
            $element->id = $DB->insert_record('customcert_elements', $element, true);
            $this->id = $element->id;

            element_created::create_from_element($this)->trigger();

            return $element->id;
        }
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $data the form data
     * @return string the unique data to save
     * @deprecated since Moodle 5.2
     */
    public function save_unique_data($data) {
        debugging(
            'save_unique_data() is deprecated since Moodle 5.2. '
            . 'Implement mod_customcert\\element\\persistable_element_interface::normalise_data() instead.',
            DEBUG_DEVELOPER
        );
        return '';
    }

    /**
     * This handles copying data from another element of the same type.
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $data the form data
     * @return bool returns true if the data was copied successfully, false otherwise
     */
    public function copy_element($data) {
        return true;
    }

    /**
     * This defines if an element plugin can be added to a certificate.
     * Can be overridden if an element plugin wants to take over the control.
     *
     * @return bool returns true if the element can be added, false otherwise
     */
    public static function can_add() {
        return true;
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * Must be overridden.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param stdClass $user the user we are rendering this for
     * @param element_renderer|null $renderer the renderer service
     */
    abstract public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void;

    /**
     * Render the element in html.
     *
     * Must be overridden.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @param element_renderer|null $renderer the renderer service
     * @return string the html
     */
    abstract public function render_html(?element_renderer $renderer = null): string;

    /**
     * Handles deleting any data this element may have introduced.
     * Can be overridden if more functionality is needed.
     *
     * @return bool success return true if deletion success, false otherwise
     */
    public function delete() {
        global $DB;

        $return = $DB->delete_records('customcert_elements', ['id' => $this->id]);

        element_deleted::create_from_element($this)->trigger();

        return $return;
    }

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * For example, the function may save data that is related to another course module, this
     * data will need to be updated if we are restoring the course as the course module id will
     * be different in the new course.
     *
     * @param restore_customcert_activity_task $restore
     * @deprecated since Moodle 5.2 — implement
     *   mod_customcert\element\restorable_element_interface::after_restore_from_backup() instead.
     */
    public function after_restore($restore) {
        debugging(
            'after_restore() is deprecated since Moodle 5.2. Implement ' .
            'mod_customcert\element\restorable_element_interface::after_restore_from_backup() instead.',
            DEBUG_DEVELOPER
        );
    }

    /**
     * Set edit form instance for the custom cert element.
     *
     * @param edit_element_form $editelementform
     */
    public function set_edit_element_form(edit_element_form $editelementform) {
        $this->editelementform = $editelementform;
    }

    /**
     * Get edit form instance for the custom cert element.
     *
     * @return edit_element_form
     */
    public function get_edit_element_form() {
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
}
