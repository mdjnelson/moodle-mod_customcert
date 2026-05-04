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
 * Contract for elements that expose positional/layout attributes.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
declare(strict_types=1);

namespace mod_customcert\element;

/**
 * Interface layout_element_interface
 *
 * Implemented by elements that expose the standard positional and layout
 * attributes used by element_helper::render_content() and
 * element_helper::render_html_content(). Both the legacy element base class
 * and legacy_element_adapter implement this interface so that element_helper
 * can accept either without coupling to the concrete base class.
 */
interface layout_element_interface {
    /**
     * Returns the X position of the element on the page (in mm).
     *
     * @return int|null
     */
    public function get_posx(): ?int;

    /**
     * Returns the Y position of the element on the page (in mm).
     *
     * @return int|null
     */
    public function get_posy(): ?int;

    /**
     * Returns the reference point constant for this element.
     *
     * @return int|null
     */
    public function get_refpoint(): ?int;

    /**
     * Returns the text alignment for this element.
     *
     * Returns null when no alignment is stored (e.g. old/partial records or
     * unknown/fallback elements). Callers such as element_helper default to
     * left alignment ('L') when null is returned.
     *
     * @return string|null
     */
    public function get_alignment(): ?string;
}
