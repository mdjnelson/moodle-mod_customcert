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
use mod_customcert\callback\course_callbacks;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\issue_repository;

/**
 * Unit tests for classes/callback/course_callbacks.php.
 *
 * reset_userdata() bulk-deletes issued certificates on course reset. A scoping bug (e.g. an
 * incorrect join/filter) would silently delete issue history for the wrong course, which is
 * destructive and would only be discovered after the fact.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_callbacks_test extends advanced_testcase {
    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        parent::setUp();
    }

    /**
     * reset_userdata() must only delete issues for the course being reset, leaving issues in
     * other courses (even ones using the same element/template shape) untouched.
     *
     * @covers \mod_customcert\callback\course_callbacks::reset_userdata
     */
    public function test_reset_userdata_only_deletes_issues_for_the_given_course(): void {
        $this->setAdminUser();

        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();

        $certa = $this->getDataGenerator()->create_module('customcert', ['course' => $coursea->id]);
        $certb = $this->getDataGenerator()->create_module('customcert', ['course' => $courseb->id]);

        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();

        $issueservice = certificate_issue_service::create();
        $issuea = $issueservice->issue_certificate((int)$certa->id, (int)$usera->id);
        $issueb = $issueservice->issue_certificate((int)$certb->id, (int)$userb->id);

        $status = course_callbacks::reset_userdata((object) [
            'courseid' => $coursea->id,
            'reset_customcert' => 1,
        ]);

        $issuerepo = new issue_repository();
        $this->assertEmpty($issuerepo->list_by_certificate((int)$certa->id));
        // Get_records() keys the result by id, not sequential index.
        $this->assertArrayHasKey($issueb, $issuerepo->list_by_certificate((int)$certb->id));

        $this->assertCount(1, $status);
        $this->assertSame(false, $status[0]['error']);
    }

    /**
     * reset_userdata() must not touch any issues, and must return an empty status array, when
     * reset_customcert was not selected.
     *
     * @covers \mod_customcert\callback\course_callbacks::reset_userdata
     */
    public function test_reset_userdata_noop_when_not_selected(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        certificate_issue_service::create()->issue_certificate((int)$customcert->id, (int)$user->id);

        $status = course_callbacks::reset_userdata((object) ['courseid' => $course->id]);

        $this->assertSame([], $status);
        $this->assertCount(1, (new issue_repository())->list_by_certificate((int)$customcert->id));
    }
}
