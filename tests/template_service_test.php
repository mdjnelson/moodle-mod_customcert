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
use mod_customcert\service\item_move_service;
use mod_customcert\service\template_service;

/**
 * Service-layer tests for template operations (non-deprecated API).
 *
 * @package   mod_customcert
 * @category  test
 * @copyright 2026 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_service_test extends advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();

        parent::setUp();
    }

    /**
     * Adding and deleting a page via the service should work without debugging.
     *
     * @covers \mod_customcert\service\template_service::add_page
     * @covers \mod_customcert\service\template_service::delete_page
     */
    public function test_add_and_delete_page_service(): void {
        global $DB;

        $template = template::create('Service test', \context_system::instance()->id);
        $service = template_service::create();

        $pageid = $service->add_page($template);
        $this->assertTrue($DB->record_exists('customcert_pages', ['id' => $pageid]));
        $this->assertDebuggingNotCalled();

        $service->delete_page($template, $pageid);
        $this->assertFalse($DB->record_exists('customcert_pages', ['id' => $pageid]));
        $this->assertDebuggingNotCalled();
    }

    /**
     * Adding multiple pages should assign increasing sequence values starting at 1.
     *
     * @covers \mod_customcert\service\template_service::add_page
     */
    public function test_add_page_sets_sequence(): void {
        global $DB;

        $template = template::create('Service sequence', \context_system::instance()->id);
        $service = template_service::create();

        $first = $service->add_page($template);
        $second = $service->add_page($template);

        $pages = $DB->get_records('customcert_pages', ['templateid' => $template->get_id()], 'sequence ASC');
        $this->assertCount(2, $pages);

        $sequences = array_map(static function ($page) {
            return (int)$page->sequence;
        }, $pages);

        $this->assertSame([1, 2], array_values($sequences));
        $ids = array_keys($pages);
        $this->assertSame([$first, $second], array_values($ids));
        $this->assertDebuggingNotCalled();
    }

    /**
     * Moving pages via the service should reorder sequences without debugging.
     *
     * @covers \mod_customcert\service\template_service::move_item
     */
    public function test_move_page_service(): void {
        global $DB;

        $template = template::create('Service move', \context_system::instance()->id);
        $service = template_service::create();

        $first = $service->add_page($template);
        $second = $service->add_page($template);

        // Move second page up.
        $service->move_item(
            $template,
            item_move_service::ITEM_PAGE,
            $second,
            item_move_service::DIRECTION_UP
        );

        $pages = $DB->get_records('customcert_pages', ['templateid' => $template->get_id()], 'sequence ASC');
        $pageids = array_keys($pages);

        $this->assertSame([$second, $first], $pageids);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Updating a template name via the service should fire template_updated only when changed.
     *
     * @covers \mod_customcert\service\template_service::update
     */
    public function test_update_template_service(): void {
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $template = template::create('Original', $coursecontext->id);
        $service = template_service::create();

        // Name changed → event fires.
        $sink = $this->redirectEvents();
        $service->update($template, (object)['name' => 'Renamed']);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertDebuggingNotCalled();

        // No change → no event.
        $sink = $this->redirectEvents();
        $service->update($template, (object)['name' => 'Renamed']);
        $this->assertCount(0, $sink->get_events());
        $this->assertDebuggingNotCalled();
    }

    /**
     * System-context renames should persist but not emit template_updated.
     *
     * @covers \mod_customcert\service\template_service::update
     */
    public function test_update_template_service_system_context_does_not_emit_event(): void {
        $template = template::create('System Original', \context_system::instance()->id);
        $service = template_service::create();

        $sink = $this->redirectEvents();
        $service->update($template, (object)['name' => 'System Renamed']);
        $events = $sink->get_events();

        $this->assertCount(0, $events);
        $this->assertSame('System Renamed', $template->get_name());
        $this->assertDebuggingNotCalled();
    }

    /**
     * Deleting a template via the service should remove pages/elements and fire expected events without debugging.
     *
     * @covers \mod_customcert\service\template_service::delete
     * @covers \mod_customcert\service\template_service::delete_page
     */
    public function test_delete_template_service(): void {
        global $DB;

        $template = template::create('Delete', \context_system::instance()->id);
        $service = template_service::create();

        $pageid = $service->add_page($template);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'E',
            'element' => 'text',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $sink = $this->redirectEvents();
        $service->delete($template);
        $events = $sink->get_events();

        // Expect at least page_deleted and template_deleted (element deletion may be emitted by element class).
        $this->assertGreaterThanOrEqual(2, count($events));
        $names = array_map(fn($event) => $event->eventname, $events);
        $this->assertContains('\\mod_customcert\\event\\page_deleted', $names);
        $this->assertContains('\\mod_customcert\\event\\template_deleted', $names);
        $this->assertFalse($DB->record_exists('customcert_templates', ['id' => $template->get_id()]));
        $this->assertFalse($DB->record_exists('customcert_pages', ['templateid' => $template->get_id()]));
        $this->assertFalse($DB->record_exists('customcert_elements', ['pageid' => $pageid]));
        $this->assertDebuggingNotCalled();
    }

    /**
     * Deleting an element via the service should resequence and fire template_updated without debugging.
     *
     * @covers \mod_customcert\service\template_service::delete_element
     */
    public function test_delete_element_service(): void {
        global $DB;

        $template = template::create('Element delete', \context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);

        $id1 = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'One',
            'element' => 'text',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $id2 = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Two',
            'element' => 'text',
            'sequence' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $sink = $this->redirectEvents();
        $service->delete_element($template, $id1);
        $events = $sink->get_events();

        // Always expect template_updated; element_deleted may be emitted by the element class implementation.
        $this->assertNotEmpty($events);
        $names = array_map(fn($event) => $event->eventname, $events);
        $this->assertContains('\\mod_customcert\\event\\template_updated', $names);
        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $id1]));

        // Remaining element resequenced to 1.
        $remaining = $DB->get_record('customcert_elements', ['id' => $id2], '*', MUST_EXIST);
        $this->assertEquals(1, $remaining->sequence);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Copying to another template via the service should duplicate pages/elements without debugging.
     *
     * @covers \mod_customcert\service\template_service::copy_to_template
     */
    public function test_copy_to_template_service(): void {
        global $DB;

        $source = template::create('Source', \context_system::instance()->id);
        $target = template::create('Target', \context_system::instance()->id);
        $service = template_service::create();

        $pageid = $service->add_page($source);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'E',
            'element' => 'text',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $sink = $this->redirectEvents();
        $service->copy_to_template($source, $target);
        $events = $sink->get_events();

        // At minimum we expect the page_created event for the target.
        $this->assertNotEmpty($events);
        $names = array_map(fn($event) => $event->eventname, $events);
        $this->assertContains('\\mod_customcert\\event\\page_created', $names);

        $targetpages = $DB->get_records('customcert_pages', ['templateid' => $target->get_id()]);
        $this->assertCount(1, $targetpages);
        $newpage = reset($targetpages);

        $elements = $DB->get_records('customcert_elements', ['pageid' => $newpage->id]);
        $this->assertCount(1, $elements);
        $this->assertDebuggingNotCalled();
    }


    /**
     * save_pages should throw when required page fields are missing from input data.
     *
     * @covers \mod_customcert\service\template_service::save_pages
     */
    public function test_save_pages_throws_on_missing_fields(): void {
        $template = template::create('Missing fields', \context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);

        $data = new \stdClass();
        // Intentionally omit height/margins to trigger validation.
        $data->{'pagewidth_' . $pageid} = 210;

        $this->expectException(\invalid_parameter_exception::class);
        $service->save_pages($template, $data, true);
    }

    /**
     * delete_page fires a debugging notice when a page contains an element of an unknown type.
     *
     * @covers \mod_customcert\service\template_service::delete_page
     */
    public function test_delete_page_with_unknown_element_type_logs_debugging(): void {
        global $DB;

        $template = template::create('Unknown element page delete', \context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);

        // Insert an element with an unregistered type directly into the DB.
        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'idontexist',
            'name' => 'Bad element',
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ], true);

        $service->delete_page($template, $pageid);

        $this->assertFalse($DB->record_exists('customcert_pages', ['id' => $pageid]));
        $this->assertFalse($DB->record_exists('customcert_elements', ['pageid' => $pageid]));
        $messages = array_column($this->getDebuggingMessages(), 'message');
        $this->resetDebugging();
        $this->assertContains(
            "Could not resolve element type 'idontexist' (id={$elementid}) during page delete; " .
            "deleting record directly without firing element_deleted event.",
            $messages
        );
    }

    /**
     * delete_element fires a debugging notice when the element type cannot be resolved.
     *
     * @covers \mod_customcert\service\template_service::delete_element
     */
    public function test_delete_element_with_unknown_type_logs_debugging(): void {
        global $DB;

        $template = template::create('Unknown element delete', \context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);

        // Insert an element with an unregistered type directly into the DB.
        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'idontexist',
            'name' => 'Bad element',
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $service->delete_element($template, $elementid);

        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $elementid]));
        $messages = array_column($this->getDebuggingMessages(), 'message');
        $this->resetDebugging();
        $this->assertContains(
            "Could not resolve element type 'idontexist' (id={$elementid}) during element delete; " .
            "deleting record directly without firing element_deleted event.",
            $messages
        );
    }
}
