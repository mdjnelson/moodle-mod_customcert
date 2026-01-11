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
        $service = new template_service();

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
        $service = new template_service();

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
        $service = new template_service();

        $first = $service->add_page($template);
        $second = $service->add_page($template);

        // Move second page up.
        $service->move_item(
            $template,
            template_service::ITEM_PAGE,
            $second,
            template_service::DIRECTION_UP
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
        $service = new template_service();

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
        $service = new template_service();

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
        $service = new template_service();

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
        $service = new template_service();
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
        $service = new template_service();

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
     * create_preview_pdf should return a configured PDF instance without debugging notices.
     *
     * @covers \mod_customcert\service\template_service::create_preview_pdf
     */
    public function test_create_preview_pdf_service(): void {
        global $DB, $USER;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $template = template::load((int)$customcert->templateid);

        $service = new template_service();
        $pdf = $service->create_preview_pdf($template, $USER);

        $this->assertInstanceOf(\pdf::class, $pdf);
        $this->assertDebuggingNotCalled();
    }

    /**
     * generate_pdf should produce a PDF string when returning output and avoid debugging.
     *
     * @covers \mod_customcert\service\template_service::generate_pdf
     */
    public function test_generate_pdf_service_preview_returns_string(): void {
        global $DB, $USER;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $template = template::load((int)$customcert->templateid);
        $service = new template_service();

        // Ensure at least one element exists for rendering.
        $page = $DB->get_record('customcert_pages', ['templateid' => $template->get_id()], '*', MUST_EXIST);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $page->id,
            'element' => 'text',
            'name' => 'Sample',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'data' => '',
        ]);

        $pdfstring = $service->generate_pdf($template, true, (int)$USER->id, true);

        $this->assertIsString($pdfstring);
        $this->assertNotEmpty($pdfstring);
        $this->assertDebuggingNotCalled();
    }

    /**
     * System templates without a backing customcert record should still generate PDFs and safe filenames.
     *
     * @covers \mod_customcert\service\template_service::generate_pdf
     * @covers \mod_customcert\service\template_service::compute_filename_for_user
     */
    public function test_generate_pdf_system_template_without_customcert(): void {
        global $DB, $USER, $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $this->setAdminUser();

        $template = template::create('System/Template Name', \context_system::instance()->id);
        $service = new template_service();

        $pageid = $service->add_page($template);

        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'text',
            'name' => 'Sample',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'data' => '',
        ]);

        $pdfstring = $service->generate_pdf($template, true, (int)$USER->id, true);

        $this->assertIsString($pdfstring);
        $this->assertNotEmpty($pdfstring);
        $filename = $service->compute_filename_for_user($template, $USER, null);

        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertStringContainsString('System', $filename);
        $this->assertStringContainsString('Template', $filename);
        $this->assertStringNotContainsString('/', $filename);
        $this->assertDebuggingNotCalled();
    }

    /**
     * compute_filename_for_user should honour custom filename patterns without debugging.
     *
     * @covers \mod_customcert\service\template_service::compute_filename_for_user
     */
    public function test_compute_filename_for_user_service(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course([
            'shortname' => 'COURSE101',
            'fullname' => 'Course 101',
        ]);

        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'usecustomfilename' => 1,
            'customfilenamepattern' => '{FIRST_NAME}-{LAST_NAME}-{COURSE_SHORT_NAME}-{ISSUE_DATE}-{GROUP_NAME}',
        ]);

        $template = template::load((int)$customcert->templateid);
        $service = new template_service();

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
        ]);

        // Enrol the user so group membership is returned by group APIs.
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Add a group and membership to exercise GROUP_NAME replacement.
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'GroupX']);
        groups_add_member($group->id, $user->id);

        // Seed an issue so ISSUE_DATE comes from the record.
        $issuedate = 1700000000;
        $DB->insert_record('customcert_issues', (object) [
            'customcertid' => $customcert->id,
            'userid' => $user->id,
            'code' => 'CODE123',
            'timecreated' => $issuedate,
            'emailed' => 0,
        ]);

        $customcertrecord = $DB->get_record('customcert', ['id' => $customcert->id]);
        $filename = $service->compute_filename_for_user($template, $user, $customcertrecord);

        $expecteddate = date('Y-m-d', $issuedate);
        // Sanitisation uses hyphens from the pattern and replaces spaces with underscores.
        $this->assertStringStartsWith('Ada-Lovelace-COURSE101-' . $expecteddate . '-GroupX', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertDebuggingNotCalled();
    }

    /**
     * compute_filename_for_user should drop GROUP_NAME when no course/group data is available.
     *
     * @covers \mod_customcert\service\template_service::compute_filename_for_user
     */
    public function test_compute_filename_for_user_without_group_data(): void {
        $this->setAdminUser();

        $template = template::create('No Group', \context_system::instance()->id);
        $service = new template_service();

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'NoGroup',
            'lastname' => 'User',
        ]);

        $customcert = (object) [
            'id' => 123,
            'course' => 0,
            'usecustomfilename' => 1,
            'customfilenamepattern' => '{FIRST_NAME}-{GROUP_NAME}-{ISSUE_DATE}',
        ];

        $filename = $service->compute_filename_for_user($template, $user, $customcert);

        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertStringNotContainsString('{GROUP_NAME}', $filename);
        $this->assertStringContainsString('NoGroup--', $filename);
        $this->assertDebuggingNotCalled();
    }

    /**
     * save_pages should throw when required page fields are missing from input data.
     *
     * @covers \mod_customcert\service\template_service::save_pages
     */
    public function test_save_pages_throws_on_missing_fields(): void {
        $template = template::create('Missing fields', \context_system::instance()->id);
        $service = new template_service();
        $pageid = $service->add_page($template);

        $data = new \stdClass();
        // Intentionally omit height/margins to trigger validation.
        $data->{'pagewidth_' . $pageid} = 210;

        $this->expectException(\invalid_parameter_exception::class);
        $service->save_pages($template, $data, true);
    }
}
