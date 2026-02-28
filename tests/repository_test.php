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
use mod_customcert\service\certificate_repository;
use mod_customcert\service\issue_email_repository;
use mod_customcert\service\issue_repository;
use mod_customcert\service\template_service;

/**
 * Unit tests for customcert repositories.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class repository_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Ensures issues can be created, marked emailed, and listed.
     *
     * @covers \mod_customcert\service\issue_repository::create
     * @covers \mod_customcert\service\issue_repository::mark_emailed
     * @covers \mod_customcert\service\issue_repository::list_emailed_users
     */
    public function test_issue_repository_marks_and_lists_emailed(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id, 'emailstudents' => 1]);

        $template = template::load((int)$customcert->templateid);
        $templateservice = template_service::create();
        $pageid = $templateservice->add_page($template);
        $DB->insert_record('customcert_elements', (object)['pageid' => $pageid, 'name' => 'X']);

        $issues = new issue_repository();
        $issueid = $issues->create($customcert->id, $student->id);

        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);
        $this->assertEquals(0, (int)$issue->emailed);

        $issues->mark_emailed($issueid);
        $issuedusers = $issues->list_emailed_users($customcert->id);

        $this->assertArrayHasKey($student->id, $issuedusers);
        $this->assertEquals(1, (int)$DB->get_field('customcert_issues', 'emailed', ['id' => $issueid]));
    }

    /**
     * Verifies certificate retrieval and element presence checks.
     *
     * @covers \mod_customcert\service\certificate_repository::get_for_processing
     * @covers \mod_customcert\service\certificate_repository::has_elements
     */
    public function test_certificate_repository_gets_and_checks_elements(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id, 'emailstudents' => 1]);

        $templateservice = template_service::create();
        $template = template::load((int)$customcert->templateid);
        $pageid = $templateservice->add_page($template);

        $repository = new certificate_repository();
        $record = $repository->get_for_processing((int)$customcert->id);
        $this->assertNotNull($record);
        $this->assertEquals($customcert->id, (int)$record->id);
        $this->assertEquals(0, $repository->has_elements((int)$record->contextid));

        $DB->insert_record('customcert_elements', (object)['pageid' => $pageid, 'name' => 'Y']);
        $this->assertEquals(1, $repository->has_elements((int)$record->contextid));
    }

    /**
     * Ensures get_by_template_id_or_fail() returns the record when it exists.
     *
     * @covers \mod_customcert\service\certificate_repository::get_by_template_id_or_fail
     */
    public function test_get_by_template_id_or_fail_returns_record(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $repository = new certificate_repository();
        $record = $repository->get_by_template_id_or_fail((int)$customcert->templateid);
        $this->assertEquals($customcert->id, (int)$record->id);
    }

    /**
     * Ensures get_by_template_id_or_fail() throws when no record exists.
     *
     * @covers \mod_customcert\service\certificate_repository::get_by_template_id_or_fail
     */
    public function test_get_by_template_id_or_fail_throws_when_missing(): void {
        $repository = new certificate_repository();
        $this->expectException(\dml_missing_record_exception::class);
        $repository->get_by_template_id_or_fail(999999);
    }

    /**
     * Ensures email repository can load certificate and issued user records.
     *
     * @covers \mod_customcert\service\issue_email_repository::get_customcert_for_email
     * @covers \mod_customcert\service\issue_email_repository::get_user_for_issue
     */
    public function test_issue_email_repository_loads_customcert_and_user(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id, 'emailstudents' => 1]);

        $templateservice = template_service::create();
        $template = template::load((int)$customcert->templateid);
        $pageid = $templateservice->add_page($template);
        $DB->insert_record('customcert_elements', (object)['pageid' => $pageid, 'name' => 'Z']);

        $issuerepo = new issue_repository();
        $issueid = $issuerepo->create($customcert->id, $student->id);

        $emailrepo = new issue_email_repository();

        $loadedcert = $emailrepo->get_customcert_for_email($customcert->id);
        $this->assertNotNull($loadedcert);
        $this->assertEquals($customcert->id, (int)$loadedcert->id);
        $this->assertEquals($customcert->templateid, (int)$loadedcert->templateid);
        $this->assertEquals($course->id, (int)$loadedcert->courseid);

        $loadeduser = $emailrepo->get_user_for_issue($customcert->id, $issueid);
        $this->assertNotNull($loadeduser);
        $this->assertEquals($student->id, (int)$loadeduser->id);
        $this->assertEquals($issueid, (int)$loadeduser->issueid);
    }

    /**
     * Email repository should return null for missing records.
     *
     * @covers \mod_customcert\service\issue_email_repository::get_customcert_for_email
     * @covers \mod_customcert\service\issue_email_repository::get_user_for_issue
     */
    public function test_issue_email_repository_handles_missing_records(): void {
        $emailrepo = new issue_email_repository();

        $this->assertNull($emailrepo->get_customcert_for_email(123456));
        $this->assertNull($emailrepo->get_user_for_issue(123456, 789));
    }
}
