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
use mod_customcert\element\constructable_element_interface;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\element_bootstrap;
use mod_customcert\element as legacy_base;
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
     * @param string $type
     * @param stdClass $record
     * @return element_interface
     */
    public function create(string $type, stdClass $record): element_interface {
        $class = $this->registry->get($type);
        // Prefer the explicit construction contract when provided.
        try {
            if (is_subclass_of($class, constructable_element_interface::class)) {
                $instance = $class::from_record($record);
            } else {
                $instance = new $class($record);
            }
        } catch (\Throwable $e) {
            // Provide a clearer developer hint if construction fails.
            debugging(
                "Failed to construct element of type '{$type}' using class '{$class}': " . $e->getMessage() .
                '. Implement constructable_element_interface::from_record() or ensure a compatible constructor.',
                DEBUG_DEVELOPER
            );
            throw $e;
        }

        if ($instance instanceof element_interface) {
            return $instance;
        }
        return new legacy_element_adapter($instance);
    }

    /**
     * Create an element from a legacy record structure, falling back to the shim when required.
     *
     * @param stdClass $record
     * @return element_interface|null
     */
    public function create_from_legacy_record(stdClass $record): ?element_interface {
        $type = (string)($record->element ?? '');
        if ($type === '') {
            return null;
        }

        // Preserve legacy behaviour: default the name when not provided so forms/tests relying on
        // legacy construction still see a sensible value (pluginname).
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
        }

        try {
            $legacy = self::get_element_instance($record);
        } catch (\Throwable $unused) {
            return null;
        }

        if ($legacy instanceof element_interface) {
            return $legacy;
        }

        return $legacy ? new legacy_element_adapter($legacy) : null;
    }

    /**
     * Wrap a legacy element instance with the v2 adapter.
     *
     * @param legacy_base $legacy
     * @return legacy_element_adapter
     */
    public function wrap_legacy(legacy_base $legacy): legacy_element_adapter {
        return new legacy_element_adapter($legacy);
    }

    /**
     * Backwards-compatible helper: return legacy element instance for given record.
     *
     * This mirrors the old static API `mod_customcert\\element_factory::get_element_instance($element)`
     * so existing call sites can be updated to reference this class without changing behavior.
     *
     * @deprecated since Moodle 5.2. Use element_factory::create() or create_from_legacy_record() instead.
     *
     * @param stdClass $element DB record or structure with at least the `element` type and optional fields.
     * @return object|false Legacy element instance (customcertelement_*\\element) or false if not found.
     */
    public static function get_element_instance(stdClass $element) {
        debugging(
            'element_factory::get_element_instance() is deprecated. Use element_factory::create() or ' .
            'create_from_legacy_record() instead.',
            DEBUG_DEVELOPER
        );

        // Compose legacy class name like: \customcertelement_{type}\element.
        $classname = '\\customcertelement_' . ($element->element ?? '') . '\\element';

        $data = new stdClass();
        $data->id = $element->id ?? null;
        $data->pageid = $element->pageid ?? null;
        $data->name = $element->name ?? get_string('pluginname', 'customcertelement_' . ($element->element ?? ''));
        $data->element = $element->element ?? null;
        $data->data = $element->data ?? null;
        $data->font = $element->font ?? null;
        $data->fontsize = $element->fontsize ?? null;
        $data->colour = $element->colour ?? null;
        $data->posx = $element->posx ?? null;
        $data->posy = $element->posy ?? null;
        $data->width = $element->width ?? null;
        $data->refpoint = $element->refpoint ?? null;
        $data->alignment = $element->alignment ?? null;

        if (class_exists($classname)) {
            return new $classname($data);
        }

        return false;
    }
}
