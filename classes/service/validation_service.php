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
use mod_customcert\element\validatable_element_interface;
use Throwable;

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

        // Let elements contribute their own specific validation when they opt-in.
        if ($element instanceof validatable_element_interface) {
            try {
                $errors += (array) $element->validate($data);
            } catch (Throwable $e) {
                // Never break the form on custom element validation; attach error to a visible field.
                $errors['name'] = get_string('invaliddata', 'error');
                if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
                    debugging('Element validation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        } else {
            // Back-compat: If the element does not implement the new interface,
            // call the deprecated element::validate_form_elements().
            try {
                // The variable $files is not used by core validations; provide empty array.
                $errors += (array) $element->validate_form_elements($data, []);
            } catch (Throwable $e) {
                $errors['name'] = get_string('invaliddata', 'error');
                if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
                    debugging('Deprecated element validation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }

        return $errors;
    }
}
