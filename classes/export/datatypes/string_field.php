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
 * Handles import and export of simple string values for certificate subplugin fields.
 *
 * This field allows optional or required string input depending on the configuration,
 * and structures the value for consistent import/export processing.
 *
 * @package    mod_customcert
 * @copyright  2026, onCampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\export\datatypes;

class string_field implements i_field {
    /**
     * @var bool Indicates whether empty strings are allowed for this field.
     */
    private bool $emptyallowed;

    /** @var string Default value */
    private string $default;

    /**
     * Constructor.
     *
     * @param bool $emptyallowed Indicates whether empty strings are allowed for this field
     * @param string $default Default value
     */
    public function __construct(bool $emptyallowed = false, string $default = 'TODO') {
        $this->emptyallowed = $emptyallowed;
        $this->default = $default;
    }

    /**
     * Imports a string value from input data, validating its presence if required.
     *
     * @param array $data Associative array with a 'value' key containing the string.
     * @return string The validated string value.
     * @throws format_exception If the value is empty and empty values are not allowed.
     */
    public function import(array $data): mixed {
        if ($data['value'] === '' && !$this->emptyallowed) {
            throw new format_exception('is empty');
        }

        return $data['value'];
    }

    /**
     * Exports the internal value to a structured array format.
     *
     * @param mixed $value The internal field value.
     * @return array Exported array structure containing the value.
     */
    public function export(mixed $value): array {
        return [
            'value' => $value,
        ];
    }

    /**
     * Get predefined default from subplugin
     *
     * @return string predefined default
     */
    public function get_fallback(): mixed {
        return $this->default;
    }
}
