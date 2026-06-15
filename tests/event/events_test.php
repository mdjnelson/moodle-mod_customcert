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
 * Contains the event tests for the module customcert.
 *
 * @package   mod_customcert
 * @category  test
 * @copyright 2023 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\event;

use customcertelement_text\element as text_element;
use mod_customcert\event\element_created;
use mod_customcert\event\element_deleted;
use mod_customcert\event\element_updated;
use mod_customcert\event\page_created;
use mod_customcert\event\page_deleted;
use mod_customcert\event\page_updated;
use mod_customcert\event\template_created;
use mod_customcert\event\template_deleted;
use mod_customcert\event\template_updated;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_repository;
use mod_customcert\service\persistence_helper;
use mod_customcert\service\item_move_service;
use mod_customcert\service\template_service;
use mod_customcert\template;
use advanced_testcase;
use context_course;
use context_module;
use context_system;
use mod_customcert\event\issue_created;
use mod_customcert\event\issue_deleted;
use stdClass;

/**
 * Event behavior test coverage for customcert.
 *
 * @package   mod_customcert
 * @category  test
 */
final class events_test extends advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Tests the events are fired correctly when creating a template.
     *
     * @covers \mod_customcert\template::create
     */
    public function test_creating_a_template(): void {
        $sink = $this->redirectEvents();
        $template = template::create('Test name', context_system::instance()->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $event = reset($events);

        $this->assertInstanceOf(template_created::class, $event);
        $this->assertEquals($template->get_id(), $event->objectid);
        $this->assertEquals(context_system::instance()->id, $event->contextid);
    }

    /**
     * Tests the events are fired correctly when updating a template.
     *
     * @covers \mod_customcert\service\template_service::update
     */
    public function test_updating_a_template(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $template = template::create('Test name', $context->id);
        $service = template_service::create();
        $data = new stdClass();
        $data->id = $template->get_id();
        $data->name = 'Test name 2';

        $sink = $this->redirectEvents();
        $service->update($template, $data);
        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $event = reset($events);

        $this->assertInstanceOf(template_updated::class, $event);
        $this->assertEquals($template->get_id(), $event->objectid);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the events are fired correctly when updating a template with no
     * changes.
     *
     * @covers \mod_customcert\service\template_service::update
     */
    public function test_updating_a_template_no_change(): void {
        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();
        $data = new stdClass();
        $data->id = $template->get_id();
        $data->name = $template->get_name();

        // Trigger and capture the event using service API; expect no events when unchanged.
        $sink = $this->redirectEvents();
        $service->update($template, $data);
        $events = $sink->get_events();

        // Check that no events were triggered.
        $this->assertCount(0, $events);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the events are fired correctly when creating a page via the service.
     *
     * @covers \mod_customcert\service\template_service::add_page
     */
    public function test_creating_a_page_via_service(): void {
        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();

        $sink = $this->redirectEvents();
        $pageid = $service->add_page($template);
        $events = $sink->get_events();
        $this->assertCount(2, $events);

        $pagecreatedevent = array_shift($events);
        $templateupdateevent = array_shift($events);

        // Check that the event data is valid.
        $this->assertInstanceOf(page_created::class, $pagecreatedevent);
        $this->assertEquals($pageid, $pagecreatedevent->objectid);
        $this->assertEquals(context_system::instance()->id, $pagecreatedevent->contextid);

        $this->assertInstanceOf(template_updated::class, $templateupdateevent);
        $this->assertEquals($template->get_id(), $templateupdateevent->objectid);
        $this->assertEquals(context_system::instance()->id, $templateupdateevent->contextid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the events are fired correctly when moving an item via the service.
     *
     * @covers \mod_customcert\service\template_service::move_item
     */
    public function test_moving_item_via_service(): void {
        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();
        $page1id = $service->add_page($template);
        $service->add_page($template);

        $sink = $this->redirectEvents();
        $service->move_item($template, item_move_service::ITEM_PAGE, $page1id, item_move_service::DIRECTION_DOWN);
        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $event = reset($events);
        $this->assertInstanceOf(template_updated::class, $event);
        $this->assertEquals($template->get_id(), $event->objectid);
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the events are fired correctly when deleting a template.
     *
     * @covers \mod_customcert\service\template_service::delete
     */
    public function test_deleting_a_template(): void {
        global $DB;

        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();
        $page1id = $service->add_page($template);

        // Check the created objects exist in the database as we will check the
        // triggered events correspond to the deletion of these records.
        $templates = $DB->get_records('customcert_templates', ['id' => $template->get_id()]);
        $this->assertEquals(1, count($templates));
        $pages = $DB->get_records('customcert_pages', ['templateid' => $template->get_id()]);
        $this->assertEquals(1, count($pages));

        $sink = $this->redirectEvents();
        $service->delete($template);
        $events = $sink->get_events();
        $this->assertCount(2, $events);

        $event = array_shift($events);
        $this->assertInstanceOf(page_deleted::class, $event);
        $this->assertEquals($page1id, $event->objectid);
        $this->assertEquals(context_system::instance()->id, $event->contextid);

        $event = array_shift($events);
        $this->assertInstanceOf(template_deleted::class, $event);
        $this->assertEquals($template->get_id(), $event->objectid);
        $this->assertEquals(context_system::instance()->id, $event->contextid);

        // Check the above page_deleted and template_deleted events correspond
        // to actual deletions in the database.
        $templates = $DB->get_records('customcert_templates', ['id' => $template->get_id()]);
        $this->assertEquals(0, count($templates));
        $pages = $DB->get_records('customcert_pages', ['templateid' => $template->get_id()]);
        $this->assertEquals(0, count($pages));
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the events are fired correctly when deleting a page.
     *
     * @covers \mod_customcert\service\template_service::delete_page
     */
    public function test_deleting_a_page(): void {
        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();
        $page1id = $service->add_page($template);

        $sink = $this->redirectEvents();
        $service->delete_page($template, $page1id);
        $events = $sink->get_events();
        $this->assertCount(2, $events);

        $pagedeletedevent = array_shift($events);
        $templateupdatedevent = array_shift($events);

        // Check that the event data is valid.
        $this->assertInstanceOf(page_deleted::class, $pagedeletedevent);
        $this->assertEquals($page1id, $pagedeletedevent->objectid);
        $this->assertEquals(context_system::instance()->id, $pagedeletedevent->contextid);

        $this->assertInstanceOf(template_updated::class, $templateupdatedevent);
        $this->assertEquals($template->get_id(), $templateupdatedevent->objectid);
        $this->assertEquals(context_system::instance()->id, $templateupdatedevent->contextid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the events are fired correctly when saving a page.
     *
     * @covers \mod_customcert\service\template_service::save_pages
     */
    public function test_updating_a_page(): void {
        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);

        $width = 'pagewidth_' . $pageid;
        $height = 'pageheight_' . $pageid;
        $leftmargin = 'pageleftmargin_' . $pageid;
        $rightmargin = 'pagerightmargin_' . $pageid;

        $p = new stdClass();
        $p->tid = $template->get_id();
        $p->$width = 1;
        $p->$height = 1;
        $p->$leftmargin = 1;
        $p->$rightmargin = 1;

        $sink = $this->redirectEvents();
        $service->save_pages($template, $p);
        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $pageupdatedevent = array_shift($events);

        // Check that the event data is valid.
        $this->assertInstanceOf(page_updated::class, $pageupdatedevent);
        $this->assertEquals($pageid, $pageupdatedevent->objectid);
        $this->assertEquals(context_system::instance()->id, $pageupdatedevent->contextid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the element_created event is fired when inserting an element via element_repository.
     *
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_element_created_event_on_insert(): void {
        global $DB;

        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();
        $page1id = $service->add_page($template);

        $record = new stdClass();
        $record->pageid = $page1id;
        $record->name = 'A name';
        $record->element = 'text';
        $record->data = persistence_helper::to_object_json(['text' => 'Some text']);
        $record->sequence = 1;
        $record->timecreated = time();
        $record->timemodified = time();

        $factory = element_factory::build_with_defaults();

        $sink = $this->redirectEvents();
        $newid = $DB->insert_record('customcert_elements', $record);
        $record->id = $newid;
        $instance = $factory->create('text', $record);
        element_created::create_from_element($instance)->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf(element_created::class, $event);
        $this->assertEquals($newid, $event->objectid);
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the element_updated event is fired when updating an element via element_repository.
     *
     * @covers \mod_customcert\service\element_repository::update_name
     */
    public function test_element_updated_event_on_update(): void {
        global $DB;

        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();
        $page1id = $service->add_page($template);

        // Insert a text element directly.
        $record = new stdClass();
        $record->pageid = $page1id;
        $record->name = 'Original name';
        $record->element = 'text';
        $record->data = persistence_helper::to_object_json(['text' => 'Hello']);
        $record->sequence = 1;
        $record->timecreated = time();
        $record->timemodified = time();
        $elementid = $DB->insert_record('customcert_elements', $record);

        $factory = element_factory::build_with_defaults();
        $repo = new element_repository($factory);

        $sink = $this->redirectEvents();
        $repo->update_name($elementid, 'A new name', context_system::instance()->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf(element_updated::class, $event);
        $this->assertEquals($elementid, $event->objectid);
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the events are fired correctly when copying to a template.
     *
     * @covers \mod_customcert\service\template_service::copy_to_template
     */
    public function test_copy_to_template(): void {
        global $DB;

        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();
        $page1id = $service->add_page($template);

        // Add an element to the page.
        $element = new stdClass();
        $element->pageid = $page1id;
        $element->name = 'image';
        $element->element = 'image';
        $element->data = '';
        $element->id = $DB->insert_record('customcert_elements', $element);

        // Add another template.
        $template2 = template::create('Test name 2', context_system::instance()->id);

        $sink = $this->redirectEvents();
        $service->copy_to_template($template, $template2);
        $events = $sink->get_events();
        $this->assertCount(2, $events);

        $pagecreatedevent = array_shift($events);
        $elementcreatedevent = array_shift($events);

        // Check that the event data is valid.
        $this->assertInstanceOf(page_created::class, $pagecreatedevent);
        $this->assertEquals(context_system::instance()->id, $pagecreatedevent->contextid);
        $this->assertDebuggingNotCalled();

        $this->assertInstanceOf(element_created::class, $elementcreatedevent);
        $this->assertEquals(context_system::instance()->id, $elementcreatedevent->contextid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the events are fired correctly when loading a template into a
     * course-level certificate.
     *
     * @covers \mod_customcert\service\template_service::copy_to_template
     */
    public function test_load_template(): void {
        global $DB;

        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();
        $page1id = $service->add_page($template);

        // Add an element to the page.
        $element = new stdClass();
        $element->pageid = $page1id;
        $element->name = 'image';
        $element->element = 'image';
        $element->data = '';
        $element->id = $DB->insert_record('customcert_elements', $element);

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $contextid = context_module::instance($activity->cmid)->id;
        $template2 = template::create($activity->name, $contextid);

        $sink = $this->redirectEvents();
        $service->copy_to_template($template, $template2);
        $events = $sink->get_events();
        $this->assertCount(3, $events);

        $pagecreatedevent = array_shift($events);
        $elementcreatedevent = array_shift($events);
        $templateupdatedevent = array_shift($events);

        // Check that the event data is valid.
        $this->assertInstanceOf(page_created::class, $pagecreatedevent);
        $this->assertEquals($contextid, $pagecreatedevent->contextid);

        $this->assertInstanceOf(element_created::class, $elementcreatedevent);
        $this->assertEquals($contextid, $elementcreatedevent->contextid);
        $this->assertDebuggingNotCalled();

        $this->assertInstanceOf(template_updated::class, $templateupdatedevent);
        $this->assertEquals($contextid, $templateupdatedevent->contextid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests the events are fired correctly when deleting an element
     *
     * @covers \mod_customcert\service\template_service::delete_element
     */
    public function test_deleting_an_element(): void {
        global $DB;

        $template = template::create('Test name', context_system::instance()->id);
        $service = template_service::create();
        $page1id = $service->add_page($template);

        // Add an element to the page.
        $element = new stdClass();
        $element->pageid = $page1id;
        $element->name = 'image';
        $element->element = 'image';
        $element->data = '';
        $element->id = $DB->insert_record('customcert_elements', $element);

        $sink = $this->redirectEvents();
        $service->delete_element($template, $element->id);
        $events = $sink->get_events();
        $this->assertCount(2, $events);

        $elementdeletedevent = array_shift($events);
        $templateupdatedevent = array_shift($events);

        // Check that the event data is valid.
        $this->assertInstanceOf(element_deleted::class, $elementdeletedevent);
        $this->assertEquals($elementdeletedevent->objectid, $element->id);
        $this->assertEquals($elementdeletedevent->contextid, context_system::instance()->id);
        $this->assertDebuggingNotCalled();

        $this->assertInstanceOf(template_updated::class, $templateupdatedevent);
        $this->assertEquals($templateupdatedevent->objectid, $template->get_id());
        $this->assertEquals($templateupdatedevent->contextid, context_system::instance()->id);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests that the issue_created event is fired correctly.
     *
     * @covers \mod_customcert\service\certificate_issue_service::issue_certificate
     * @covers \mod_customcert\event\issue_created
     */
    public function test_issue_created_event(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $context = context_module::instance($customcert->cmid);

        $sink = $this->redirectEvents();

        // Call the actual function that creates an issue and triggers the event.
        $issueid = $this->issue_certificate((int)$customcert->id, (int)$user->id);

        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $event = reset($events);
        $this->assertInstanceOf(issue_created::class, $event);
        $this->assertEquals($issueid, $event->objectid);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals($user->id, $event->relateduserid);
    }

    /**
     * Tests that the issue_deleted event is fired correctly.
     * This simulates the deletion process that happens in view.php.
     *
     * @covers \mod_customcert\service\certificate_issue_service::issue_certificate
     * @covers \mod_customcert\event\issue_deleted
     */
    public function test_issue_deleted_event(): void {
        global $DB;

        $this->resetAfterTest();

        // Create course, teacher, student, and customcert module.
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'Test cert',
        ]);

        $cm = get_coursemodule_from_instance('customcert', $customcert->id);
        $context = context_module::instance($cm->id);

        // Create an issue using the natural method.
        $issueid = $this->issue_certificate((int)$customcert->id, (int)$student->id);

        // Get the issue record (as view.php does).
        $issue = $DB->get_record('customcert_issues', [
            'id' => $issueid,
            'customcertid' => $customcert->id,
        ], '*', MUST_EXIST);

        // Set the user to teacher for proper context.
        $this->setUser($teacher);

        // Capture events for the deletion.
        $sink = $this->redirectEvents();

        // Simulate the exact deletion process from view.php.
        $deleted = $DB->delete_records('customcert_issues', [
            'id' => $issueid,
            'customcertid' => $customcert->id,
        ]);

        if ($deleted) {
            $event = issue_deleted::create([
                'objectid'      => $issue->id,
                'context'       => $context,
                'relateduserid' => $issue->userid,
            ]);
            $event->trigger();
        }

        $events = $sink->get_events();
        $sink->close();

        // Assertions.
        $this->assertTrue($deleted);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(issue_deleted::class, $event);
        $this->assertEquals($issueid, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $this->assertEquals($context->id, $event->contextid);

        // Verify the issue was actually deleted from database.
        $issueexists = $DB->record_exists('customcert_issues', ['id' => $issueid]);
        $this->assertFalse($issueexists);
    }

    /**
     * Issue a certificate via the service for test setup.
     *
     * @param int $customcertid
     * @param int $userid
     * @return int
     */
    private function issue_certificate(int $customcertid, int $userid): int {
        $service = certificate_issue_service::create();

        return $service->issue_certificate($customcertid, $userid);
    }
}
