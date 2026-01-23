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
 * Handles floating-point number validation and export for custom certificate fields.
 *
 * This field ensures that numerical values fall within optional minimum and maximum
 * boundaries, and structures them for import/export operations.
 *
 * @package    mod_customcert
 * @copyright  2026, onCampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class float_field implements i_field {
    /**
     * @var float|null Minimum allowed value, or null if no lower bound.
     */
    private ?float $min;
    /**
     * @var float|null Maximum allowed value, or null if no upper bound.
     */
    private ?float $max;

    /**
     * Constructor.
     *
     * @param float|null $min Minimum allowed value, or null if no lower bound
     * @param float|null $max Maximum allowed value, or null if no upper bound
     */
    public function __construct(
        ?float $min = null,
        ?float $max = null,
    ) {
        $this->min = $min;
        $this->max = $max;
    }

    /**
     * Validates and imports a float value from structured input.
     *
     * Checks that the value falls within the defined range, if applicable.
     *
     * @param array $data Associative array with a 'value' key.
     * @return float The validated float value.
     * @throws format_exception If the value is outside the defined bounds.
     */
    public function import(array $data) {
        $value = $data['value'];

        if ($this->min != null && $value < $this->min) {
            throw new format_exception("Value should be less than $this->min");
        }

        if ($this->min != null && $value > $this->max) {
            throw new format_exception("Value should be higher than $this->max");
        }

        return $value;
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
}
