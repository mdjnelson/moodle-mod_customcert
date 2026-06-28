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
 *
 * Invariant: the returned string is always a JSON object (i.e. decodes to an associative
 * array, never a list, scalar, or null). This guarantees that customcert_elements.data
 * is always a JSON object.
 */
final class persistence_helper {
    /**
     * Convert form submission to a JSON string according to element capabilities.
     *
     * - Persistable elements: use normalise_data() and enforce object JSON.
     * - Fallback: empty JSON object {}.
     *
     * @param object $element Element instance implementing persistable_element_interface
     * @param stdClass $formdata Raw form data
     * @return string JSON object string suitable for DB storage
     */
    public static function to_json_data(object $element, stdClass $formdata): string {
        if ($element instanceof persistable_element_interface) {
            $normalised = $element->normalise_data($formdata);
            return self::to_object_json($normalised);
        }
        // Absolute fallback: empty object.
        return json_encode(new stdClass());
    }

    /**
     * Coerce any value to a JSON object string.
     *
     * Rules:
     * - Associative (non-list) array → json_encode directly (already object-shaped).
     * - JSON string that decodes to an associative array → pass through as-is.
     * - Everything else (scalar, null, list array, JSON list/scalar string) → wrap as {"value": ...}.
     *
     * @param mixed $value
     * @return string JSON object string
     */
    public static function to_object_json(mixed $value): string {
        // Associative array: encode directly as a JSON object.
        if (is_array($value) && !array_is_list($value)) {
            return json_encode($value);
        }
        // JSON string: only pass through if it decodes to a JSON object (including '{}').
        // Use json_decode(..., false) so that '{}' becomes stdClass rather than [],
        // which lets us distinguish an empty object from an empty list.
        if (is_string($value) && $value !== '' && json_validate($value)) {
            $decoded = json_decode($value, false);
            if ($decoded instanceof \stdClass) {
                return $value;
            }
            // JSON list, scalar JSON, etc. — fall through to wrap.
        }
        // Null input: return empty object.
        if ($value === null) {
            return json_encode(new stdClass());
        }
        // List array, scalar (string/int/float/bool), or non-object JSON string → wrap.
        return json_encode(['value' => $value]);
    }
}
