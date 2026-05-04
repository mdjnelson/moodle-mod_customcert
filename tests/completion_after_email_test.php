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

use advanced_testcase;
use completion_info;
use mod_customcert\service\certificate_email_service;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * End-to-end integration tests: activity completion is triggered when a certificate is emailed.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\service\certificate_email_service
 */
final class completion_after_email_test extends advanced_testcase {
    /**
     * Set up common fixtures.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        set_config('useadhoc', 0, 'customcert');
        set_config('certificateexecutionperiod', 0, 'customcert');
        parent::setUp();
    }

    /**
     * Helper: create a course with completion enabled, a customcert instance with completionemailed=1
     * and emailstudents=1, add a template page+element, enrol a student, and return all fixtures.
     *
     * @return array{course: \stdClass, cm: \cm_info, customcert: \stdClass, student: \stdClass}
     */
    private function create_fixtures(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course'             => $course->id,
            'emailstudents'      => 1,
            'completionemailed'  => 1,
            'completion'         => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        // Add a page and element so the certificate is non-empty (required for email dispatch).
        $template = template::from_record((new template_repository())->get_by_id_or_fail((int)$customcert->templateid));
        $templateservice = template_service::create();
        $pageid = $templateservice->add_page($template);
        $DB->insert_record('customcert_elements', (object)['pageid' => $pageid, 'name' => 'TestElement']);

        [$course, $cm] = get_course_and_cm_from_instance($customcert->id, 'customcert', $course->id);

        return ['course' => $course, 'cm' => $cm, 'customcert' => $customcert, 'student' => $student];
    }

    /**
     * After send_issue() the activity completion state must be COMPLETE for the student.
     *
     * @covers \mod_customcert\service\certificate_email_service::send_issue
     */
    public function test_completion_is_complete_after_send_issue(): void {
        global $DB;

        $fixtures = $this->create_fixtures();
        $cm       = $fixtures['cm'];
        $student  = $fixtures['student'];
        $customcert = $fixtures['customcert'];

        // Issue the certificate (emailed = 0 at this point).
        $issueservice = certificate_issue_service::create();
        $issueid = $issueservice->issue_certificate((int)$customcert->id, (int)$student->id);

        // Confirm not yet complete.
        $completioninfo = new completion_info($fixtures['course']);
        $data = $completioninfo->get_data($cm, false, (int)$student->id);
        $this->assertSame(COMPLETION_INCOMPLETE, (int)$data->completionstate);

        // Send the email (marks emailed=1 and triggers completion update).
        $sink = $this->redirectEmails();
        $emailservice = certificate_email_service::create();
        $emailservice->send_issue((int)$customcert->id, $issueid);
        $sink->close();

        // Issue must now be marked emailed in the DB.
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);
        $this->assertSame(1, (int)$issue->emailed);

        // Completion state must now be COMPLETE.
        $data = $completioninfo->get_data($cm, true, (int)$student->id);
        $this->assertSame(COMPLETION_COMPLETE, (int)$data->completionstate);
    }

    /**
     * When completionemailed = 0 on the instance, sending the email must NOT mark the activity complete.
     *
     * @covers \mod_customcert\service\certificate_email_service::send_issue
     */
    public function test_completion_not_triggered_when_rule_disabled(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        // Completionemailed = 0 — rule disabled.
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course'            => $course->id,
            'emailstudents'     => 1,
            'completionemailed' => 0,
            'completion'        => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        $template = template::from_record((new template_repository())->get_by_id_or_fail((int)$customcert->templateid));
        $templateservice = template_service::create();
        $pageid = $templateservice->add_page($template);
        $DB->insert_record('customcert_elements', (object)['pageid' => $pageid, 'name' => 'TestElement']);

        [$course, $cm] = get_course_and_cm_from_instance($customcert->id, 'customcert', $course->id);

        $issueservice = certificate_issue_service::create();
        $issueid = $issueservice->issue_certificate((int)$customcert->id, (int)$student->id);

        $sink = $this->redirectEmails();
        $emailservice = certificate_email_service::create();
        $emailservice->send_issue((int)$customcert->id, $issueid);
        $sink->close();

        // Issue is emailed but completion rule is off — state must remain incomplete.
        $completioninfo = new completion_info($course);
        $data = $completioninfo->get_data($cm, true, (int)$student->id);
        $this->assertSame(COMPLETION_INCOMPLETE, (int)$data->completionstate);
    }

    /**
     * Full run via process_email_issuance_run(): completion becomes COMPLETE after the run.
     *
     * @covers \mod_customcert\service\certificate_issuer_service::process_email_issuance_run
     */
    public function test_completion_complete_after_issuance_run(): void {
        global $DB;

        $fixtures   = $this->create_fixtures();
        $cm         = $fixtures['cm'];
        $student    = $fixtures['student'];
        $customcert = $fixtures['customcert'];

        // Before the run: no issue, no completion.
        $completioninfo = new completion_info($fixtures['course']);
        $data = $completioninfo->get_data($cm, false, (int)$student->id);
        $this->assertSame(COMPLETION_INCOMPLETE, (int)$data->completionstate);

        $sink = $this->redirectEmails();
        $issuer = \mod_customcert\service\certificate_issuer_service::create();
        $issuer->process_email_issuance_run();
        $sink->close();

        // After the run: issue exists, emailed = 1, completion = COMPLETE.
        $issue = $DB->get_record('customcert_issues', ['customcertid' => $customcert->id, 'userid' => $student->id]);
        $this->assertNotEmpty($issue);
        $this->assertSame(1, (int)$issue->emailed);

        $data = $completioninfo->get_data($cm, true, (int)$student->id);
        $this->assertSame(COMPLETION_COMPLETE, (int)$data->completionstate);
    }
}
