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
use mod_customcert\callback\instance_callbacks;
use mod_customcert\event\issue_deleted;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\issue_repository;
use mod_customcert\service\template_repository;

/**
 * Unit tests for classes/callback/instance_callbacks.php.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class instance_callbacks_test extends advanced_testcase {
    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        parent::setUp();
    }

    /**
     * add_instance() must create a linked template with one page, and encode the
     * submitted protection checkboxes into the stored protection string.
     *
     * The generator itself calls add_instance() (matching Moodle's real module-creation
     * flow, which first creates the course_modules row before invoking it), so this test
     * asserts on add_instance()'s specific outcomes rather than re-implementing that
     * bootstrapping by hand.
     *
     * @covers \mod_customcert\callback\instance_callbacks::add_instance
     */
    public function test_add_instance_creates_linked_template_and_page(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'protection_print' => 1,
            'protection_modify' => 1,
        ]);

        $record = $DB->get_record('customcert', ['id' => $customcert->id], '*', MUST_EXIST);
        $this->assertNotEmpty($record->templateid);

        $template = (new template_repository())->get_by_id_or_fail((int)$record->templateid);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id, false, MUST_EXIST);
        $this->assertEquals(context_module::instance($cm->id)->id, $template->contextid);

        $pages = $DB->get_records('customcert_pages', ['templateid' => $template->id]);
        $this->assertCount(1, $pages);

        $this->assertSame('print, modify', $record->protection);
    }

    /**
     * update_instance() must persist changes and re-encode the protection checkboxes.
     *
     * @covers \mod_customcert\callback\instance_callbacks::update_instance
     */
    public function test_update_instance_updates_record_and_protection(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'name' => 'Original name',
        ]);

        $original = $DB->get_record('customcert', ['id' => $customcert->id], '*', MUST_EXIST);

        $data = (object) [
            'instance' => $customcert->id,
            'name' => 'Updated name',
            'protection_copy' => 1,
        ];

        $result = instance_callbacks::update_instance($data, null);
        $this->assertTrue($result);

        $updated = $DB->get_record('customcert', ['id' => $customcert->id], '*', MUST_EXIST);
        $this->assertSame('Updated name', $updated->name);
        $this->assertSame('copy', $updated->protection);
        $this->assertGreaterThanOrEqual($original->timemodified, $updated->timemodified);
    }

    /**
     * delete_instance() must remove the customcert, its issues and its template, and must
     * fire an issue_deleted event for every issue before deleting it — this is the ordering
     * a silent regression could break without any test noticing.
     *
     * @covers \mod_customcert\callback\instance_callbacks::delete_instance
     */
    public function test_delete_instance_removes_everything_and_fires_events(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $record = $DB->get_record('customcert', ['id' => $customcert->id], '*', MUST_EXIST);
        $templateid = (int)$record->templateid;

        $issueid = certificate_issue_service::create()->issue_certificate((int)$customcert->id, (int)$user->id);

        $sink = $this->redirectEvents();
        $result = instance_callbacks::delete_instance((int)$customcert->id);
        $events = array_filter($sink->get_events(), fn ($event) => $event instanceof issue_deleted);
        $sink->close();

        $this->assertTrue($result);

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($issueid, $event->objectid);
        $this->assertEquals($user->id, $event->relateduserid);

        $this->assertFalse($DB->record_exists('customcert', ['id' => $customcert->id]));
        $this->assertFalse($DB->record_exists('customcert_templates', ['id' => $templateid]));
        $this->assertEmpty((new issue_repository())->list_by_certificate((int)$customcert->id));
    }

    /**
     * delete_instance() must return false, not throw, for an id that doesn't exist.
     *
     * @covers \mod_customcert\callback\instance_callbacks::delete_instance
     */
    public function test_delete_instance_returns_false_for_unknown_id(): void {
        $this->assertFalse(instance_callbacks::delete_instance(-1));
    }
}
