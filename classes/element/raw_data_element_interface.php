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
 * Optional contract for elements that expose the raw, untouched persistence representation.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

/**
 * Interface raw_data_element_interface
 *
 * This is deliberately kept separate from {@see element_interface} so that third-party
 * plugins implementing element_interface directly are not broken by a new required method:
 * element_interface is a stable, minimal contract that any third-party element must be
 * able to satisfy without modification.
 *
 * Elements implementing this optional interface allow the persistence layer (e.g.
 * element_repository) to store the untouched persistence representation of the element
 * data, instead of falling back to get_data(), which may apply legacy compatibility
 * unwrapping and lose migrated fields.
 */
interface raw_data_element_interface {
    /**
     * Returns the raw, untouched persistence representation of the element data.
     *
     * Unlike {@see element_interface::get_data()}, this must never apply legacy
     * compatibility unwrapping. It always returns exactly what is (or will be) stored
     * in the `customcert_elements.data` database column, so that the persistence layer
     * (e.g. element_repository) never loses migrated fields that a legacy scalar
     * compatibility view would otherwise hide.
     *
     * This method is intended for use by the persistence/storage boundary (e.g.
     * element_repository) only, and must not be used as a substitute for get_data() by
     * element rendering or form-handling code.
     *
     * @return mixed
     */
    public function get_raw_data(): mixed;
}
