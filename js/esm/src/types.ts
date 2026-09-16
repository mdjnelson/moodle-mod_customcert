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
 * Shared TypeScript types for the custom certificate rearranger.
 *
 * @module     mod_customcert/types
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Certificate page geometry in millimetres. */
export type CertificatePage = {
    /** Page width in mm. */
    width: number;
    /** Page height in mm. */
    height: number;
    /** Left margin in mm. */
    leftmargin: number;
    /** Right margin in mm. */
    rightmargin: number;
};

/**
 * One certificate element as supplied to the React rearranger.
 *
 * Preview HTML is server-rendered by each element plugin's render_html() so the
 * frontend stays agnostic of concrete customcertelement_* types.
 */
export type CertificateElement = {
    /** Element instance id. */
    id: number;
    /** Display name used for accessibility labels. */
    name: string;
    /** X position in mm (relative to the configured refpoint). */
    posx: number;
    /** Y position in mm (relative to the top of the page). */
    posy: number;
    /** Optional max width in mm from element data. */
    width: number | null;
    /** Reference point: 0 top-left, 1 top-center, 2 top-right. */
    refpoint: number;
    /** Text alignment: L, C, or R. */
    alignment: string;
    /** Server-rendered preview HTML for the element. */
    html: string;
};

/** Props passed from rearrange.php via data-react-props. */
export type RearrangeProps = {
    /** Template id being edited. */
    templateid: number;
    /** Context id used for fragment loading. */
    contextid: number;
    /** Page id being rearranged. */
    pageid: number;
    /** Page geometry. */
    page: CertificatePage;
    /** Elements on the page. */
    elements: CertificateElement[];
    /** URL for the template edit page (save and close / cancel). */
    editurl: string;
    /** URL for the rearrange page (save and continue). */
    rearrangeurl: string;
};

/** Element position values saved via ajax.php. */
export type ElementPositionValue = {
    id: number;
    posx: number;
    posy: number;
};

/** Name/value pair accepted by mod_customcert_save_element. */
export type ElementFormValue = {
    name: string;
    value: string;
};

/**
 * Generic layout properties returned by mod_customcert_save_element after a successful save.
 *
 * These are element-type agnostic so the rearranger can refresh React state without a reload.
 */
export type SaveElementResult = {
    /** Element instance id. */
    id: number;
    /** X position in mm. */
    posx: number;
    /** Y position in mm. */
    posy: number;
    /** Optional max width in mm. */
    width: number | null;
    /** Reference point: 0 top-left, 1 top-center, 2 top-right. */
    refpoint: number;
    /** Text alignment: L, C, or R. */
    alignment: string;
    /** Server-rendered preview HTML for the element. */
    html: string;
};
