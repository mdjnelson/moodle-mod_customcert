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
use context_module;
use required_capability_exception;
use require_login_exception;

/**
 * Tests for the mobile certificate access checks.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\mobile_access
 */
final class mobile_access_test extends advanced_testcase {
    /**
     * Creates a course with an enrolled student and a certificate.
     *
     * @param array $moduleoptions Extra options for the customcert instance.
     * @return array The course, student, course module and certificate record.
     */
    private function create_scenario(array $moduleoptions = []): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id] + $moduleoptions);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id, false, MUST_EXIST);
        $certificate = $DB->get_record('customcert', ['id' => $customcert->id], '*', MUST_EXIST);

        return [$course, $student, $cm, $certificate];
    }

    /**
     * A student can access their own certificate for a visible activity.
     *
     * @covers ::require_access
     */
    public function test_student_can_access_own_certificate(): void {
        $this->resetAfterTest();

        [, $student, $cm, $certificate] = $this->create_scenario();
        $this->setUser($student);

        $this->assertTrue(mobile_access::require_access($cm, $certificate, (int)$student->id, false));
        $this->assertTrue(mobile_access::require_access($cm, $certificate, (int)$student->id, true));
    }

    /**
     * A student cannot use the endpoint to reach a hidden activity.
     *
     * @covers ::require_access
     */
    public function test_hidden_activity_is_denied(): void {
        $this->resetAfterTest();

        [, $student, $cm, $certificate] = $this->create_scenario(['visible' => 0]);
        $this->setUser($student);

        $this->expectException(require_login_exception::class);
        mobile_access::require_access($cm, $certificate, (int)$student->id, false);
    }

    /**
     * A student cannot use the endpoint to bypass restrict access conditions.
     *
     * @covers ::require_access
     */
    public function test_restricted_activity_is_denied(): void {
        $this->resetAfterTest();

        $availability = json_encode(\core_availability\tree::get_root_json([
            \availability_date\condition::get_json(\availability_date\condition::DIRECTION_FROM, time() + DAYSECS),
        ]));
        [, $student, $cm, $certificate] = $this->create_scenario(['availability' => $availability]);
        $this->setUser($student);

        $this->expectException(require_login_exception::class);
        mobile_access::require_access($cm, $certificate, (int)$student->id, false);
    }

    /**
     * A user who cannot receive issues cannot cause a certificate to be issued to themselves.
     *
     * @covers ::require_access
     */
    public function test_receiveissue_required_to_issue(): void {
        $this->resetAfterTest();

        [, $student, $cm, $certificate] = $this->create_scenario();
        $studentrole = $this->get_role_id('student');
        assign_capability(
            'mod/customcert:receiveissue',
            CAP_PROHIBIT,
            $studentrole,
            context_module::instance($cm->id)->id,
            true
        );
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        mobile_access::require_access($cm, $certificate, (int)$student->id, false);
    }

    /**
     * Downloading an existing issue does not require the receiveissue capability.
     *
     * @covers ::require_access
     */
    public function test_receiveissue_not_required_for_existing_issue(): void {
        $this->resetAfterTest();

        [, $student, $cm, $certificate] = $this->create_scenario();
        assign_capability(
            'mod/customcert:receiveissue',
            CAP_PROHIBIT,
            $this->get_role_id('student'),
            context_module::instance($cm->id)->id,
            true
        );
        $this->setUser($student);

        $this->assertTrue(mobile_access::require_access($cm, $certificate, (int)$student->id, true));
    }

    /**
     * Requesting another user's certificate requires the viewreport capability.
     *
     * @covers ::require_access
     */
    public function test_other_user_requires_viewreport(): void {
        $this->resetAfterTest();

        [$course, $student, $cm, $certificate] = $this->create_scenario();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        mobile_access::require_access($cm, $certificate, (int)$other->id, true);
    }

    /**
     * A teacher can access another user's issued certificate but nothing is generated without an issue.
     *
     * @covers ::require_access
     */
    public function test_teacher_can_access_other_user_issue(): void {
        $this->resetAfterTest();

        [$course, $student, $cm, $certificate] = $this->create_scenario();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->assertTrue(mobile_access::require_access($cm, $certificate, (int)$student->id, true));
        $this->assertFalse(mobile_access::require_access($cm, $certificate, (int)$student->id, false));
    }

    /**
     * A user who has not met the required time is turned away.
     *
     * @covers ::require_access
     */
    public function test_required_time_is_enforced(): void {
        global $DB;

        $this->resetAfterTest();

        [, $student, $cm, $certificate] = $this->create_scenario();
        $DB->set_field('customcert', 'requiredtime', 60, ['id' => $certificate->id]);
        $certificate->requiredtime = 60;
        $this->setUser($student);

        $this->assertFalse(mobile_access::require_access($cm, $certificate, (int)$student->id, false));
    }

    /**
     * Gets a role id by shortname.
     *
     * @param string $shortname
     * @return int
     */
    private function get_role_id(string $shortname): int {
        global $DB;

        return (int)$DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
