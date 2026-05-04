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
 * Element type registry for mapping keys to element classes.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use coding_exception;
use moodle_exception;
use mod_customcert\element\form_element_interface;

/**
 * Simple in-memory registry of element types to class names.
 */
final class element_registry {
    /** @var array<string, class-string> */
    private array $map = [];

    /**
     * Register a class for a given element type key.
     *
     * @param string $type
     * @param string $class Must implement form_element_interface (or extend mod_customcert\element, which does)
     * @return void
     */
    public function register(string $type, string $class): void {
        if (!class_exists($class)) {
            throw new coding_exception("Cannot register element type '{$type}': class '{$class}' does not exist.");
        }
        if (!is_a($class, form_element_interface::class, true)) {
            throw new coding_exception(
                "Cannot register element type '{$type}': '{$class}' must implement form_element_interface."
            );
        }
        $this->map[$type] = $class;
    }

    /**
     * Whether the given element type is registered.
     *
     * @param string $type
     * @return bool
     */
    public function has(string $type): bool {
        return array_key_exists($type, $this->map);
    }

    /**
     * Get the class-string for the given type.
     *
     * @param string $type
     * @return string class-string
     */
    public function get(string $type): string {
        if (!$this->has($type)) {
            throw new moodle_exception('Unknown element type: ' . $type);
        }
        return $this->map[$type];
    }

    /**
     * Return all registered mappings.
     *
     * @return array<string, class-string>
     */
    public function all(): array {
        return $this->map;
    }
}
