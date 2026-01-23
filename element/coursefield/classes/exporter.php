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

namespace customcertelement_coursefield;

use core_course\customfield\course_handler;
use mod_customcert\export\contracts\subplugin_text_exportable;
use mod_customcert\export\datatypes\enum_field;
use mod_customcert\export\datatypes\i_field;

/**
 * Handles import and export of course field elements for custom certificates.
 *
 * This exporter processes course-related fields, including predefined ones like
 * fullname, shortname, and idnumber, as well as custom fields. It verifies the
 * existence of the field during import and serializes its identifier.
 *
 * @package    customcertelement_coursefield
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_text_exportable {
    /**
     * Defines the custom data fields
     *
     * @return i_field[] plugin-specific custom data fields
     */
    protected function get_fields(): array {
        return parent::get_fields() + [
            'customfieldname' => new enum_field($this->get_course_fields()),
        ];
    }

    /**
     * Gets a list of valid course field types (inclusive moodle specific fields)
     *
     * @return array list of course field types
     */
    private function get_course_fields(): array {
        $fields = ['fullname', 'shortname', 'idnumber'];

        // Get the course custom fields.
        $handler = course_handler::create();
        $customfields = $handler->get_fields();
        $arrcustomfields = array_map(fn ($field) => $field->get('id'), $customfields);

        return array_merge($fields, $arrcustomfields);
    }
}
