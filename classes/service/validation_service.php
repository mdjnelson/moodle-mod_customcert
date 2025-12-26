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
 * Service for validating element configurations.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\element;
use mod_customcert\element_helper;

/**
 * Service for validating element configurations.
 */
class validation_service {
    /**
     * Validate the configuration for an element.
     *
     * @param element $element
     * @param array $data
     * @return array
     */
    public function validate(element $element, array $data): array {
        $errors = [];

        // Validate standard fields if present.
        if (array_key_exists('colour', $data)) {
            $errors += element_helper::validate_form_element_colour($data);
        }
        // Validate position only if enabled and both controls are present in submission.
        if (
            get_config('customcert', 'showposxy') &&
            array_key_exists('posx', $data) &&
            array_key_exists('posy', $data)
        ) {
            $errors += element_helper::validate_form_element_position($data);
        }
        if (array_key_exists('width', $data)) {
            // Allow zero as valid when explicitly provided by elements like Border.
            $errors += element_helper::validate_form_element_width($data, true);
        }

        return $errors;
    }
}
