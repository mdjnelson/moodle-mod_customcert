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
 * Backward-compatibility shim for mod_customcert\element_factory.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use mod_customcert\service\element_factory as service_element_factory;

/**
 * Deprecated element factory shim.
 *
 * @deprecated since Moodle 5.2 — use mod_customcert\service\element_factory instead.
 *   This class exists solely to avoid hard BC breaks for third-party code that calls
 *   \mod_customcert\element_factory::get_element_instance(). It will be removed in a
 *   future major release.
 */
final class element_factory {
    /**
     * Returns an element instance for the given record.
     *
     * @deprecated since Moodle 5.2 — use mod_customcert\service\element_factory::get_element_instance() instead.
     * @param \stdClass $element A record from customcert_elements.
     * @return mixed Element instance (legacy or v2).
     */
    public static function get_element_instance(\stdClass $element) {
        debugging(
            '\mod_customcert\element_factory::get_element_instance() is deprecated since Moodle 5.2. '
            . 'Use \mod_customcert\service\element_factory::get_element_instance() instead.',
            DEBUG_DEVELOPER
        );
        return service_element_factory::get_element_instance($element);
    }
}
