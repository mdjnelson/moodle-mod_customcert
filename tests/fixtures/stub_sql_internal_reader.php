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

defined('MOODLE_INTERNAL') || die();

/**
 * Minimal sql_internal_table_reader stub to satisfy the contract for time calculations.
 *
 * @package    mod_customcert
 */
class stub_sql_internal_reader implements sql_internal_table_reader {
    /** @var string */
    private string $tablename;

    /**
     * Constructor.
     *
     * @param string $tablename
     */
    public function __construct(string $tablename) {
        $this->tablename = $tablename;
    }

    /**
     * @return string
     */
    public function get_internal_log_table_name(): string {
        return $this->tablename;
    }

    /**
     * @return string
     */
    public function get_name() {
        return 'stub';
    }

    /**
     * @return string
     */
    public function get_description() {
        return 'stub';
    }

    /**
     * @return bool
     */
    public function is_logging() {
        return true;
    }

    public function get_events_select($selectwhere, array $params, $sort, $limitfrom, $limitnum) {
        return [];
    }

    public function get_events_select_count($selectwhere, array $params) {
        return 0;
    }

    public function get_events_select_exists(string $selectwhere, array $params): bool {
        return false;
    }

    public function get_events_select_iterator($selectwhere, array $params, $sort, $limitfrom, $limitnum) {
        return new \EmptyIterator();
    }

    public function get_log_event($data) {
        return null;
    }
}
