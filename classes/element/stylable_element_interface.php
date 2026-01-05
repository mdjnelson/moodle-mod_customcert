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

/**
 * Contract for elements that expose standard visual attributes.
 *
 * Implement this in addition to element_interface when your element supports
 * the shared visual fields (font, fontsize, colour, width). This lets renderers
 * and form services safely rely on these getters via a typed contract.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface stylable_element_interface {
    /**
     * Get the font name for this element or null if not set.
     */
    public function get_font(): ?string;

    /**
     * Get the font size for this element or null if not set.
     */
    public function get_fontsize(): ?int;

    /**
     * Get the font colour (hex or named) for this element or null if not set.
     */
    public function get_colour(): ?string;

    /**
     * Get the width for this element (in mm) or null if not set.
     */
    public function get_width(): ?int;
}
