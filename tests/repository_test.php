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
use mod_customcert\service\template_repository;
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
     * Ensures get_by_id_or_fail throws when issue does not exist.
     *
     * @covers \mod_customcert\service\issue_repository::get_by_id_or_fail
     */
    public function test_issue_repository_get_by_id_or_fail_throws_when_missing(): void {
        $this->expectException(\dml_missing_record_exception::class);
        (new issue_repository())->get_by_id_or_fail(999999);
    }

    /**
     * Ensures get_by_id_or_fail returns the record when it exists.
     *
     * @covers \mod_customcert\service\issue_repository::get_by_id_or_fail
     */
    public function test_issue_repository_get_by_id_or_fail_returns_record(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $repo = new issue_repository();
        $issueid = $repo->create($customcert->id, $student->id);
        $record = $repo->get_by_id_or_fail($issueid);
        $this->assertEquals($issueid, (int)$record->id);
        $this->assertEquals($student->id, (int)$record->userid);
    }

    /**
     * Ensures delete removes the issue record.
     *
     * @covers \mod_customcert\service\issue_repository::delete
     */
    public function test_issue_repository_delete_removes_record(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $repo = new issue_repository();
        $issueid = $repo->create($customcert->id, $student->id);
        $this->assertTrue($DB->record_exists('customcert_issues', ['id' => $issueid]));
        $repo->delete($issueid);
        $this->assertFalse($DB->record_exists('customcert_issues', ['id' => $issueid]));
    }

    /**
     * Ensures list_by_user_certificate returns all issues for a user/certificate pair.
     *
     * @covers \mod_customcert\service\issue_repository::list_by_user_certificate
     */
    public function test_issue_repository_list_by_user_certificate(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $repo = new issue_repository();
        $issueid = $repo->create($customcert->id, $student->id);
        $list = $repo->list_by_user_certificate($customcert->id, $student->id);
        $this->assertCount(1, $list);
        $this->assertArrayHasKey($issueid, $list);
    }

    /**
     * Ensures exists_for_user returns correct boolean.
     *
     * @covers \mod_customcert\service\issue_repository::exists_for_user
     */
    public function test_issue_repository_exists_for_user(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $repo = new issue_repository();
        $this->assertFalse($repo->exists_for_user($customcert->id, $student->id));
        $repo->create($customcert->id, $student->id);
        $this->assertTrue($repo->exists_for_user($customcert->id, $student->id));
    }

    /**
     * Ensures list_by_certificate returns all issues for a certificate.
     *
     * @covers \mod_customcert\service\issue_repository::list_by_certificate
     */
    public function test_issue_repository_list_by_certificate(): void {
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student1->id, $course->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $repo = new issue_repository();
        $repo->create($customcert->id, $student1->id);
        $repo->create($customcert->id, $student2->id);
        $list = $repo->list_by_certificate($customcert->id);
        $this->assertCount(2, $list);
    }

    /**
     * Ensures delete_by_certificate removes all issues for a certificate.
     *
     * @covers \mod_customcert\service\issue_repository::delete_by_certificate
     */
    public function test_issue_repository_delete_by_certificate(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $repo = new issue_repository();
        $repo->create($customcert->id, $student->id);
        $this->assertEquals(1, $DB->count_records('customcert_issues', ['customcertid' => $customcert->id]));
        $repo->delete_by_certificate($customcert->id);
        $this->assertEquals(0, $DB->count_records('customcert_issues', ['customcertid' => $customcert->id]));
    }

    /**
     * Ensures delete_by_course removes all issues for all certificates in a course.
     *
     * @covers \mod_customcert\service\issue_repository::delete_by_course
     */
    public function test_issue_repository_delete_by_course(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $cert1 = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $cert2 = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $repo = new issue_repository();
        $repo->create($cert1->id, $student->id);
        $repo->create($cert2->id, $student->id);
        $this->assertEquals(2, $DB->count_records('customcert_issues'));
        $repo->delete_by_course($course->id);
        $this->assertEquals(0, $DB->count_records('customcert_issues'));
    }

    /**
     * Ensures template_repository::get_by_id returns null when not found and the record when found.
     *
     * @covers \mod_customcert\service\template_repository::get_by_id
     */
    public function test_template_repository_get_by_id(): void {
        $repo = new template_repository();
        $this->assertNull($repo->get_by_id(999999));
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $record = $repo->get_by_id((int)$customcert->templateid);
        $this->assertNotNull($record);
        $this->assertEquals($customcert->templateid, (int)$record->id);
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

    /**
     * delete_for_certificate deletes the issue when it belongs to the certificate.
     *
     * @covers \mod_customcert\service\issue_repository::delete_for_certificate
     */
    public function test_delete_for_certificate_deletes_matching_issue(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $repo = new issue_repository();
        $issueid = $repo->create((int)$customcert->id, (int)$user->id);
        $repo->delete_for_certificate($issueid, (int)$customcert->id);
        $this->assertFalse($DB->record_exists('customcert_issues', ['id' => $issueid]));
    }

    /**
     * delete_for_certificate throws an exception when the issue does not belong to the certificate.
     *
     * @covers \mod_customcert\service\issue_repository::delete_for_certificate
     */
    public function test_delete_for_certificate_throws_for_wrong_certificate(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $other = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $repo = new issue_repository();
        $issueid = $repo->create((int)$customcert->id, (int)$user->id);
        $this->expectException(\moodle_exception::class);
        $repo->delete_for_certificate($issueid, (int)$other->id);
    }

    /**
     * get_issues returns issued users for a certificate.
     *
     * @covers \mod_customcert\service\issue_repository::get_issues
     */
    public function test_get_issues_returns_issued_users(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $repo = new issue_repository();
        $repo->create((int)$customcert->id, (int)$user1->id);
        $repo->create((int)$customcert->id, (int)$user2->id);
        $issues = $repo->get_issues((int)$customcert->id, $cm, 0, 0);
        $this->assertCount(2, $issues);
    }

    /**
     * get_number_of_issues returns the correct count.
     *
     * @covers \mod_customcert\service\issue_repository::get_number_of_issues
     */
    public function test_get_number_of_issues_returns_correct_count(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id);
        $user = $this->getDataGenerator()->create_user();
        $repo = new issue_repository();
        $this->assertSame(0, $repo->get_number_of_issues((int)$customcert->id, $cm));
        $repo->create((int)$customcert->id, (int)$user->id);
        $this->assertSame(1, $repo->get_number_of_issues((int)$customcert->id, $cm));
    }

    /**
     * get_number_of_certificates_for_user returns the correct count.
     *
     * @covers \mod_customcert\service\certificate_repository::get_number_of_certificates_for_user
     */
    public function test_get_number_of_certificates_for_user(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $issuerepo = new issue_repository();
        $certrepo = new certificate_repository();
        $this->assertSame(0, $certrepo->get_number_of_certificates_for_user((int)$user->id));
        $issuerepo->create((int)$customcert->id, (int)$user->id);
        $this->assertSame(1, $certrepo->get_number_of_certificates_for_user((int)$user->id));
    }

    /**
     * get_certificates_for_user returns the certificates issued to a user.
     *
     * @covers \mod_customcert\service\certificate_repository::get_certificates_for_user
     */
    public function test_get_certificates_for_user(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $issuerepo = new issue_repository();
        $certrepo = new certificate_repository();
        $issuerepo->create((int)$customcert->id, (int)$user->id);
        $certs = $certrepo->get_certificates_for_user((int)$user->id, 0, 0);
        $this->assertCount(1, $certs);
        $cert = reset($certs);
        $this->assertEquals($customcert->id, $cert->id);
        $this->assertEquals($course->fullname, $cert->coursename);
    }
}
