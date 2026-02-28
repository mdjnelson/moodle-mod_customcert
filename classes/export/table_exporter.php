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

namespace mod_customcert\export;

use core\di;
use moodle_database;

/**
 * Exports database records from a specified table by ID and selected fields.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table_exporter {
    /**
     * @var moodle_database Database connection
     */
    private moodle_database $db;
    /** @var string The name of the table to export from. */
    public readonly string $tablename;

    /**
     * Constructor.
     *
     * @param string $tablename The name of the table to export from.
     */
    public function __construct(
        string $tablename
    ) {
        $this->tablename = $tablename;
        $this->db = di::get(moodle_database::class);
    }

    /**
     * Retrieves a record from the table using the given ID and fields.
     *
     * @param int $id The primary key ID of the record to fetch.
     * @param array $fields List of fields to retrieve.
     * @return array Associative array of the retrieved record data.
     */
    public function export(int $id, array $fields): array {
        $data = (array) $this->db->get_record(
            $this->tablename,
            ['id' => $id],
            implode(', ', $fields)
        );

        return $data;
    }
}
