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
 * Persistence helper to produce JSON element data from form submissions.
 *
 * @package    mod_customcert
 * @copyright  Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\element\persistable_element_interface;
use stdClass;

/**
 * Helper to convert form data to the JSON payload stored in customcert_elements.data.
 */
final class persistence_helper {
    /**
     * Convert form submission to a JSON string according to element capabilities.
     *
     * - Persistable elements: use normalise_data() and json_encode arrays.
     * - Legacy elements: use save_unique_data() and ensure JSON string output.
     *
     * @param object $element Element instance (persistable or legacy)
     * @param stdClass $formdata Raw form data
     * @return string JSON string suitable for DB storage
     */
    public static function to_json_data(object $element, stdClass $formdata): string {
        // Persistable path.
        if ($element instanceof persistable_element_interface) {
            $normalised = $element->normalise_data($formdata);
            return is_array($normalised) ? json_encode($normalised) : (string)$normalised;
        }

        // Legacy path.
        if (method_exists($element, 'save_unique_data')) {
            $legacy = $element->save_unique_data($formdata);
            if (is_array($legacy)) {
                return json_encode($legacy);
            }
            if (is_string($legacy)) {
                $trim = trim($legacy);
                if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                    return $legacy;
                }
                return json_encode(['value' => $legacy]);
            }
            if ($legacy === null) {
                return json_encode(new stdClass());
            }
            return json_encode($legacy);
        }

        // Absolute fallback: empty object.
        return json_encode(new stdClass());
    }
}
