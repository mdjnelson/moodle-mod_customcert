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
use stdClass;

/**
 * Registry-based factory for creating elements by type.
 */
class element_factory {
    /**
     * @var element_registry Element type registry instance.
     */
    private element_registry $registry;

    /**
     * Constructor.
     *
     * @param element_registry $registry
     */
    public function __construct(element_registry $registry) {
        $this->registry = $registry;
    }

    /**
     * Register an element class for a given type key.
     *
     * @param string $type
     * @param string $class Class-string implementing element_interface
     * @return void
     */
    public function register(string $type, string $class): void {
        $this->registry->register($type, $class);
    }

    /**
     * Create an element instance from a record and type.
     *
     * @param string $type
     * @param stdClass $record
     * @return element_interface
     */
    public function create(string $type, stdClass $record)/*: element_interface */ {
        $class = $this->registry->get($type);
        // The returned instance will be a legacy element class (e.g., customcertelement_foo\element)
        // which does not implement element_interface yet. We keep the return type unhinted here
        // to preserve compatibility while the adapter layer is introduced.
        $instance = new $class($record);
        return $instance;
    }
}
