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
 * Tests for mod_customcert\completion_hook_callbacks.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use advanced_testcase;
use completion_info;
use core_completion_external;
use core_external\external_api;
use mod_customcert\event\issue_created;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class for unit testing the completion override to certificate issuance behaviour.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class completion_hook_callbacks_test extends advanced_testcase {
    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Override completion for a user through the real completion API, as a teacher would via the
     * activity completion report.
     *
     * @param object $teacher
     * @param object $student
     * @param int $cmid
     * @param int $newstate
     * @return void
     */
    private function override_completion(object $teacher, object $student, int $cmid, int $newstate): void {
        $this->setUser($teacher);
        $result = core_completion_external::override_activity_completion_status($student->id, $cmid, $newstate);
        external_api::clean_returnvalue(core_completion_external::override_activity_completion_status_returns(), $result);
    }

    /**
     * Set up a course with a customcert activity, and a teacher and student enrolled in it.
     *
     * @param array $customcertoptions Options passed to create_module() for the customcert instance.
     * @param int $completiontracking COMPLETION_TRACKING_MANUAL or COMPLETION_TRACKING_AUTOMATIC.
     * @return array [course, customcert, teacher, student]
     */
    private function create_course_with_customcert(
        array $customcertoptions = [],
        int $completiontracking = COMPLETION_TRACKING_MANUAL
    ): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $customcert = $this->getDataGenerator()->create_module(
            'customcert',
            array_merge(['course' => $course->id], $customcertoptions),
            ['completion' => $completiontracking]
        );

        return [$course, $customcert, $teacher, $student];
    }

    /**
     * Manually overriding a student's completion to complete issues them a certificate that is
     * visible through the normal report data path.
     *
     * @covers \mod_customcert\completion_hook_callbacks::after_cm_completion_updated
     */
    public function test_manual_override_to_complete_issues_certificate(): void {
        global $DB;

        [, $customcert, $teacher, $student] = $this->create_course_with_customcert();

        $this->assertFalse($DB->record_exists(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        ));

        $this->override_completion($teacher, $student, (int)$customcert->cmid, COMPLETION_COMPLETE);

        $issue = $DB->get_record(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        );
        $this->assertNotEmpty($issue);
        $this->assertSame((int)$student->id, (int)$issue->userid);

        $cm = get_coursemodule_from_id('customcert', $customcert->cmid);
        $groupmode = groups_get_activity_groupmode($cm);
        $report = certificate::get_issues((int)$customcert->id, $groupmode, $cm, 0, 0);
        $this->assertArrayHasKey($student->id, $report);
    }

    /**
     * The issue is created for the student whose completion was overridden, not the teacher who
     * performed the override.
     *
     * @covers \mod_customcert\completion_hook_callbacks::after_cm_completion_updated
     */
    public function test_issue_created_for_target_student_not_actor(): void {
        global $DB;

        [, $customcert, $teacher, $student] = $this->create_course_with_customcert();

        $this->override_completion($teacher, $student, (int)$customcert->cmid, COMPLETION_COMPLETE);

        $this->assertTrue($DB->record_exists(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        ));
        $this->assertFalse($DB->record_exists(
            'customcert_issues',
            ['userid' => $teacher->id, 'customcertid' => $customcert->id]
        ));
    }

    /**
     * Repeated qualifying completion overrides must never create a duplicate issue or duplicate
     * issue_created event.
     *
     * @covers \mod_customcert\completion_hook_callbacks::after_cm_completion_updated
     */
    public function test_repeated_override_does_not_duplicate_issue(): void {
        global $DB;

        [, $customcert, $teacher, $student] = $this->create_course_with_customcert();

        $this->override_completion($teacher, $student, (int)$customcert->cmid, COMPLETION_COMPLETE);
        $firstissue = $DB->get_record(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        );

        // Toggle incomplete then complete again to fire another qualifying completion override; the
        // issue already exists, so this must not trigger a second issue_created event.
        $this->override_completion($teacher, $student, (int)$customcert->cmid, COMPLETION_INCOMPLETE);

        $sink = $this->redirectEvents();
        $this->override_completion($teacher, $student, (int)$customcert->cmid, COMPLETION_COMPLETE);
        $events = $sink->get_events();
        $sink->close();

        $issuecreatedevents = array_filter($events, static fn($event) => $event instanceof issue_created);
        $this->assertCount(0, $issuecreatedevents);

        $secondissue = $DB->get_record(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        );
        $this->assertSame((int)$firstissue->id, (int)$secondissue->id);

        $allissues = $DB->get_records(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        );
        $this->assertCount(1, $allissues);
    }

    /**
     * Overriding completion to incomplete must not issue a certificate.
     *
     * @covers \mod_customcert\completion_hook_callbacks::after_cm_completion_updated
     */
    public function test_override_to_incomplete_does_not_issue_certificate(): void {
        global $DB;

        [$course, $customcert, $teacher, $student] = $this->create_course_with_customcert();

        // Bring completion to a non-incomplete state first (as the student, no override) so the
        // subsequent override to incomplete actually changes the record and fires the hook.
        $cm = get_coursemodule_from_id('customcert', $customcert->cmid);
        $completion = new completion_info($course);
        $this->setUser($student);
        $completion->update_state($cm, COMPLETION_COMPLETE, $student->id);

        $this->assertFalse($DB->record_exists(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        ));

        $this->override_completion($teacher, $student, (int)$customcert->cmid, COMPLETION_INCOMPLETE);

        $this->assertFalse($DB->record_exists(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        ));
    }

    /**
     * An ordinary completion update with no override must not issue a certificate.
     *
     * @covers \mod_customcert\completion_hook_callbacks::after_cm_completion_updated
     */
    public function test_ordinary_completion_update_without_override_does_not_issue_certificate(): void {
        global $DB;

        [$course, $customcert, , $student] = $this->create_course_with_customcert();

        $cm = get_coursemodule_from_id('customcert', $customcert->cmid);
        $completion = new completion_info($course);

        $this->setUser($student);
        $completion->update_state($cm, COMPLETION_COMPLETE, $student->id);

        $this->assertFalse($DB->record_exists(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        ));
    }

    /**
     * Completion overrides for non-customcert course modules must be ignored.
     *
     * @covers \mod_customcert\completion_hook_callbacks::after_cm_completion_updated
     */
    public function test_completion_event_for_other_activity_type_is_ignored(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $data = $this->getDataGenerator()->create_module('data', ['course' => $course->id], ['completion' => 1]);

        $this->override_completion($teacher, $student, (int)$data->cmid, COMPLETION_COMPLETE);

        $this->assertSame(0, $DB->count_records('customcert_issues'));
    }

    /**
     * An automatic-completion Custom certificate can still be manually overridden to complete, and
     * that override must issue a certificate.
     *
     * @covers \mod_customcert\completion_hook_callbacks::after_cm_completion_updated
     */
    public function test_automatic_completion_activity_manually_overridden_issues_certificate(): void {
        global $DB;

        [, $customcert, $teacher, $student] = $this->create_course_with_customcert(
            [],
            COMPLETION_TRACKING_AUTOMATIC
        );

        $this->assertFalse($DB->record_exists(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        ));

        $this->override_completion($teacher, $student, (int)$customcert->cmid, COMPLETION_COMPLETE);

        $this->assertTrue($DB->record_exists(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        ));
    }

    /**
     * An existing certificate issue is left untouched and no duplicate is inserted when a
     * qualifying completion override fires again.
     *
     * @covers \mod_customcert\completion_hook_callbacks::after_cm_completion_updated
     */
    public function test_existing_issue_is_not_duplicated(): void {
        global $DB;

        [, $customcert, $teacher, $student] = $this->create_course_with_customcert();

        $existingissueid = certificate::issue_certificate((int)$customcert->id, (int)$student->id);

        $this->override_completion($teacher, $student, (int)$customcert->cmid, COMPLETION_COMPLETE);

        $all = $DB->get_records(
            'customcert_issues',
            ['userid' => $student->id, 'customcertid' => $customcert->id]
        );
        $this->assertCount(1, $all);
        $this->assertArrayHasKey($existingissueid, $all);
    }
}
