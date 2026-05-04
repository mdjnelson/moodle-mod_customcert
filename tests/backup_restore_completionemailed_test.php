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

declare(strict_types=1);

namespace mod_customcert;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');

/**
 * Tests that the 'completionemailed' field survives a backup/restore round-trip.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \backup_customcert_activity_structure_step
 * @covers \restore_customcert_activity_structure_step
 */
final class backup_restore_completionemailed_test extends \restore_date_testcase {
    /**
     * The 'completionemailed' field must be preserved after a full backup/restore cycle.
     */
    public function test_completionemailed_survives_backup_restore(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and a customcert instance with completionemailed = 1.
        [$course, $customcert] = $this->create_course_and_module(
            'customcert',
            ['completionemailed' => 1, 'emailstudents' => 1]
        );

        $this->assertSame(
            1,
            (int)$customcert->completionemailed,
            'Pre-condition: completionemailed must be 1 on the original instance.'
        );

        // Backup the course and restore it into a new course.
        $newcourseid = $this->backup_and_restore($course);

        // Retrieve the restored customcert record.
        $restored = $DB->get_record('customcert', ['course' => $newcourseid], '*', MUST_EXIST);

        $this->assertSame(
            1,
            (int)$restored->completionemailed,
            'completionemailed must be 1 after restore.'
        );
    }

    /**
     * When completionemailed = 0 the field must also be preserved (not silently defaulted to 1).
     */
    public function test_completionemailed_zero_survives_backup_restore(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $customcert] = $this->create_course_and_module(
            'customcert',
            ['completionemailed' => 0]
        );

        $this->assertSame(
            0,
            (int)$customcert->completionemailed,
            'Pre-condition: completionemailed must be 0 on the original instance.'
        );

        $newcourseid = $this->backup_and_restore($course);

        $restored = $DB->get_record('customcert', ['course' => $newcourseid], '*', MUST_EXIST);

        $this->assertSame(
            0,
            (int)$restored->completionemailed,
            'completionemailed must be 0 after restore.'
        );
    }
}
