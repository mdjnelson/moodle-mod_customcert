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

use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\raw_data_element_interface;
use mod_customcert\local\upgrade\row_migrator;
use ReflectionMethod;
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
     * - Legacy elements: use save_unique_data() (only when overridden) and enforce object JSON.
     * - Fallback: empty JSON object {}.
     *
     * @param object $element Element instance (persistable or legacy)
     * @param stdClass $formdata Raw form data
     * @return string JSON object string suitable for DB storage
     */
    public static function to_json_data(object $element, stdClass $formdata): string {
        // Persistable path.
        if ($element instanceof persistable_element_interface) {
            $normalised = $element->normalise_data($formdata);
            return self::to_object_json($normalised);
        }

        // Legacy path: only invoke save_unique_data() when the concrete class actually overrides it,
        // not when it is merely inherited from the mod_customcert\element base class.
        // Unwrap the adapter so we inspect the inner legacy element's declaring class.
        $target = ($element instanceof legacy_element_adapter) ? $element->get_inner() : $element;
        if (
            method_exists($target, 'save_unique_data') &&
            (new ReflectionMethod($target, 'save_unique_data'))->getDeclaringClass()->getName() !== \mod_customcert\element::class
        ) {
            debugging(
                'save_unique_data() is deprecated since Moodle 5.2. Implement ' .
                'mod_customcert\element\persistable_element_interface::normalise_data() instead.',
                DEBUG_DEVELOPER
            );
            $legacy = $element->save_unique_data($formdata);
            return self::to_object_json(self::merge_legacy_visuals($legacy, $element, $formdata));
        }

        // Absolute fallback: empty object.
        return json_encode(new stdClass());
    }

    /**
     * Combine a legacy element's save_unique_data() result with the common visual form
     * fields (width, font, fontsize, colour), mirroring the pre-5.x
     * element::save_form_elements() semantics where these fields were persisted by the
     * base class separately from the plugin-specific scalar value.
     *
     * When a visual field is absent from the submitted form data (which, in practice,
     * only happens in synthetic/test scenarios since the real legacy edit form always
     * renders these fields with defaults), the previous value already stored in the
     * element's raw data is preserved instead of being dropped.
     *
     * @param mixed $legacy Result of save_unique_data(): typically a scalar, but may
     *                       already be an associative array for plugins that return
     *                       structured data.
     * @param object $element Element instance being persisted.
     * @param stdClass $formdata Submitted form data.
     * @return mixed The value to pass to to_object_json(): an associative array when
     *               any visual field applies, otherwise the original $legacy value.
     */
    private static function merge_legacy_visuals(mixed $legacy, object $element, stdClass $formdata): mixed {
        // Look up any previously stored recognised compatibility-wrapper metadata (e.g.
        // height, alphachannel) so it is not silently discarded on the next legacy save.
        // Only trusted when the existing raw data is itself a recognised generic
        // migration wrapper — arbitrary/structured third-party JSON is never blindly
        // merged in, since that could resurrect stale plugin-specific state.
        $existing = [];
        if ($element instanceof raw_data_element_interface) {
            $raw = $element->get_raw_data();
            if (
                is_string($raw) && $raw !== '' && json_validate($raw) &&
                \mod_customcert\element::is_generic_migration_wrapper($raw)
            ) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !array_is_list($decoded)) {
                    $existing = $decoded;
                }
            }
        }

        $legacystructured = self::decode_structured_legacy_value($legacy);
        if ($legacystructured !== null) {
            // The plugin itself returns structured data (either a PHP associative array,
            // or, per the historical string-returning save_unique_data() API, a JSON
            // object string). Use it as-is without merging in previously stored
            // compatibility-wrapper metadata, so stale plugin-specific keys cannot
            // resurrect.
            $payload = $legacystructured;
        } else {
            // Scalar historical value: start from the existing recognised wrapper metadata
            // (if any) so keys such as height/alphachannel survive, then replace the
            // plugin-specific scalar with the latest save_unique_data() result.
            $payload = $existing;
            $payload['value'] = $legacy;
        }

        $width = isset($formdata->width) ? (int) $formdata->width : (isset($existing['width']) ? (int) $existing['width'] : null);
        $font = $formdata->font ?? ($existing['font'] ?? null);
        $fontsize = isset($formdata->fontsize)
            ? (int) $formdata->fontsize
            : (isset($existing['fontsize']) ? (int) $existing['fontsize'] : null);
        $colour = $formdata->colour ?? ($existing['colour'] ?? null);

        $payload = row_migrator::merge_visuals(
            $payload,
            $width,
            $font !== null ? (string) $font : null,
            $fontsize,
            $colour !== null ? (string) $colour : null
        );

        return $payload;
    }

    /**
     * Determine whether a legacy save_unique_data() result represents plugin-owned
     * structured data, as opposed to a historical scalar compatibility value.
     *
     * Historically, save_unique_data() could return either a plain scalar (e.g. a
     * string, teacher id, etc.) or a JSON object string for elements that persisted
     * multiple fields (e.g. the bundled date/daterange/expiry/grade/image/qrcode/
     * userpicture/digitalsignature elements on MOODLE_404_STABLE). Both a PHP
     * associative array and a JSON object string are treated as structured data;
     * plain scalars, JSON scalar strings, JSON lists, booleans and null are not.
     *
     * @param mixed $legacy Result of save_unique_data().
     * @return array|null The decoded associative array when $legacy is structured, or
     *                     null when it should be treated as a scalar compatibility value.
     */
    private static function decode_structured_legacy_value(mixed $legacy): ?array {
        if (is_array($legacy) && !array_is_list($legacy)) {
            return $legacy;
        }

        if (is_string($legacy) && $legacy !== '' && json_validate($legacy)) {
            // Use the non-associative decode first so an empty JSON object ('{}') can be
            // distinguished from an empty JSON list ('[]'); both decode to [] otherwise.
            $decoded = json_decode($legacy, false);
            if ($decoded instanceof stdClass) {
                return (array) $decoded;
            }
        }

        return null;
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
