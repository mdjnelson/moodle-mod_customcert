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
 * Represents a field that cannot be imported in custom certificate exports.
 *
 * This field type is used for elements that are not exportable.
 *
 * @package    mod_customcert
 * @copyright  2026, onCampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unimported_field implements i_field {
    /**
     * Throws an exception to indicate that this field is not importable.
     *
     * @param array $data Input data array (ignored).
     * @throws format_exception Always thrown to block import attempts.
     */
    public function import(array $data) {
        throw new format_exception('can not be imported');
    }

    /**
     * Returns an empty array as this field has no exportable data.
     *
     * @param mixed $value Ignored value.
     * @return array Always returns an empty array.
     */
    public function export($value): array {
        return [];
    }

    /**
     * Empty cause import not possible
     *
     * @return string Empty fallback.
     */
    public function get_fallback() {
        return "";
    }
}
