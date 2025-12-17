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
 * Base abstract element class for the Element System v2.
 *
 * This class is scaffolding only. It defines the minimal identity and attribute
 * surface for new element implementations. It is not used by legacy elements.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

/**
 * Abstract element definition for Element System v2.
 *
 * All methods describe element metadata only. Concrete element classes will
 * implement these getters. No runtime behavior exists at this stage.
 */
abstract class abstract_element implements element_interface {
    /**
     * Get the internal element ID.
     *
     * @return int Element database ID.
     */
    abstract public function get_id(): int;

    /**
     * Get the page ID this element belongs to.
     *
     * @return int Page ID.
     */
    abstract public function get_pageid(): int;

    /**
     * Get the display/name label of the element.
     *
     * @return string Human-readable name.
     */
    abstract public function get_name(): string;

    /**
     * Get element data payload (raw configuration or legacy data).
     *
     * @return mixed Arbitrary element data.
     */
    abstract public function get_data(): mixed;

    /**
     * Get the font name used by this element.
     *
     * @return string|null Font name or null if not applicable.
     */
    abstract public function get_font(): ?string;

    /**
     * Get the font size used by this element.
     *
     * @return int|null Font size or null if not set.
     */
    abstract public function get_fontsize(): ?int;

    /**
     * Get the colour value used by this element.
     *
     * @return string|null Colour value (e.g., hex) or null.
     */
    abstract public function get_colour(): ?string;

    /**
     * Get the X coordinate position.
     *
     * @return int|null X position in mm.
     */
    abstract public function get_posx(): ?int;

    /**
     * Get the Y coordinate position.
     *
     * @return int|null Y position in mm.
     */
    abstract public function get_posy(): ?int;

    /**
     * Get the width allocated to the element.
     *
     * @return int|null Width in mm, or null if auto.
     */
    abstract public function get_width(): ?int;

    /**
     * Get the reference point used for positioning.
     *
     * @return int|null Reference point constant or null.
     */
    abstract public function get_refpoint(): ?int;

    /**
     * Get the alignment for this element (e.g., left, center, right).
     *
     * @return string Alignment value.
     */
    abstract public function get_alignment(): string;
}
