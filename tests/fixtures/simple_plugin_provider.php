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
 * Simple plugin provider fixture for tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\element\provider\plugin_provider;

/**
 * A plugin provider that returns a fixed list containing only 'fakeplugin'.
 *
 * Used to exercise element_bootstrap discovery with a controlled plugin list.
 */
final class simple_plugin_provider implements plugin_provider {
    /**
     * Returns a fixed list of plugins for testing.
     *
     * @return array<string,string>
     */
    public function get_plugins(): array {
        return ['fakeplugin' => '/virtual/path'];
    }
}
