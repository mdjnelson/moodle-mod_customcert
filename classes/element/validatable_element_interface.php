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
 * Contract for elements that provide custom validation.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

/**
 * Elements can implement this to contribute element-specific validation rules.
 * The central validation_service will detect this interface and invoke it.
 */
interface validatable_element_interface {
    /**
     * Validate submitted form data for this element.
     *
     * Return an associative array of errors in the MoodleForms format:
     *  - key: the form field name (e.g., 'width') or a special key (e.g., '_elementvalidation')
     *  - value: the error message string
     *
     * @param array $data Submitted data (typically $mform->get_data() cast to array).
     * @return array<string,string> List of field errors.
     */
    public function validate(array $data): array;
}
