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
 * Contains unit tests for backing up and restoring mod_customcert.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use backup;
use restore_controller;
use restore_dbops;
use restore_date_testcase;
use mod_customcert\service\certificate_issue_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Unit tests for backing up and restoring mod_customcert.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_restore_test extends restore_date_testcase {
    /**
     * completionemailed on the instance, and studentemailed on an issue, must both survive a
     * course backup and restore -- neither field was originally included in the backup structure.
     *
     * @covers \backup_customcert_activity_structure_step
     * @covers \restore_customcert_activity_structure_step
     */
    public function test_backup_restore_preserves_completionemailed_and_studentemailed(): void {
        global $DB;

        [$course, $customcert] = $this->create_course_and_module('customcert', [
            'emailstudents' => 1,
            'completionemailed' => 1,
        ]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $issueid = certificate_issue_service::create()->issue_certificate((int)$customcert->id, (int)$student->id);
        $DB->set_field('customcert_issues', 'emailed', 1, ['id' => $issueid]);
        $DB->set_field('customcert_issues', 'studentemailed', 1, ['id' => $issueid]);

        $newcourseid = $this->backup_and_restore($course);

        $newcustomcert = $DB->get_record('customcert', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals(1, (int)$newcustomcert->emailstudents);
        $this->assertEquals(1, (int)$newcustomcert->completionemailed);

        $newissue = $DB->get_record('customcert_issues', ['customcertid' => $newcustomcert->id], '*', MUST_EXIST);
        $this->assertEquals(1, (int)$newissue->emailed);
        $this->assertEquals(1, (int)$newissue->studentemailed);
    }

    /**
     * NULL studentemailed (legacy/unknown) must survive backup and restore as NULL, not be
     * coerced into 0.
     *
     * @covers \backup_customcert_activity_structure_step
     * @covers \restore_customcert_activity_structure_step
     */
    public function test_backup_restore_preserves_null_studentemailed(): void {
        global $DB;

        [$course, $customcert] = $this->create_course_and_module('customcert', [
            'emailstudents' => 1,
            'completionemailed' => 1,
        ]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $issueid = certificate_issue_service::create()->issue_certificate((int)$customcert->id, (int)$student->id);
        $DB->set_field('customcert_issues', 'emailed', 1, ['id' => $issueid]);
        $DB->set_field('customcert_issues', 'studentemailed', null, ['id' => $issueid]);

        $newcourseid = $this->backup_and_restore($course);

        $newcustomcert = $DB->get_record('customcert', ['course' => $newcourseid], '*', MUST_EXIST);
        $newissue = $DB->get_record('customcert_issues', ['customcertid' => $newcustomcert->id], '*', MUST_EXIST);
        $this->assertEquals(1, (int)$newissue->emailed);
        $this->assertNull($newissue->studentemailed);
    }

    /**
     * A backup predating studentemailed has no <studentemailed> element at all. Restoring it must
     * leave the issue NULL (legacy/unknown), not 0, and must not trigger a re-email. Simulated by
     * stripping the element from a real backup's activity XML before restoring.
     *
     * @covers \restore_customcert_activity_structure_step::process_customcert_issue
     */
    public function test_restore_from_backup_predating_studentemailed_treated_as_unknown(): void {
        global $DB, $USER, $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'emailstudents' => 1,
            'completionemailed' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $issueid = certificate_issue_service::create()->issue_certificate((int)$customcert->id, (int)$student->id);
        $DB->set_field('customcert_issues', 'emailed', 1, ['id' => $issueid]);
        $DB->set_field('customcert_issues', 'studentemailed', 1, ['id' => $issueid]);

        // Take a real backup and extract it so its XML can be edited before restoring.
        $backupid = 'custe-nostudentemailed-' . $course->id;
        $bc = new \backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(1);
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $backuppath = $CFG->tempdir . '/backup/' . $backupid;
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $backuppath);
        $bc->destroy();

        // Strip the <studentemailed>...</studentemailed> element from the activity XML, to
        // simulate a backup taken before this field existed.
        $activityxmls = glob($backuppath . '/activities/customcert_*/customcert.xml');
        $this->assertCount(1, $activityxmls);
        $xml = file_get_contents($activityxmls[0]);
        $stripped = preg_replace('#<studentemailed>.*?</studentemailed>#s', '', $xml, 1, $count);
        $this->assertEquals(1, $count, 'Expected exactly one studentemailed element to strip from the fixture.');
        $this->assertStringNotContainsString('studentemailed', $stripped);
        file_put_contents($activityxmls[0], $stripped);

        // Restore the edited backup into a new course.
        $categoryid = (int)$DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        $newcourseid = restore_dbops::create_new_course('No studentemailed restore', 'custe-nse', $categoryid);
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $newcustomcert = $DB->get_record('customcert', ['course' => $newcourseid], '*', MUST_EXIST);
        $newissue = $DB->get_record('customcert_issues', ['customcertid' => $newcustomcert->id], '*', MUST_EXIST);
        $this->assertEquals(1, (int)$newissue->emailed);
        $this->assertNull($newissue->studentemailed);

        // Must not be treated as retryable by a later cron run.
        set_config('useadhoc', 0, 'customcert');
        $sink = $this->redirectEmails();
        (new \mod_customcert\task\issue_certificates_task())->execute();
        $emails = $sink->get_messages();
        $sink->close();
        $this->assertCount(0, $emails);
        $this->assertNull($DB->get_field('customcert_issues', 'studentemailed', ['id' => $newissue->id]));
    }
}
