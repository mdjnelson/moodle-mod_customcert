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
    /**
     * Validates that the specified user field exists in core or as a custom profile field.
     *
     * Logs a warning and rejects the data if the field is not found.
     *
     * @param array $data Element configuration including 'userfield'.
     * @return array|false Validated data array or false if invalid.
     */
    public function validate(array $data): array|false {
        $userfield = $data['userfield'];

        if (!$this->validate_userfield($userfield)) {
            $this->logger->warning("User field $userfield does not exists");
            return false;
        }

        return $data;
    }

    /**
     * Extracts the user field identifier for storage.
     *
     * @param array $data Validated user field data.
     * @return string|null The field name to store.
     */
    public function convert_for_import(array $data): ?string {
        return $data['userfield'];
    }

    /**
     * Checks if a user field exists among core user fields or custom profile fields.
     *
     * @param string $userfield The field identifier to validate.
     * @return bool True if the field exists, false otherwise.
     */
    private function validate_userfield(string $userfield): bool {
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

        if (in_array($userfield, $basefields)) {
            return true;
        }

        $arrcustomfields = condition::get_custom_profile_fields();
        $customfields = array_map(fn ($field) => $field->id, $arrcustomfields);
        if (in_array($userfield, $customfields)) {
            return true;
        }

        return false;
    }

    /**
     * Prepares the user field for export in an associative array format.
     *
     * @param int $elementid The ID of the user field element.
     * @param string $customdata Stored field name.
     * @return array Associative array containing the 'userfield' key.
     */
    public function export(int $elementid, string $customdata): array {
        $data = [];
        $data['userfield'] = $customdata;
        return $data;
    }
}
