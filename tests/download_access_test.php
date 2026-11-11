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
use moodle_exception;
use stdClass;

/**
 * Tests for the group visibility checks on certificate downloads.
 *
 * Every outcome is asserted against both the shared check used by view.php and the mobile
 * route, which must apply the same policy.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\download_access
 */
final class download_access_test extends advanced_testcase {
    /**
     * Creates a course without enrolment welcome messages, which would initialise the page theme.
     *
     * @param array $options Course options.
     * @return stdClass
     */
    private function create_course(array $options = []): stdClass {
        set_config('sendcoursewelcomemessage', 0, 'enrol_manual');

        return $this->getDataGenerator()->create_course($options);
    }

    /**
     * Creates a course and a certificate activity.
     *
     * @param int $groupmode The activity group mode.
     * @param int $groupingid The activity grouping.
     * @param array $courseoptions Extra course options.
     * @return array The course, course module and certificate record.
     */
    private function create_activity(int $groupmode, int $groupingid = 0, array $courseoptions = []): array {
        global $DB;

        $course = $this->create_course($courseoptions);
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'groupmode' => $groupmode,
            'groupingid' => $groupingid,
        ]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id, false, MUST_EXIST);
        $certificate = $DB->get_record('customcert', ['id' => $customcert->id], '*', MUST_EXIST);

        return [$course, $cm, $certificate];
    }

    /**
     * Creates a user enrolled with the given role and added to the given groups.
     *
     * @param stdClass $course
     * @param string $role
     * @param stdClass[] $groups
     * @return stdClass
     */
    private function create_member(stdClass $course, string $role, array $groups = []): stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
        foreach ($groups as $group) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }

        return $user;
    }

    /**
     * Creates a group in the course.
     *
     * @param stdClass $course
     * @return stdClass
     */
    private function create_group(stdClass $course): stdClass {
        return $this->getDataGenerator()->create_group(['courseid' => $course->id]);
    }

    /**
     * Asserts the requester can or cannot download for the recipient, on both routes.
     *
     * @param bool $expected Whether access should be allowed.
     * @param stdClass $requester
     * @param stdClass $recipient
     * @param stdClass $cm
     * @param stdClass $certificate
     * @param bool[] $issuedstates Whether the recipient has an issue, for each request made on the mobile route.
     */
    private function assert_download_policy(
        bool $expected,
        stdClass $requester,
        stdClass $recipient,
        stdClass $cm,
        stdClass $certificate,
        array $issuedstates = [true, false]
    ): void {
        $this->setUser($requester);

        $this->assertSame($expected, download_access::can_download_for_user($cm, (int)$recipient->id));

        // The mobile route must agree whether or not the recipient has an issue.
        foreach ($issuedstates as $issued) {
            $allowed = true;
            try {
                mobile_access::require_access($cm, $certificate, (int)$recipient->id, $issued);
            } catch (moodle_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
                $allowed = false;
            }
            $this->assertSame($expected, $allowed);
        }
    }

    /**
     * A teacher cannot download for a student in another group, and can for a student in their own group.
     *
     * @covers ::can_download_for_user
     */
    public function test_separate_groups_limits_teacher_to_own_group(): void {
        $this->resetAfterTest();

        [$course, $cm, $certificate] = $this->create_activity(SEPARATEGROUPS);
        $groupa = $this->create_group($course);
        $groupb = $this->create_group($course);
        $teacher = $this->create_member($course, 'teacher', [$groupa]);
        $studenta = $this->create_member($course, 'student', [$groupa]);
        $studentb = $this->create_member($course, 'student', [$groupb]);

        $this->assert_download_policy(true, $teacher, $studenta, $cm, $certificate);
        $this->assert_download_policy(false, $teacher, $studentb, $cm, $certificate);
    }

    /**
     * Users with moodle/site:accessallgroups can download for any group.
     *
     * @covers ::can_download_for_user
     */
    public function test_accessallgroups_can_download_any_group(): void {
        $this->resetAfterTest();

        [$course, $cm, $certificate] = $this->create_activity(SEPARATEGROUPS);
        $groupa = $this->create_group($course);
        $groupb = $this->create_group($course);
        $teacher = $this->create_member($course, 'editingteacher');
        $studenta = $this->create_member($course, 'student', [$groupa]);
        $studentb = $this->create_member($course, 'student', [$groupb]);
        $ungrouped = $this->create_member($course, 'student');

        $this->assert_download_policy(true, $teacher, $studenta, $cm, $certificate);
        $this->assert_download_policy(true, $teacher, $studentb, $cm, $certificate);
        $this->assert_download_policy(true, $teacher, $ungrouped, $cm, $certificate);
    }

    /**
     * A teacher who belongs to no group is denied.
     *
     * @covers ::can_download_for_user
     */
    public function test_requester_without_group_is_denied(): void {
        $this->resetAfterTest();

        [$course, $cm, $certificate] = $this->create_activity(SEPARATEGROUPS);
        $groupa = $this->create_group($course);
        $teacher = $this->create_member($course, 'teacher');
        $studenta = $this->create_member($course, 'student', [$groupa]);
        $ungrouped = $this->create_member($course, 'student');

        $this->assert_download_policy(false, $teacher, $studenta, $cm, $certificate);
        $this->assert_download_policy(false, $teacher, $ungrouped, $cm, $certificate);
    }

    /**
     * A recipient who belongs to no group is not visible to a restricted teacher.
     *
     * @covers ::can_download_for_user
     */
    public function test_recipient_without_group_is_denied(): void {
        $this->resetAfterTest();

        [$course, $cm, $certificate] = $this->create_activity(SEPARATEGROUPS);
        $groupa = $this->create_group($course);
        $teacher = $this->create_member($course, 'teacher', [$groupa]);
        $ungrouped = $this->create_member($course, 'student');

        $this->assert_download_policy(false, $teacher, $ungrouped, $cm, $certificate);
    }

    /**
     * Sharing a course group outside the activity grouping does not grant access.
     *
     * @covers ::can_download_for_user
     */
    public function test_activity_grouping_is_respected(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $groupa = $this->create_group($course);
        $groupb = $this->create_group($course);
        $unrelated = $this->create_group($course);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $groupa->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $groupb->id]);

        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'groupmode' => SEPARATEGROUPS,
            'groupingid' => $grouping->id,
        ]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id, false, MUST_EXIST);
        $certificate = $DB->get_record('customcert', ['id' => $customcert->id], '*', MUST_EXIST);

        $teacher = $this->create_member($course, 'teacher', [$groupa, $unrelated]);
        $sameunrelated = $this->create_member($course, 'student', [$unrelated]);
        $studentb = $this->create_member($course, 'student', [$groupb, $unrelated]);
        $studenta = $this->create_member($course, 'student', [$groupa]);

        $this->assert_download_policy(false, $teacher, $sameunrelated, $cm, $certificate);
        $this->assert_download_policy(false, $teacher, $studentb, $cm, $certificate);
        $this->assert_download_policy(true, $teacher, $studenta, $cm, $certificate);
    }

    /**
     * Any shared group is enough when the requester or recipient belongs to several.
     *
     * @covers ::can_download_for_user
     */
    public function test_multiple_group_memberships(): void {
        $this->resetAfterTest();

        [$course, $cm, $certificate] = $this->create_activity(SEPARATEGROUPS);
        $groupa = $this->create_group($course);
        $groupb = $this->create_group($course);
        $groupc = $this->create_group($course);
        $teacher = $this->create_member($course, 'teacher', [$groupa, $groupb]);
        $studentb = $this->create_member($course, 'student', [$groupb]);
        $studentbc = $this->create_member($course, 'student', [$groupc, $groupb]);
        $studentc = $this->create_member($course, 'student', [$groupc]);

        $this->assert_download_policy(true, $teacher, $studentb, $cm, $certificate);
        $this->assert_download_policy(true, $teacher, $studentbc, $cm, $certificate);
        $this->assert_download_policy(false, $teacher, $studentc, $cm, $certificate);
    }

    /**
     * Visible groups follows the report, which lists only members of the viewer's own groups.
     *
     * @covers ::can_download_for_user
     */
    public function test_visible_groups_limits_teacher_to_own_group(): void {
        $this->resetAfterTest();

        [$course, $cm, $certificate] = $this->create_activity(VISIBLEGROUPS);
        $groupa = $this->create_group($course);
        $groupb = $this->create_group($course);
        $teacher = $this->create_member($course, 'teacher', [$groupa]);
        $editingteacher = $this->create_member($course, 'editingteacher');
        $studenta = $this->create_member($course, 'student', [$groupa]);
        $studentb = $this->create_member($course, 'student', [$groupb]);

        $this->assert_download_policy(true, $teacher, $studenta, $cm, $certificate);
        $this->assert_download_policy(false, $teacher, $studentb, $cm, $certificate);
        $this->assert_download_policy(true, $editingteacher, $studentb, $cm, $certificate);
    }

    /**
     * Without groups every report viewer keeps access.
     *
     * @covers ::can_download_for_user
     */
    public function test_no_groups_mode_allows_report_viewers(): void {
        $this->resetAfterTest();

        [$course, $cm, $certificate] = $this->create_activity(NOGROUPS);
        $groupa = $this->create_group($course);
        $groupb = $this->create_group($course);
        $teacher = $this->create_member($course, 'teacher', [$groupa]);
        $studentb = $this->create_member($course, 'student', [$groupb]);
        $ungrouped = $this->create_member($course, 'student');

        $this->assert_download_policy(true, $teacher, $studentb, $cm, $certificate);
        $this->assert_download_policy(true, $teacher, $ungrouped, $cm, $certificate);
    }

    /**
     * A group mode forced by the course applies even if the activity has none.
     *
     * @covers ::can_download_for_user
     */
    public function test_forced_course_group_mode_applies(): void {
        $this->resetAfterTest();

        [$course, $cm, $certificate] = $this->create_activity(NOGROUPS, 0, [
            'groupmode' => SEPARATEGROUPS,
            'groupmodeforce' => 1,
        ]);
        $groupa = $this->create_group($course);
        $groupb = $this->create_group($course);
        $teacher = $this->create_member($course, 'teacher', [$groupa]);
        $studentb = $this->create_member($course, 'student', [$groupb]);

        $this->assert_download_policy(false, $teacher, $studentb, $cm, $certificate);
    }

    /**
     * Users can always download their own certificate, even when outside every group.
     *
     * @covers ::can_download_for_user
     */
    public function test_own_certificate_is_always_allowed(): void {
        $this->resetAfterTest();

        [$course, $cm, $certificate] = $this->create_activity(SEPARATEGROUPS);
        $teacher = $this->create_member($course, 'teacher');
        $student = $this->create_member($course, 'student');

        $this->assert_download_policy(true, $teacher, $teacher, $cm, $certificate, [true]);
        $this->assert_download_policy(true, $student, $student, $cm, $certificate);
    }

    /**
     * A user without viewreport is still refused by the mobile route before any group check.
     *
     * @covers \mod_customcert\mobile_access::require_access
     */
    public function test_viewreport_is_still_required(): void {
        $this->resetAfterTest();

        [$course, $cm, $certificate] = $this->create_activity(SEPARATEGROUPS);
        $groupa = $this->create_group($course);
        $studentone = $this->create_member($course, 'student', [$groupa]);
        $studenttwo = $this->create_member($course, 'student', [$groupa]);
        $this->setUser($studentone);

        $this->expectException(\required_capability_exception::class);
        mobile_access::require_access($cm, $certificate, (int)$studenttwo->id, true);
    }

    /**
     * A denied request throws instead of allowing a PDF, with the same error whether or not an issue exists.
     *
     * @covers ::require_download_for_user
     */
    public function test_denied_request_throws_without_revealing_issue(): void {
        $this->resetAfterTest();

        [$course, $cm] = $this->create_activity(SEPARATEGROUPS);
        $groupa = $this->create_group($course);
        $groupb = $this->create_group($course);
        $teacher = $this->create_member($course, 'teacher', [$groupa]);
        $studentb = $this->create_member($course, 'student', [$groupb]);
        $this->setUser($teacher);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('nopermissions', 'error', get_string('download')));
        download_access::require_download_for_user($cm, (int)$studentb->id);
    }
}
