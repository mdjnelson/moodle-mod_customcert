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
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\element\element_interface;

/**
 * Simple in-memory registry of element types to class names.
 */
class element_registry {
    /** @var array<string, class-string> */
    private array $map = [];

    /**
     * Register a class for a given element type key.
     *
     * @param string $type
     * @param string $class Must implement element_interface
     * @return void
     */
    public function register(string $type, string $class): void {
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
            throw new \moodle_exception('Unknown element type: ' . $type);
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
