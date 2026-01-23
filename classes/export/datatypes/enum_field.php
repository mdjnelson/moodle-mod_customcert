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

namespace mod_customcert\export\datatypes;

/**
 * Represents a field with a fixed set of valid options (select).
 *
 * This class validates that input values match a predefined set of allowed options,
 * and structures the data for import and export operations in subplugin configurations.
 *
 * @package    mod_customcert
 * @copyright  2026, onCampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enum_field implements i_field {
    /**
     * @var array List of allowed option values for the field.
     */
    private array $options;

    /** @var bool Use first value from list as default */
    private bool $firstasdefault;

    /**
     * Constructor.
     *
     * @param array $options Array of valid options
     * @param mixed $firstasdefault True if the first field should be used as default, else uses null
     */
    public function __construct(array $options, $firstasdefault = true) {
        $this->options = $options;
        $this->firstasdefault = $firstasdefault;
    }

    /**
     * Validates and imports an input value from the given data array.
     *
     * Ensures the input value is one of the allowed options. Throws an exception
     * if the value is not valid.
     *
     * @param array $data Associative array containing a 'value' key.
     * @return mixed The validated input value.
     * @throws format_exception If the input is not a valid option.
     */
    public function import(array $data) {
        $option = $data['value'];

        if (!in_array($option, $this->options)) {
            throw new format_exception("$option is not an valid option");
        }

        return $option;
    }

    /**
     * Exports the internal value to a structured array format.
     *
     * @param mixed $value The internal field value.
     * @return array Exported array structure containing the value.
     */
    public function export($value): array {
        return [
            'value' => $value,
        ];
    }

    /**
     * Fallback value for select.
     *
     * @return mixed Returns the first element as fallback
     */
    public function get_fallback() {
        if ($this->firstasdefault) {
            return reset($this->options);
        }

        return null;
    }
}
