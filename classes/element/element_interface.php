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
 * Defines the minimal contract for a certificate element: identity, page
 * membership, name, data payload and type. Layout and rendering are handled
 * by the concrete base class and the repository respectively.
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

    /**
     * Returns the type of the element.
     *
     * @return string
     */
    public function get_type(): string;
}
