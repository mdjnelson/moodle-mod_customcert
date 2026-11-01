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
 * Convert form data to JSON object payloads for customcert_elements.data.
 * Invariant: the result is always a JSON object (including {}), never a JSON array, scalar or null.
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
        // Unwrap the adapter: an adapted element may itself implement persistable_element_interface.
        $target = ($element instanceof legacy_element_adapter) ? $element->get_inner() : $element;
        if ($target instanceof persistable_element_interface) {
            $normalised = $target->normalise_data($formdata);
            return self::to_object_json($normalised);
        }

        // Only call save_unique_data() when the concrete class overrides it, not when it is merely
        // inherited from the base class.
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

        return json_encode(new stdClass());
    }

    /**
     * Combine a legacy element's save_unique_data() result with the common visual form
     * fields (width, font, fontsize, colour), mirroring the pre-5.x
     * element::save_form_elements() semantics where these fields were persisted by the
     * base class separately from the plugin-specific scalar value.
     *
     * When a visual field is absent from the submitted data (in practice only in synthetic/test
     * scenarios), the previously stored value is preserved instead of dropped.
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
        // Keep previously stored wrapper metadata (e.g. height, alphachannel) so a legacy save does
        // not discard it. Only trusted when the raw data is a generic migration wrapper: other
        // third-party JSON is never merged in, as that could resurrect stale plugin state.
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
            // The plugin returned structured data. Use it as-is, without merging stored wrapper
            // metadata, so stale plugin-specific keys cannot resurrect.
            $payload = $legacystructured;
        } else {
            // Scalar value: keep existing wrapper metadata (height/alphachannel) and replace 'value'.
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
            // Decode to objects so '{}' can be told apart from '[]'; both are [] when decoded as arrays.
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
        if (is_array($value) && !array_is_list($value)) {
            return json_encode($value);
        }

        // Pass a JSON string through only if it is an object. Decoding to objects keeps '{}' distinct from '[]'.
        if (is_string($value) && $value !== '' && json_validate($value)) {
            $decoded = json_decode($value, false);
            if ($decoded instanceof \stdClass) {
                return $value;
            }
        }

        if ($value === null) {
            return json_encode(new stdClass());
        }

        return json_encode(['value' => $value]);
    }
}
