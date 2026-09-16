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
 * Shared constants for the custom certificate rearranger.
 *
 * @module     mod_customcert/constants
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Number of CSS pixels in one millimetre (matches legacy YUI rearranger). */
export const PIXELSINMM = 3.779527559055;

/** Reference point: top-left of the element. */
export const REF_POINT_TOPLEFT = 0;

/** Reference point: top-center of the element. */
export const REF_POINT_TOPCENTER = 1;

/** Reference point: top-right of the element. */
export const REF_POINT_TOPRIGHT = 2;

/** Text alignment left (TCPDF 'L'). */
export const ALIGN_LEFT = 'L';

/** Text alignment center (TCPDF 'C'). */
export const ALIGN_CENTER = 'C';

/** Text alignment right (TCPDF 'R'). */
export const ALIGN_RIGHT = 'R';

/** Keyboard nudge step in millimetres. */
export const KEYBOARD_NUDGE_MM = 1;

/** Keyboard nudge step when Shift is held, in millimetres. */
export const KEYBOARD_NUDGE_MM_LARGE = 5;
