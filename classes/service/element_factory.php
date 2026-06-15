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
 * The element_factory - Registry-based factory for creating elements by type.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\element\element_interface;
use mod_customcert\element\element_bootstrap;
use stdClass;

/**
 * Registry-based factory for creating elements by type.
 */
final class element_factory {
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
     * Build a factory with the default registry wiring and plugin discovery.
     *
     * @return self
     */
    public static function build_with_defaults(): self {
        $registry = new element_registry();
        element_bootstrap::register_defaults($registry);
        return new self($registry);
    }

    /**
     * Create an element instance from a record and type.
     *
     * The registered class must implement element_interface. If it does not,
     * element_registry::register() will have already thrown a coding_exception
     * at registration time.
     *
     * @param string $type
     * @param stdClass $record
     * @return element_interface
     */
    public function create(string $type, stdClass $record): element_interface {
        $class = $this->registry->get($type);
        try {
            $instance = new $class($record);
        } catch (\Throwable $e) {
            // Provide a clearer developer hint if construction fails.
            debugging(
                "Failed to construct element of type '{$type}' using class '{$class}': " . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            throw $e;
        }
        return $instance;
    }

    /**
     * Create an element from a record, returning null when the type is unknown.
     *
     * @param stdClass $record
     * @return element_interface|null
     */
    public function create_from_record(stdClass $record): ?element_interface {
        $type = (string)($record->element ?? '');
        if ($type === '') {
            return null;
        }
        if (!$this->registry->has($type)) {
            return null;
        }
        // Default the name when not provided so forms/tests see a sensible value.
        if (!property_exists($record, 'name') || $record->name === null || $record->name === '') {
            $record->name = get_string('pluginname', 'customcertelement_' . $type);
        }
        try {
            return $this->create($type, $record);
        } catch (\Throwable $e) {
            if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
                debugging(
                    "Element factory failed for type '{$type}': " . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
            return null;
        }
    }
}
