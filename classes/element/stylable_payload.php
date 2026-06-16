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
 * Helper for normalising the standard style payload fields shared by text-like elements.
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
 * Call {@see stylable_payload::from_form()} inside `normalise_data()` and merge the
 * result with any element-specific fields to avoid duplicating this logic.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stylable_payload {
    /**
     * Extract and normalise the four standard style fields from form submission data.
     *
     * Returns an array with exactly the keys `font`, `fontsize`, `colour`, and `width`,
     * cast to their canonical types. Merge this with element-specific fields inside
     * `normalise_data()`:
     *
     * ```php
     * public function normalise_data(stdClass $formdata): array {
     *     return array_merge(
     *         ['myfield' => (string)($formdata->myfield ?? '')],
     *         stylable_payload::from_form($formdata),
     *     );
     * }
     * ```
     *
     * @param stdClass $formdata Raw form submission data.
     * @return array{font:string,fontsize:int,colour:string,width:int}
     */
    public static function from_form(stdClass $formdata): array {
        return [
            'font' => (string)($formdata->font ?? ''),
            'fontsize' => (int)($formdata->fontsize ?? 0),
            'colour' => (string)($formdata->colour ?? ''),
            'width' => (int)($formdata->width ?? 0),
        ];
    }
}
