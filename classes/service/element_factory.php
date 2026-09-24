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

use coding_exception;
use mod_customcert\element as legacy_base;
use mod_customcert\element\element_bootstrap;
use mod_customcert\element\element_interface;
use mod_customcert\element\legacy_compatibility_diagnostic;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\renderable_element_interface;
use stdClass;

/**
 * Registry-based factory for creating elements by type.
 *
 * Creation path for Moodle 5.3:
 * - Instantiate the registered class
 * - If it implements renderable_element_interface (native v2), return it directly
 * - If it is a supported legacy mod_customcert\element subclass, wrap via legacy_element_adapter
 * - Otherwise fail clearly
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
     * @param string $class Class-string of a native v2 element or legacy mod_customcert\element subclass
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

        // Native v2 path: already satisfies the renderable contract (and usually element_interface).
        if ($instance instanceof renderable_element_interface && $instance instanceof element_interface) {
            return $instance;
        }

        // Legacy path: wrap supported historical subclasses. Emit the single general
        // compatibility diagnostic here, at the narrowest coherent boundary where a
        // legacy element is detected and wrapped, deduplicated per component/type.
        if ($instance instanceof legacy_base) {
            legacy_compatibility_diagnostic::notify($type, $class);
            return new legacy_element_adapter($instance);
        }

        throw new coding_exception(
            "Element factory cannot use class '{$class}' for type '{$type}': "
            . 'it must implement renderable_element_interface (native v2) or extend mod_customcert\element.'
        );
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
