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
     * JSON encoding flags used consistently across this class.
     *
     * - JSON_THROW_ON_ERROR to avoid silently returning false
     * - JSON_INVALID_UTF8_SUBSTITUTE to handle legacy bytes
     * - JSON_UNESCAPED_UNICODE for readability
     */
    private const int ENCODE_FLAGS =
        JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;

    /**
     * Determine if a raw JSON string represents a JSON object (as opposed to a list/array or scalar).
     *
     * Uses the first non-whitespace character for accurate classification, ensuring that
     * empty objects "{}" (which decode to [] in PHP) are still treated as JSON objects.
     *
     * @param string $raw Raw JSON string to check.
     * @return bool True if the string represents a JSON object, false otherwise.
     */
    private static function is_json_object_string(string $raw): bool {
        $raw = ltrim($raw);
        // Strip UTF-8 BOM if present before checking the first character.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        return $raw !== '' && $raw[0] === '{';
    }

    /**
     * Strip an optional UTF-8 BOM after trimming leading whitespace.
     *
     * @param string $raw
     * @return string Cleaned string without BOM at the start.
     */
    private static function strip_utf8_bom(string $raw): string {
        $raw = ltrim($raw);
        // Use preg to safely drop a leading BOM if present.
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        return is_string($clean) ? $clean : $raw;
    }

    /**
     * Encode payload to JSON using safe flags and controlled fallback.
     *
     * Flags:
     * - JSON_THROW_ON_ERROR to avoid silently returning false
     * - JSON_INVALID_UTF8_SUBSTITUTE to handle legacy bytes
     * - JSON_UNESCAPED_UNICODE for readability
     *
     * If encoding still fails (very rare), we fall back to a minimal payload
     * using the original raw string when provided.
     *
     * @param array $payload The associative array to encode.
     * @param string|null $fallbackraw The original raw string to use on failure.
     * @return string JSON-encoded string.
     */
    private static function encode_payload(array $payload, ?string $fallbackraw): string {
        try {
            return json_encode($payload, self::ENCODE_FLAGS);
        } catch (\JsonException $e) {
            // Best-effort fallback: prefer preserving original raw string to avoid data loss.
            $fallback = $fallbackraw !== null ? ['value' => $fallbackraw] : $payload;
            try {
                return json_encode($fallback, self::ENCODE_FLAGS);
            } catch (\JsonException $e2) {
                // Last resort: minimal safe JSON to satisfy strict return type contracts.
                return '{"value":""}';
            }
        }
    }
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
     * Migrate a legacy border element row.
     *
     * Legacy border elements stored their line thickness as a plain integer scalar in the
     * 'data' column (e.g. "3") and may never have had the 'width' column populated. This
     * method promotes such a scalar to the 'width' key in the JSON envelope so that
     * element::get_width() returns the correct value after upgrade.
     *
     * @param string|null $rawdata Original data field value.
     * @param int|null $width Value of the old 'width' column (may be null).
     * @param string|null $font
     * @param int|null $fontsize
     * @param string|null $colour
     * @return string|null Migrated JSON string.
     */
    public static function migrate_border_row(
        ?string $rawdata,
        ?int $width,
        ?string $font,
        ?int $fontsize,
        ?string $colour
    ): ?string {
        // If the width column was never set but data holds a plain integer scalar,
        // that scalar IS the border line thickness — promote it to the width argument
        // and clear rawdata so the scalar is not also stored under the 'value' key.
        if ($rawdata !== null) {
            $rawdata = trim($rawdata);
        }
        if ($width === null && $rawdata !== null && $rawdata !== '') {
            $decoded = json_decode($rawdata, true);
            if (is_int($decoded) || (is_string($decoded) && ctype_digit($decoded))) {
                $width = (int)$decoded;
                $rawdata = null;
            }
        }
        return self::migrate_row($rawdata, $width, $font, $fontsize, $colour);
    }

    /**
     * Migrate a single row's data payload to the consolidated JSON format.
     *
     * Rules:
     * - If no visuals are provided, normalise any non-object JSON (scalars/JSON lists) and non-JSON to {"value": ...}.
     *   - NULL/empty stays as-is.
     *   - Existing JSON object payloads stay unchanged (only associative objects, not JSON lists).
     *   - Object vs list classification is based on the first non-whitespace character of the raw string:
     *     '{' => object, '[' => list.
     *   - JSON scalars, JSON lists, and JSON null are stored as native decoded types in the "value" field.
     * - If any visual is provided, always return JSON:
     *   - NULL/empty -> JSON with provided visuals.
     *   - Existing JSON object -> merge/overwrite provided visuals.
     *   - JSON scalar/list -> {"value": <decoded native>, ...visuals...}.
     *   - Invalid JSON string -> {"value": "...original string...", ...visuals...}.
     *
     * @param string|null $rawdata Original data field as stored (may be NULL, '', scalar, or JSON string).
     * @param int|null $width
     * @param string|null $font
     * @param int|null $fontsize
     * @param string|null $colour
     * @return string|null New JSON string (or original when unchanged); may be NULL when original was NULL and no visuals provided.
     */
    public static function migrate_row(?string $rawdata, ?int $width, ?string $font, ?int $fontsize, ?string $colour): ?string {
        if ($rawdata !== null) {
            $rawdata = trim($rawdata);
        }
        $novisuals = ($width === null && $font === null && $fontsize === null && $colour === null);

        // Nothing to migrate (no visuals): normalise scalars/JSON non-objects to JSON for consistency.
        if ($novisuals) {
            if ($rawdata === null || $rawdata === '') {
                return $rawdata; // Keep null/empty as-is.
            }
            // Decode once and treat ONLY JSON objects as existing JSON payloads.
            $clean = self::strip_utf8_bom($rawdata);
            $decoded = json_decode($clean, true);
            $jsonok = (json_last_error() === JSON_ERROR_NONE);
            if ($jsonok && self::is_json_object_string($clean)) {
                return $rawdata; // Already JSON object – leave unchanged (even for {}).
            }
            // For valid JSON scalars, lists, and null, keep native decoded value; else preserve original string.
            if ($jsonok) {
                $value = ($decoded === null || is_scalar($decoded) || (is_array($decoded) && array_is_list($decoded)))
                    ? $decoded
                    : $rawdata;
                return self::encode_payload(['value' => $value], $rawdata);
            }
            return self::encode_payload(['value' => (string)$rawdata], $rawdata);
        }

        // Visuals provided – always output JSON.
        if ($rawdata === null || $rawdata === '') {
            $payload = [];
            $payload = self::merge_visuals($payload, $width, $font, $fontsize, $colour);
            return !empty($payload) ? self::encode_payload($payload, $rawdata) : $rawdata;
        }

        // Decode once and decide handling: only JSON objects are treated as JSON payloads.
        $clean = self::strip_utf8_bom($rawdata);
        $decoded = json_decode($clean, true);
        $jsonok = (json_last_error() === JSON_ERROR_NONE);
        if ($jsonok && self::is_json_object_string($clean)) {
            $payload = is_array($decoded) ? $decoded : [];
            $payload = self::merge_visuals($payload, $width, $font, $fontsize, $colour);
            return self::encode_payload($payload, $rawdata);
        }

        // For JSON scalars, JSON lists, or JSON null build a payload with correctly typed value.
        if ($jsonok && ($decoded === null || is_scalar($decoded) || (is_array($decoded) && array_is_list($decoded)))) {
            $payload = ['value' => $decoded];
        } else {
            // Preserve the original string (not decoded) to avoid numeric coercion and keep leading zeros, etc.
            $payload = ['value' => (string)$rawdata];
        }
        $payload = self::merge_visuals($payload, $width, $font, $fontsize, $colour);
        return self::encode_payload($payload, $rawdata);
    }
}
