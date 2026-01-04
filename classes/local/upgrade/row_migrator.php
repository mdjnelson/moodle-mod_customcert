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

declare(strict_types=1);

namespace mod_customcert\local\upgrade;

/**
 * Pure helpers for migrating element row data to the new JSON format.
 *
 * Stateless, with no DB dependencies, so it can be used from upgrade/restore
 * code and directly from PHPUnit tests without touching the DB.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <@mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class row_migrator {
    /**
     * Merge visual attributes into the provided payload array.
     *
     * @param array $payload Existing associative array payload to augment.
     * @param int|null $width Width value (0 is meaningful), or null to skip.
     * @param string|null $font Font name, or null to skip.
     * @param int|null $fontsize Font size, or null to skip.
     * @param string|null $colour Colour string, or null to skip.
     * @return array Augmented payload.
     */
    public static function merge_visuals(array $payload, ?int $width, ?string $font, ?int $fontsize, ?string $colour): array {
        if ($width !== null) {
            $payload['width'] = (int)$width;
        }
        if ($font !== null) {
            $payload['font'] = (string)$font;
        }
        if ($fontsize !== null) {
            $payload['fontsize'] = (int)$fontsize;
        }
        if ($colour !== null) {
            $payload['colour'] = (string)$colour;
        }
        return $payload;
    }

    /**
     * Migrate a single row's data payload to the consolidated JSON format.
     *
     * Rules:
     * - If no visuals are provided, normalise non-JSON scalars to JSON {"value": ...}.
     *   - NULL/empty stays as-is.
     *   - Existing JSON stays unchanged.
     * - If any visual is provided, always return JSON:
     *   - NULL/empty -> JSON with provided visuals.
     *   - Scalar int string -> {"value": <int>, ...visuals...}.
     *   - Existing JSON -> merge/overwrite provided visuals.
     *   - Non-JSON string -> {"value": "...", ...visuals...}.
     *
     * @param string|null $rawdata Original data field as stored (may be NULL, '', scalar, or JSON string).
     * @param int|null $width
     * @param string|null $font
     * @param int|null $fontsize
     * @param string|null $colour
     * @return string|null New JSON string (or original when unchanged); may be NULL when original was NULL and no visuals provided.
     */
    public static function migrate_row(?string $rawdata, ?int $width, ?string $font, ?int $fontsize, ?string $colour): ?string {
        $novisuals = ($width === null && $font === null && $fontsize === null && $colour === null);

        // Nothing to migrate (no visuals): normalise scalars to JSON for consistency.
        if ($novisuals) {
            if ($rawdata === null || $rawdata === '') {
                return $rawdata; // Keep null/empty as-is.
            }
            // Preserve string identity to avoid stripping leading zeros.
            $decoded = json_decode($rawdata, true);
            if (is_array($decoded)) {
                return $rawdata; // Already JSON – leave unchanged.
            }
            return json_encode(['value' => (string)$rawdata]);
        }

        // Visuals provided – always output JSON.
        if ($rawdata === null || $rawdata === '') {
            $payload = [];
            $payload = self::merge_visuals($payload, $width, $font, $fontsize, $colour);
            return !empty($payload) ? json_encode($payload) : $rawdata;
        }

        // Preserve string identity for numeric-looking strings.
        if (is_string($rawdata) && json_decode($rawdata, true) === null) {
            $payload = ['value' => (string)$rawdata];
            $payload = self::merge_visuals($payload, $width, $font, $fontsize, $colour);
            return json_encode($payload);
        }

        $decoded = json_decode($rawdata, true);
        if (is_array($decoded)) {
            $decoded = self::merge_visuals($decoded, $width, $font, $fontsize, $colour);
            return json_encode($decoded);
        }

        $payload = ['value' => (string)$rawdata];
        $payload = self::merge_visuals($payload, $width, $font, $fontsize, $colour);
        return json_encode($payload);
    }
}
