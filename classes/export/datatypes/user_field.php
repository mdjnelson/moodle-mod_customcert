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

namespace mod_customcert\export\datatypes;

use core\di;
use moodle_database;

/**
 * Handles export and import of user data fields for custom certificate subplugins.
 *
 * This class validates and serializes user references, ensuring that user identities
 * match between stored data and current Moodle user records.
 *
 * @package    mod_customcert
 * @copyright  2026, onCampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_field implements i_field {
    /**
     * @var moodle_database Reference to the Moodle database instance used for user lookups.
     */
    private moodle_database $db;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db = di::get(moodle_database::class);
    }

    /**
     * Validates and imports user data from a provided array.
     *
     * Ensures the user exists in the database and that the full name matches
     * the expected value from the import data.
     *
     * @param array $data Associative array with 'userid' and 'fullname'.
     * @return int The validated user ID.
     * @throws format_exception If the user does not exist or the name does not match.
     */
    public function import(array $data) {
        $userid = $data['userid'] ?? -1;
        $username = $data['fullname'] ?? null;

        $user = $this->db->get_record('user', ['id' => $userid]);

        if (!$user) {
            throw new format_exception("User with $userid does not exist");
        }

        if (fullname($user) != $username) {
            throw new format_exception("User with $userid is not the same as in backup.");
        }

        return $userid;
    }

    /**
     * Exports a user field value into a structured array.
     *
     * Retrieves the user by ID and includes both ID and full name in the export.
     * Returns an empty array if the user cannot be found.
     *
     * @param mixed $value The user ID to export.
     * @return array Exported data with 'userid' and 'fullname' keys, or an empty array.
     */
    public function export($value): array {
        if (empty($value)) {
            return [];
        }

        $userid = (int) $value;
        $user = $this->db->get_record('user', ['id' => $userid]);

        if (!$user) {
            return [];
        }

        return [
            'userid' => $userid,
            'fullname' => fullname($user),
        ];
    }

    /**
     * Empty cause import not possible
     *
     * @return string Empty fallback.
     */
    public function get_fallback() {
        return "";
    }
}
