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
 * Optional contract for elements that expose the raw persistence representation.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

/**
 * Optional storage contract for elements whose get_data() exposes a derived view.
 * Kept separate from element_interface so that third-party plugins implementing that stable
 * contract directly are not broken by a new required method. The persistence layer falls back
 * to get_data() for elements that do not implement this interface.
 */
interface raw_data_element_interface {
    /**
     * Return the exact value to store in customcert_elements.data.
     * For persistence only; rendering and forms should use get_data().
     *
     * @return mixed
     */
    public function get_raw_data(): mixed;
}
