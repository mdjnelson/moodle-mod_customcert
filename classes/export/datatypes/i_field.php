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
 * Defines the interface for all exportable field types in custom certificates.
 *
 * Implementing classes must provide import and export logic to handle
 * structured data transformations between external formats and internal values.
 *
 * @package    mod_customcert
 * @copyright  2026, onCampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface i_field {
    /**
     * Validates and converts input data to an internal value.
     *
     * @param array $data Associative array representing field data.
     * @return mixed The processed value ready for internal use.
     * @throws format_exception If the input format is incorrect or not supported.
     * @throws format_error If a fatal formatting issue occurs.
     */
    public function import(array $data);

    /**
     * Converts an internal value to a structured export format.
     *
     * @param mixed $value The internal value to be exported.
     * @return array Associative array representing the exported structure.
     */
    public function export($value): array;
}
