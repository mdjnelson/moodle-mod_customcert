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
 * element_factory (scaffolding only; not wired yet).
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\element\element_interface;
use moodle_exception;
use stdClass;

/**
 * Registry-based factory for creating elements by type.
 */
class element_factory {
    /** @var array<string, class-string> */
    private array $registry = [];

    /**
     * Register an element class for a given type key.
     *
     * @param string $type
     * @param string $class Class-string implementing element_interface
     * @return void
     */
    public function register(string $type, string $class): void {
        $this->registry[$type] = $class;
    }

    /**
     * Create an element instance from a record and type.
     *
     * @param string $type
     * @param stdClass $record
     * @return element_interface
     */
    public function create(string $type, stdClass $record): element_interface {
        if (!isset($this->registry[$type])) {
            throw new moodle_exception('Unknown element type: ' . $type);
        }
        $class = $this->registry[$type];
        /** @var element_interface $instance */
        $instance = new $class($record);
        return $instance;
    }
}
