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
 * Contract for elements that provide typed normalisation of form data.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

use stdClass;

/**
 * Elements implementing this interface provide a typed normalisation of form data.
 * The form_service will JSON-encode the returned array for storage in `data`.
 */
interface persistable_element_interface {
    /**
     * Normalise raw form submission into a serialisable array.
     *
     * @param stdClass $formdata Raw form data object (stdClass)
     * @return array Normalised associative array ready for json_encode
     */
    public function normalise_data(stdClass $formdata): array;
}
