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
 * Contract for elements that expose a raw, untouched persistence representation of their data.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

/**
 * Optional interface exposing an element's untouched persistence data, as opposed to the legacy
 * compatibility view returned by get_data().
 *
 * Not part of the minimal element_interface contract. It only matters for elements whose
 * get_data() may unwrap a generic migration wrapper that must never be written back to the database.
 */
interface raw_data_element_interface {
    /**
     * Returns the raw, untouched persistence representation of the element data.
     *
     * Unlike get_data(), this never applies a legacy compatibility transformation.
     * For the persistence layer only; rendering and form code must use get_data().
     *
     * @return mixed
     */
    public function get_raw_data(): mixed;
}
