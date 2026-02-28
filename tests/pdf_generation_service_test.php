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

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\service\pdf_generation_service;
use mod_customcert\service\template_service;

/**
 * Tests for the PDF generation service.
 *
 * @package   mod_customcert
 * @category  test
 * @copyright 2026 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf_generation_service_test extends advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();

        parent::setUp();
    }

    /**
     * create_preview_pdf should return a configured PDF instance without debugging notices.
     *
     * @covers \mod_customcert\service\pdf_generation_service::create_preview_pdf
     */
    public function test_create_preview_pdf_service(): void {
        global $USER;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $template = template::load((int)$customcert->templateid);

        $service = new pdf_generation_service();
        $pdf = $service->create_preview_pdf($template, $USER);

        $this->assertInstanceOf(\pdf::class, $pdf);
        $this->assertDebuggingNotCalled();
    }

    /**
     * generate_pdf should produce a PDF string when returning output and avoid debugging.
     *
     * @covers \mod_customcert\service\pdf_generation_service::generate_pdf
     */
    public function test_generate_pdf_service_preview_returns_string(): void {
        global $DB, $USER;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $template = template::load((int)$customcert->templateid);
        $service = new pdf_generation_service();

        // Ensure at least one element exists for rendering.
        $page = $DB->get_record('customcert_pages', ['templateid' => $template->get_id()], '*', MUST_EXIST);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $page->id,
            'element' => 'text',
            'name' => 'Sample',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'data' => '',
        ]);

        $pdfstring = $service->generate_pdf($template, true, (int)$USER->id, true);

        $this->assertIsString($pdfstring);
        $this->assertNotEmpty($pdfstring);
        $this->assertDebuggingNotCalled();
    }

    /**
     * System templates without a backing customcert record should still generate PDFs and safe filenames.
     *
     * @covers \mod_customcert\service\pdf_generation_service::generate_pdf
     * @covers \mod_customcert\service\pdf_generation_service::compute_filename_for_user
     */
    public function test_generate_pdf_system_template_without_customcert(): void {
        global $DB, $USER, $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $this->setAdminUser();

        $template = template::create('System/Template Name', \context_system::instance()->id);
        $templateservice = template_service::create();
        $pageid = $templateservice->add_page($template);

        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'text',
            'name' => 'Sample',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'data' => '',
        ]);

        $service = new pdf_generation_service();
        $pdfstring = $service->generate_pdf($template, true, (int)$USER->id, true);

        $this->assertIsString($pdfstring);
        $this->assertNotEmpty($pdfstring);
        $filename = $service->compute_filename_for_user($template, $USER, null);

        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertStringContainsString('System', $filename);
        $this->assertStringContainsString('Template', $filename);
        $this->assertStringNotContainsString('/', $filename);
        $this->assertDebuggingNotCalled();
    }

    /**
     * compute_filename_for_user should honour custom filename patterns without debugging.
     *
     * @covers \mod_customcert\service\pdf_generation_service::compute_filename_for_user
     */
    public function test_compute_filename_for_user_service(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course([
            'shortname' => 'COURSE101',
            'fullname' => 'Course 101',
        ]);

        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'usecustomfilename' => 1,
            'customfilenamepattern' => '{FIRST_NAME}-{LAST_NAME}-{COURSE_SHORT_NAME}-{ISSUE_DATE}-{GROUP_NAME}',
        ]);

        $template = template::load((int)$customcert->templateid);
        $service = new pdf_generation_service();

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
        ]);

        // Enrol the user so group membership is returned by group APIs.
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Add a group and membership to exercise GROUP_NAME replacement.
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'GroupX']);
        groups_add_member($group->id, $user->id);

        // Seed an issue so ISSUE_DATE comes from the record.
        $issuedate = 1700000000;
        $DB->insert_record('customcert_issues', (object) [
            'customcertid' => $customcert->id,
            'userid' => $user->id,
            'code' => 'CODE123',
            'timecreated' => $issuedate,
            'emailed' => 0,
        ]);

        $customcertrecord = $DB->get_record('customcert', ['id' => $customcert->id]);
        $filename = $service->compute_filename_for_user($template, $user, $customcertrecord);

        $expecteddate = date('Y-m-d', $issuedate);
        // Sanitisation uses hyphens from the pattern and replaces spaces with underscores.
        $this->assertStringStartsWith('Ada-Lovelace-COURSE101-' . $expecteddate . '-GroupX', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertDebuggingNotCalled();
    }

    /**
     * compute_filename_for_user should drop GROUP_NAME when no course/group data is available.
     *
     * @covers \mod_customcert\service\pdf_generation_service::compute_filename_for_user
     */
    public function test_compute_filename_for_user_without_group_data(): void {
        $this->setAdminUser();

        $template = template::create('No Group', \context_system::instance()->id);
        $service = new pdf_generation_service();

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'NoGroup',
            'lastname' => 'User',
        ]);

        $customcert = (object) [
            'id' => 123,
            'course' => 0,
            'usecustomfilename' => 1,
            'customfilenamepattern' => '{FIRST_NAME}-{GROUP_NAME}-{ISSUE_DATE}',
        ];

        $filename = $service->compute_filename_for_user($template, $user, $customcert);

        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertStringNotContainsString('{GROUP_NAME}', $filename);
        $this->assertStringContainsString('NoGroup--', $filename);
        $this->assertDebuggingNotCalled();
    }
}
