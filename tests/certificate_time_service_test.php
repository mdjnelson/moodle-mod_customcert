<?php
// This file is part of Moodle - http://moodle.org/
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

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\service\certificate_time_service;
use mod_customcert\tests\fixtures\stub_log_manager;
use mod_customcert\tests\fixtures\stub_sql_internal_reader;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/stub_log_manager.php');
require_once(__DIR__ . '/fixtures/stub_sql_internal_reader.php');

/**
 * Tests for the certificate_time_service.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\service\certificate_time_service
 */
final class certificate_time_service_test extends advanced_testcase {
    /**
     * Calculates total course time by accumulating contiguous sessions.
     * @covers ::get_course_time
     */
    public function test_get_course_time_accumulates_sessions(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        $CFG->sessiontimeout = 1_000;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $tablename = 'cert_time_log';
        $this->create_log_table($tablename);
        $this->insert_log($tablename, $course->id, $user->id, 1_000);
        $this->insert_log($tablename, $course->id, $user->id, 1_300);
        $this->insert_log($tablename, $course->id, $user->id, 1_600);

        $logmanager = new stub_log_manager(['stub' => new stub_sql_internal_reader($tablename)]);
        set_config('enabled_stores', 'stub', 'tool_log');

        $service = new certificate_time_service(static fn() => $logmanager, $DB, $CFG);

        $this->assertSame(600, $service->get_course_time($course->id, $user->id));

        $this->drop_log_table($tablename);
    }

    /**
     * Resets accumulated time when gaps exceed session timeout.
     * @covers ::get_course_time
     */
    public function test_get_course_time_resets_after_timeout(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        $CFG->sessiontimeout = 1_000;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $tablename = 'cert_time_log_timeout';
        $this->create_log_table($tablename);
        $this->insert_log($tablename, $course->id, $user->id, 1_000);
        $this->insert_log($tablename, $course->id, $user->id, 1_300);
        $this->insert_log($tablename, $course->id, $user->id, 5_000);
        $this->insert_log($tablename, $course->id, $user->id, 5_200);

        $logmanager = new stub_log_manager(['stub' => new stub_sql_internal_reader($tablename)]);
        set_config('enabled_stores', 'stub', 'tool_log');

        $service = new certificate_time_service(static fn() => $logmanager, $DB, $CFG);

        // First two hits 300s, then a timeout, then 200s.
        $this->assertSame(500, $service->get_course_time($course->id, $user->id));

        $this->drop_log_table($tablename);
    }

    /**
     * Create a temporary log table used by the stub reader.
     *
     * @param string $tablename
     * @return void
     */
    private function create_log_table(string $tablename): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table($tablename);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
    }

    /**
     * Drop the temporary log table if it exists.
     *
     * @param string $tablename
     * @return void
     */
    private function drop_log_table(string $tablename): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table($tablename);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
    }

    /**
     * Insert a log record into the temporary log table.
     *
     * @param string $tablename
     * @param int $courseid
     * @param int $userid
     * @param int $timecreated
     * @return void
     */
    private function insert_log(string $tablename, int $courseid, int $userid, int $timecreated): void {
        global $DB;

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->userid = $userid;
        $record->timecreated = $timecreated;
        $DB->insert_record($tablename, $record);
    }
}
