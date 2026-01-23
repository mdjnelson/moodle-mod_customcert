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

namespace customcertelement_userfield;

use availability_profile\condition;
use mod_customcert\classes\export\datatypes\enum_field;
use mod_customcert\export\contracts\subplugin_exportable;

/**
 * Handles import and export of user field elements for custom certificates.
 *
 * This exporter validates standard and custom user profile fields, ensuring only
 * existing fields are serialized. It supports both core user data and extended
 * profile fields configured in Moodle.
 *
 * @package    customcertelement_userfield
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {

    protected function get_fields(): array {
        return [
            'userfield' => new enum_field($this->get_user_fields()),
        ];
    }

    /**
     * Get all valid user field options exists among core user fields and custom profile fields.
     *
     * @return array List of valid user field options.
     */
    private function get_user_fields(): array {
        $basefields = [
            'firstname',
            'lastname',
            'email',
            'city',
            'country',
            'url',
            'idnumber',
            'institution',
            'department',
            'phone1',
            'phone2',
            'address',
        ];

        $arrcustomfields = condition::get_custom_profile_fields();
        $customfields = array_map(fn ($field) => $field->id, $arrcustomfields);
        return array_merge($basefields, $customfields);
    }
}
