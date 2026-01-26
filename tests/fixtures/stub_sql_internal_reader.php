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

namespace mod_customcert\tests\fixtures;

use core\log\sql_internal_table_reader;

/**
 * Minimal sql_internal_table_reader stub to satisfy the contract for time calculations.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stub_sql_internal_reader implements sql_internal_table_reader {
    /** @var string */
    private string $tablename;

    /**
     * Constructor.
     *
     * @param string $tablename Table name to expose via the stub reader
     */
    public function __construct(string $tablename) {
        $this->tablename = $tablename;
    }

    /**
     * Get the log table name used by this stub reader.
     *
     * @return string
     */
    public function get_internal_log_table_name(): string {
        return $this->tablename;
    }

    /**
     * Short name for the stub reader.
     *
     * @return string
     */
    public function get_name() {
        return 'stub';
    }

    /**
     * Human-readable description for the stub reader.
     *
     * @return string
     */
    public function get_description() {
        return 'stub';
    }

    /**
     * Indicates logging is active for the stub reader.
     *
     * @return bool
     */
    public function is_logging() {
        return true;
    }

    /**
     * Return an empty event list for the stub.
     *
     * @param string $selectwhere
     * @param array $params
     * @param string $sort
     * @param int $limitfrom
     * @param int $limitnum
     * @return array
     */
    public function get_events_select($selectwhere, array $params, $sort, $limitfrom, $limitnum) {
        return [];
    }

    /**
     * Return zero event count for the stub.
     *
     * @param string $selectwhere
     * @param array $params
     * @return int
     */
    public function get_events_select_count($selectwhere, array $params) {
        return 0;
    }

    /**
     * Return false for existence checks.
     *
     * @param string $selectwhere
     * @param array $params
     * @return bool
     */
    public function get_events_select_exists(string $selectwhere, array $params): bool {
        return false;
    }

    /**
     * Return an empty iterator for the stub.
     *
     * @param string $selectwhere
     * @param array $params
     * @param string $sort
     * @param int $limitfrom
     * @param int $limitnum
     * @return \Traversable
     */
    public function get_events_select_iterator($selectwhere, array $params, $sort, $limitfrom, $limitnum) {
        return new \EmptyIterator();
    }

    /**
     * Stub event factory (unused).
     *
     * @param \stdClass $data
     * @return null
     */
    public function get_log_event($data) {
        return null;
    }
}
