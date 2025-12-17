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
 * Element definition DTO.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\dto;

/**
 * Element definition captures stable metadata about an element type.
 *
 * Scaffolding only; not used at runtime yet.
 */
final class element_definition {
    /**
     * Constructor.
     *
     * Represents metadata describing a single element type such as "text", "image", etc.
     *
     * @param string $type A unique machine-readable type name.
     * @param string $name A human-readable name for UI use.
     * @param string $component The Moodle plugin component string, e.g. 'mod_customcert'.
     * @param array $defaultconfig Default configuration array for this element.
     */
    public function __construct(
        /** @var string A unique machine-readable type name. **/
        private readonly string $type,
        /** @var string A human-readable name for UI use. **/
        private readonly string $name,
        /** @var string The Moodle plugin component string, e.g. 'mod_customcert'. **/
        private readonly string $component,
        /** @var array Default configuration array for this element. **/
        private readonly array $defaultconfig = []
    ) {
    }

    /**
     * Get the element type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Get the human-readable element name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Get the Moodle component that owns this element.
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Get the default configuration for this element type.
     *
     * @return array<string,mixed>
     */
    public function get_default_config(): array {
        return $this->defaultconfig;
    }
}
