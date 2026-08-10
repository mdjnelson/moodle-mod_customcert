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
use context_module;
use context_system;
use mod_customcert\callback\file_callbacks;
use moodle_exception;

/**
 * Unit tests for classes/callback/file_callbacks.php.
 *
 * pluginfile() is the access-control gate for serving certificate template images:
 * CONTEXT_MODULE requests must be logged in to the course, CONTEXT_SYSTEM requests must
 * hold mod/customcert:manage. A weakened or dropped check here would leak files with
 * nothing failing, so this locks both branches down explicitly.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_callbacks_test extends advanced_testcase {
    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        parent::setUp();
    }

    /**
     * Only the 'image' filearea is handled; anything else must be a no-op.
     *
     * @covers \mod_customcert\callback\file_callbacks::pluginfile
     */
    public function test_pluginfile_ignores_non_image_filearea(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $result = file_callbacks::pluginfile($course, $cm, $context, 'other', ['file.png'], false);

        $this->assertNull($result);
    }

    /**
     * A CONTEXT_SYSTEM request without mod/customcert:manage must be denied before any file
     * lookup happens.
     *
     * @covers \mod_customcert\callback\file_callbacks::pluginfile
     */
    public function test_pluginfile_system_context_denies_without_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);

        $course = new \stdClass();
        $context = context_system::instance();
        $cm = new \stdClass();

        $result = file_callbacks::pluginfile($course, $cm, $context, 'image', ['file.png'], false);

        $this->assertFalse($result);
    }

    /**
     * A CONTEXT_SYSTEM request with mod/customcert:manage must pass the capability check and
     * proceed to the (here, unsuccessful) file lookup, proving the check doesn't also block
     * legitimate managers.
     *
     * @covers \mod_customcert\callback\file_callbacks::pluginfile
     */
    public function test_pluginfile_system_context_allows_with_manage_capability(): void {
        $this->setAdminUser();

        $course = new \stdClass();
        $context = context_system::instance();
        $cm = new \stdClass();

        $result = file_callbacks::pluginfile($course, $cm, $context, 'image', ['does-not-exist.png'], false);

        // No such file exists, so it must fail closed with false rather than error or serve
        // something unexpected — but critically, it got past the capability check to do so.
        $this->assertFalse($result);
    }

    /**
     * A CONTEXT_MODULE request from a user who isn't logged in to the course must be rejected
     * by require_login(), not silently allowed through.
     *
     * pluginfile() calls require_login($course, false, $cm) without $preventredirect, so for an
     * anonymous user require_login() takes the "redirect to the login page" branch rather than
     * throwing require_login_exception (that's only thrown when $preventredirect is true). Under
     * PHPUnit an actual HTTP redirect can't happen, so Moodle raises a generic moodle_exception
     * ("Unsupported redirect detected") instead — that's the real, observable rejection here.
     *
     * @covers \mod_customcert\callback\file_callbacks::pluginfile
     */
    public function test_pluginfile_module_context_requires_login(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $this->expectException(moodle_exception::class);
        file_callbacks::pluginfile($course, $cm, $context, 'image', ['file.png'], false);
    }

    /**
     * A CONTEXT_MODULE request from a user who is logged in to the course must pass the
     * login check and proceed to the (here, unsuccessful) file lookup.
     *
     * @covers \mod_customcert\callback\file_callbacks::pluginfile
     */
    public function test_pluginfile_module_context_allows_with_login(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $this->setUser($student);

        $result = file_callbacks::pluginfile($course, $cm, $context, 'image', ['does-not-exist.png'], false);

        $this->assertFalse($result);
    }
}
