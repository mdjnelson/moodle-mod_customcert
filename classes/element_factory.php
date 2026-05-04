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
 * @copyright  2017 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

/**
 * Deprecated element factory shim.
 *
 * @deprecated since Moodle 5.2 — use mod_customcert\service\element_factory instead.
 *   This class exists solely to avoid hard BC breaks for third-party code that calls
 *   \mod_customcert\element_factory::get_element_instance(). It will be removed in a
 *   future major release.
 */
class element_factory {
    /**
     * Returns an element instance for the given record.
     *
     * @deprecated since Moodle 5.2 — use mod_customcert\service\element_factory::build_with_defaults()->create_from_legacy_record()
     *   or inject mod_customcert\service\element_factory and call create() / create_from_legacy_record() instead.
     * @param mixed $element A record from customcert_elements.
     * @return mixed Element instance or false if the element class does not exist.
     */
    public static function get_element_instance($element) {
        debugging(
            '\mod_customcert\element_factory::get_element_instance() is deprecated since Moodle 5.2. '
            . 'Use \mod_customcert\service\element_factory::build_with_defaults()->create_from_legacy_record() '
            . 'or inject \mod_customcert\service\element_factory and call create() / create_from_legacy_record().',
            DEBUG_DEVELOPER
        );

        $classname = '\\customcertelement_' . ($element->element ?? '') . '\\element';
        $data = new \stdClass();
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
