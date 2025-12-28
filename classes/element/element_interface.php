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
 * Defines the interface for Element System v2 elements.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

/**
 * Interface element_interface
 *
 * Defines the contract for an element, providing functions related to its metadata,
 * positioning, and fundamental attributes. This interface is focused on the
 * minimal set of attributes useful across various element types without coupling
 * to specific implementations or rendering requirements. Styling and other
 * content-specific data should be part of the element's editable payload.
 */
interface element_interface {
    /**
     * Returns the unique ID of the element record.
     *
     * @return int
     */
    public function get_id(): int;

    /**
     * Returns the page ID this element belongs to.
     *
     * @return int
     */
    public function get_pageid(): int;

    /**
     * Returns the human-readable element name.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Returns the raw element data payload.
     *
     * @return mixed
     */
    public function get_data(): mixed;

    // Presentation-related styling such as font, fontsize, and colour are
    // no longer part of the base element contract. These values should be
    // stored in the element's data payload and consumed by renderers or
    // element-specific helpers. If shared styling contracts are needed,
    // prefer dedicated opt-in interfaces.

    /**
     * Returns the X coordinate used for positioning.
     *
     * @return int|null
     */
    public function get_posx(): ?int;

    /**
     * Returns the Y coordinate used for positioning.
     *
     * @return int|null
     */
    public function get_posy(): ?int;

    // Width is persisted inside the element data payload and is not part of
    // the base contract to avoid coupling to specific element types.

    /**
     * Returns the reference point constant used for positioning.
     *
     * @return int|null
     */
    public function get_refpoint(): ?int;

    /**
     * Returns the alignment value (e.g., left, center, right).
     *
     * @return string
     */
    public function get_alignment(): string;

    /**
     * Returns the type of the element.
     *
     * @return string
     */
    public function get_type(): string;
}
