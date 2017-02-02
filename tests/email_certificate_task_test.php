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

/**
 * File contains the unit tests for the email certificate task.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Unit tests for the email certificate task.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_customcert_task_email_certificate_task_testcase extends advanced_testcase {

    /**
     * Test set up.
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Tests the email certificate task.
     */
    public function test_email_certificates() {
        global $DB;

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create three users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // Enrol two of them in the course.
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $roleids['student']);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $roleids['student']);

        // Create a custom certificate.
        $customcert = $this->getDataGenerator()->create_module('customcert', array('course' => $course->id));

        // Ok, now issue this to one user.
        $customcertissue = new stdClass();
        $customcertissue->customcertid = $customcert->id;
        $customcertissue->userid = $user1->id;
        $customcertissue->code = \mod_customcert\certificate::generate_code();
        $customcertissue->timecreated = time();
        $customcertissue->emailed = 1;

        // Insert the record into the database.
        $DB->insert_record('customcert_issues', $customcertissue);

        // Confirm there is only entry in this table.
        $this->assertEquals(1, $DB->count_records('customcert_issues'));

        // Run the task.
        $task = new \mod_customcert\task\email_certificate_task();
        $task->execute();

        // Get the issues from the issues table now.
        $issues = $DB->get_records('customcert_issues');
        $this->assertCount(2, $issues);

        // Go through the issues and confirm they are all good.

    }
}