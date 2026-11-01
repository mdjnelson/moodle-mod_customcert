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
 * Unit tests for recipient eligibility in the issue certificates scheduled task.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\task\issue_certificates_task;
use stdClass;

/**
 * Unit tests for recipient eligibility in the issue certificates scheduled task.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\task\issue_certificates_task
 */
final class issue_certificates_task_test extends advanced_testcase {
    /**
     * Test set up.
     */
    #[\Override]
    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        set_config('certificateexecutionperiod', 0, 'customcert');
        set_config('certificatesperrun', 100, 'customcert');
        set_config('certificate_offset', 0, 'customcert');
        set_config('useadhoc', 0, 'customcert');
    }

    /**
     * Create a certificate with one element in the given course.
     *
     * @param int $courseid
     * @param array $options Extra module options.
     * @return stdClass
     */
    private function create_certificate(int $courseid, array $options = []): stdClass {
        global $DB;

        $customcert = $this->getDataGenerator()->create_module(
            'customcert',
            ['course' => $courseid, 'emailstudents' => 1] + $options
        );
        $pageid = $DB->insert_record('customcert_pages', (object)[
            'templateid' => $customcert->templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('customcert_elements', (object)['pageid' => $pageid, 'name' => 'E']);

        return $customcert;
    }

    /**
     * Create a role granting receiveissue and view, assigned to the user at system level.
     *
     * @param int $userid
     */
    private function assign_system_receiveissue_role(int $userid): void {
        $roleid = $this->getDataGenerator()->create_role();
        $syscontext = \context_system::instance();
        assign_capability('mod/customcert:receiveissue', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('mod/customcert:view', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $userid, $syscontext->id);
    }

    /**
     * Run the task and return the emails sent.
     *
     * @return array
     */
    private function run_task(): array {
        $sink = $this->redirectEmails();
        (new issue_certificates_task())->execute();
        $emails = $sink->get_messages();
        $sink->close();

        return $emails;
    }

    /**
     * Get the ids of users issued the certificate.
     *
     * @param int $customcertid
     * @return array
     */
    private function get_issued_userids(int $customcertid): array {
        global $DB;

        return array_map('intval', array_values(
            $DB->get_fieldset_select('customcert_issues', 'userid', 'customcertid = ?', [$customcertid])
        ));
    }

    /**
     * An enrolled student is issued the certificate.
     */
    public function test_enrolled_student_is_issued(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $customcert = $this->create_certificate($course->id);

        $emails = $this->run_task();

        $this->assertEquals([(int)$student->id], $this->get_issued_userids($customcert->id));
        $this->assertCount(1, $emails);
    }

    /**
     * A user with the capability at system level who is not enrolled is not issued the certificate (#773).
     */
    public function test_unenrolled_user_with_system_capability_is_not_issued(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $outsider = $this->getDataGenerator()->create_user();
        $this->assign_system_receiveissue_role($outsider->id);
        $customcert = $this->create_certificate($course->id);

        $emails = $this->run_task();

        $this->assertEquals([(int)$student->id], $this->get_issued_userids($customcert->id));
        $this->assertCount(1, $emails);
        $this->assertEquals($student->email, reset($emails)->to);
    }

    /**
     * An enrolled user whose capability comes only from a system-level role is still issued the certificate.
     */
    public function test_enrolled_user_with_system_capability_is_issued(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        unassign_capability('mod/customcert:receiveissue', $studentroleid);
        unassign_capability('mod/customcert:view', $studentroleid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->assign_system_receiveissue_role($student->id);
        $customcert = $this->create_certificate($course->id, [
            'availability' => json_encode([
                'op' => '&',
                'c' => [['type' => 'date', 'd' => '<', 't' => time() + DAYSECS]],
                'showc' => [false],
            ]),
        ]);

        $emails = $this->run_task();
        $this->assertEquals([(int)$student->id], $this->get_issued_userids($customcert->id));
        $this->assertCount(1, $emails);
        $this->assertEquals($student->email, reset($emails)->to);

        $emails = $this->run_task();
        $this->assertEquals([(int)$student->id], $this->get_issued_userids($customcert->id));
        $this->assertCount(0, $emails);
    }

    /**
     * A user who is no longer enrolled is not issued a new certificate (#773).
     */
    public function test_previously_enrolled_user_is_not_issued(): void {
        $course = $this->getDataGenerator()->create_course();
        $former = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->assign_system_receiveissue_role($former->id);
        $customcert = $this->create_certificate($course->id);

        $instances = enrol_get_instances($course->id, true);
        $instance = array_values(array_filter($instances, fn($i) => $i->enrol === 'manual'))[0];
        enrol_get_plugin('manual')->unenrol_user($instance, $former->id);

        $emails = $this->run_task();

        $this->assertSame([], $this->get_issued_userids($customcert->id));
        $this->assertCount(0, $emails);
    }

    /**
     * Users with a suspended enrolment or a suspended account are not issued the certificate.
     */
    public function test_suspended_users_are_not_issued(): void {
        $course = $this->getDataGenerator()->create_course();
        $active = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $suspendedenrolment = $this->getDataGenerator()->create_and_enrol(
            $course,
            'student',
            null,
            'manual',
            0,
            0,
            ENROL_USER_SUSPENDED
        );
        $suspendedaccount = $this->getDataGenerator()->create_and_enrol($course, 'student', ['suspended' => 1]);
        $customcert = $this->create_certificate($course->id);

        $this->run_task();

        $this->assertEquals([(int)$active->id], $this->get_issued_userids($customcert->id));
        $this->assertNotContains((int)$suspendedenrolment->id, $this->get_issued_userids($customcert->id));
        $this->assertNotContains((int)$suspendedaccount->id, $this->get_issued_userids($customcert->id));
    }

    /**
     * A teacher also enrolled as a student remains eligible, while a teacher alone is not issued.
     */
    public function test_teacher_also_enrolled_as_student_is_issued(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacheronly = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $both = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        role_assign($studentroleid, $both->id, \context_course::instance($course->id)->id);
        $customcert = $this->create_certificate($course->id);

        $this->run_task();

        $this->assertEquals([(int)$both->id], $this->get_issued_userids($customcert->id));
        $this->assertNotContains((int)$teacheronly->id, $this->get_issued_userids($customcert->id));
    }

    /**
     * Certificates on the site home are issued to users holding the capability without course enrolment.
     */
    public function test_site_home_certificate_is_issued(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assign_system_receiveissue_role($user->id);
        $other = $this->getDataGenerator()->create_user();
        $customcert = $this->create_certificate(SITEID);

        $emails = $this->run_task();

        $this->assertEquals([(int)$user->id], $this->get_issued_userids($customcert->id));
        $this->assertNotContains((int)$other->id, $this->get_issued_userids($customcert->id));
        $this->assertCount(1, $emails);
    }

    /**
     * Repeated runs do not create duplicate issues or send duplicate emails.
     */
    public function test_repeated_runs_do_not_duplicate(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $customcert = $this->create_certificate($course->id);

        $first = $this->run_task();
        $second = $this->run_task();
        $third = $this->run_task();

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertCount(0, $third);
        $this->assertEquals(1, $DB->count_records('customcert_issues', ['customcertid' => $customcert->id]));
        $this->assertEquals([(int)$student->id], $this->get_issued_userids($customcert->id));
    }

    /**
     * Access restrictions still apply to enrolled users.
     */
    public function test_availability_restriction_is_respected(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $restricted = $this->create_certificate($course->id, [
            'availability' => json_encode([
                'op' => '&',
                'c' => [['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS]],
                'showc' => [false],
            ]),
        ]);
        $open = $this->create_certificate($course->id);

        $this->run_task();

        $this->assertSame([], $this->get_issued_userids($restricted->id));
        $this->assertEquals([(int)$student->id], $this->get_issued_userids($open->id));
    }
}
