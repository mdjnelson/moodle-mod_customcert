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
 * Provides helper functionality.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

use mod_customcert\dto\config_bag;

/**
 * Base abstract configurable element (scaffolding only; not wired yet).
 *
 * Provides typed, immutable configuration access for future element implementations
 * via {@see config_bag}. Legacy properties and storage used by
 * existing elements remain unchanged and are not impacted by this class at this stage.
 * No behavior is wired into runtime in this phase.
 */
abstract class abstract_configurable_element extends abstract_element {
    /** @var config_bag */
    protected config_bag $config;

    /**
     * Constructor.
     *
     * @param config_bag|null $config
     */
    public function __construct(?config_bag $config = null) {
        $this->config = $config ?? config_bag::empty();
    }

    /**
     * Get the configuration.
     *
     * @return config_bag
     */
    public function get_config(): config_bag {
        return $this->config;
    }

    /**
     * Return a new bag with the given key set to value.
     *
     * @param config_bag $config
     */
    public function with_config(config_bag $config): static {
        $clone = clone $this;
        $clone->config = $config;

        return $clone;
    }
}
