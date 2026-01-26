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
        $templateservice = new template_service();
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

        $templateservice = new template_service();
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
}
