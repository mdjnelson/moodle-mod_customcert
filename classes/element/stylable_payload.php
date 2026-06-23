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

namespace mod_customcert\element;

use stdClass;

/**
 * Immutable value object for the standard visual style fields shared by text-like elements.
 *
 * Text-like elements (text, code, date, grade, studentname, teachername, coursename,
 * categoryname, userfield, coursefield, gradeitemname, expiry, etc.) all store the same
 * four visual fields in their JSON payload:
 *
 *   font     - font family name (string)
 *   fontsize - point size (int)
 *   colour   - hex colour string (string)
 *   width    - text-box width in mm (int)
 *
 * Use {@see stylable_payload::from_form()} inside `normalise_data()` and compose it
 * into a typed payload class, or call `->to_array()` to merge with element-specific
 * fields for direct array return.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stylable_payload {
    /**
     * Construct a stylable_payload with the given style field values.
     *
     * @param string $font Font family name.
     * @param int $fontsize Font size in points.
     * @param string $colour Hex colour string (without leading #).
     * @param int $width Text-box width in mm.
     */
    public function __construct(
        /** @var string Font family name. */
        public readonly string $font,
        /** @var int Font size in points. */
        public readonly int $fontsize,
        /** @var string Hex colour string (without leading #). */
        public readonly string $colour,
        /** @var int Text-box width in mm. */
        public readonly int $width,
    ) {
    }

    /**
     * Construct a stylable_payload from form submission data.
     *
     * @param stdClass $formdata Raw form submission data.
     * @return static
     */
    public static function from_form(stdClass $formdata): static {
        return new static(
            font: (string)($formdata->font ?? ''),
            fontsize: (int)($formdata->fontsize ?? 0),
            colour: (string)($formdata->colour ?? ''),
            width: (int)($formdata->width ?? 0),
        );
    }

    /**
     * Construct a stylable_payload from a decoded data array.
     *
     * Missing keys receive safe defaults; values are cast to their canonical types.
     *
     * @param array $data Associative array, typically from json_decode($raw, true).
     * @return static
     */
    public static function from_array(array $data): static {
        return new static(
            font: (string)($data['font'] ?? ''),
            fontsize: (int)($data['fontsize'] ?? 0),
            colour: (string)($data['colour'] ?? ''),
            width: (int)($data['width'] ?? 0),
        );
    }

    /**
     * Serialize the style fields to an associative array suitable for json_encode().
     *
     * @return array{font:string,fontsize:int,colour:string,width:int}
     */
    public function to_array(): array {
        return [
            'font' => $this->font,
            'fontsize' => $this->fontsize,
            'colour' => $this->colour,
            'width' => $this->width,
        ];
    }
}
