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
 * Dummy element_interface implementation fixture for tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\element\element_interface;

/**
 * A minimal element implementing element_interface for use in tests.
 *
 * Accepts a page id and element type via constructor; returns fixed stub values
 * for all other interface methods.
 */
final class dummy_element_interface_element implements element_interface {
    /** @var int Page ID. */
    private int $pageid;

    /** @var string Element type. */
    private string $type;

    /**
     * Constructor.
     *
     * @param int $pageid The page id.
     * @param string $type The element type.
     */
    public function __construct(int $pageid, string $type = 'text') {
        $this->pageid = $pageid;
        $this->type = $type;
    }

    /**
     * Get element ID.
     *
     * @return int
     */
    public function get_id(): int {
        return 0;
    }

    /**
     * Get page ID.
     *
     * @return int
     */
    public function get_pageid(): int {
        return $this->pageid;
    }

    /**
     * Get element name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'Dummy name';
    }

    /**
     * Get element data.
     *
     * @return mixed
     */
    public function get_data(): mixed {
        return 'Dummy data';
    }

    /**
     * Get element type.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }
}
