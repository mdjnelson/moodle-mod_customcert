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
use context_course;
use context_system;
use mod_customcert\service\template_service;

/**
 * Dedicated coverage for deprecated template shims to ensure debugging is emitted.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_deprecated_shims_test extends advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Deprecated add_page shim should emit debugging.
     *
     * @covers \mod_customcert\template::add_page
     */
    public function test_add_page_shim_emits_debugging(): void {
        $template = template::create('Shim', context_system::instance()->id);

        $template->add_page();

        $this->assertDebuggingCalled();
        $this->resetDebugging();
    }

    /**
     * Deprecated save shim should emit debugging.
     *
     * @covers \mod_customcert\template::save
     */
    public function test_save_shim_emits_debugging(): void {
        $template = template::create('Shim', context_system::instance()->id);

        $template->save((object) [
            'id' => $template->get_id(),
            'name' => 'Renamed',
        ]);

        $this->assertDebuggingCalled();
        $this->resetDebugging();
    }

    /**
     * Deprecated delete_page shim should emit debugging.
     *
     * @covers \mod_customcert\template::delete_page
     */
    public function test_delete_page_shim_emits_debugging(): void {
        $template = template::create('Shim', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);

        $template->delete_page($pageid);

        $this->assertDebuggingCalled();
        $this->resetDebugging();
    }

    /**
     * Deprecated delete_element shim should emit debugging.
     *
     * @covers \mod_customcert\template::delete_element
     */
    public function test_delete_element_shim_emits_debugging(): void {
        global $DB;

        $template = template::create('Shim', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Shim element',
            'element' => 'text',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $template->delete_element($elementid);

        $this->assertDebuggingCalled();
        $this->resetDebugging();
    }

    /**
     * Deprecated move_item shim should emit debugging.
     *
     * @covers \mod_customcert\template::move_item
     */
    public function test_move_item_shim_emits_debugging(): void {
        $template = template::create('Shim', context_system::instance()->id);
        $service = template_service::create();
        $page1 = $service->add_page($template);
        $service->add_page($template);

        $template->move_item('page', $page1, 'down');

        $this->assertDebuggingCalled();
        $this->resetDebugging();
    }

    /**
     * Deprecated delete shim should emit debugging and still remove the record.
     *
     * @covers \mod_customcert\template::delete
     */
    public function test_delete_shim_emits_debugging(): void {
        global $DB;

        $template = template::create('Shim', context_system::instance()->id);
        $service = template_service::create();
        $service->add_page($template);

        $template->delete();

        $this->assertDebuggingCalled();
        $this->resetDebugging();
        $this->assertFalse($DB->record_exists('customcert_templates', ['id' => $template->get_id()]));
    }

    /**
     * Deprecated generate_pdf shim should emit debugging while returning PDF contents.
     *
     * @covers \mod_customcert\template::generate_pdf
     */
    public function test_generate_pdf_shim_emits_debugging(): void {
        global $DB, $USER;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = template::load((int)$customcert->templateid);

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

        $pdfstring = $template->generate_pdf(true, (int)$USER->id, true);

        $this->assertIsString($pdfstring);
        $this->assertNotEmpty($pdfstring);
        $this->assertDebuggingCalled();
        $this->resetDebugging();
    }

    /**
     * Deprecated create_preview_pdf shim should emit debugging and return a PDF instance.
     *
     * @covers \mod_customcert\template::create_preview_pdf
     */
    public function test_create_preview_pdf_shim_emits_debugging(): void {
        global $USER;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = template::load((int)$customcert->templateid);

        $pdf = $template->create_preview_pdf($USER);

        $this->assertInstanceOf(\pdf::class, $pdf);
        $this->assertDebuggingCalled();
        $this->resetDebugging();
    }

    /**
     * Deprecated copy_to_template shim should emit debugging and copy pages/elements.
     *
     * @covers \mod_customcert\template::copy_to_template
     */
    public function test_copy_to_template_shim_emits_debugging(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $service = template_service::create();

        $sourcecustomcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $source = template::load((int)$sourcecustomcert->templateid);
        $pageid = $service->add_page($source);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'text',
            'name' => 'Sample',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'data' => '',
        ]);

        $target = template::create('Target', $context->id);

        $source->copy_to_template($target);

        $this->assertDebuggingCalled();
        $this->resetDebugging();

        $targetpages = $DB->get_records('customcert_pages', ['templateid' => $target->get_id()]);
        $this->assertGreaterThanOrEqual(1, count($targetpages));

        $elementcopied = false;
        foreach ($targetpages as $targetpage) {
            if ($DB->record_exists('customcert_elements', ['pageid' => $targetpage->id])) {
                $elementcopied = true;
                break;
            }
        }

        $this->assertTrue($elementcopied);
    }

    /**
     * Deprecated compute_filename_for_user shim should emit debugging.
     *
     * @covers \mod_customcert\template::compute_filename_for_user
     */
    public function test_compute_filename_for_user_shim_emits_debugging(): void {
        $template = template::create('Shim', context_system::instance()->id);

        $user = (object) ['id' => 5, 'firstname' => 'Shim', 'lastname' => 'User'];
        $customcert = (object) [
            'id' => 1,
            'course' => 0,
            'usecustomfilename' => 0,
            'customfilenamepattern' => '',
        ];

        $template->compute_filename_for_user($user, $customcert);

        $this->assertDebuggingCalled();
        $this->resetDebugging();
    }
}
