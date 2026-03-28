<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
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
 * Unit tests for mod_customcert\export\template.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use core\clock;
use mod_customcert\export\element;
use mod_customcert\export\import_exception;
use mod_customcert\export\page;
use mod_customcert\export\template as export_template;
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\export\template_appendix_manager_interface;

/**
 * Tests for export\template.
 *
 * @group      mod_customcert
 * @covers     \mod_customcert\export\template
 */
final class export_template_test extends advanced_testcase {
    /** @var clock */
    private clock $clock;
    /** @var export_template */
    private export_template $template;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->clock = $this->createMock(clock::class);
        $this->clock->method('time')->willReturn(1000000);
        $logger = $this->createMock(template_import_logger_interface::class);
        $filemng = $this->createMock(template_appendix_manager_interface::class);
        $element = new element($this->clock, $logger, $filemng);
        $page = new page($this->clock, $element);
        $this->template = new export_template($this->clock, $page);
    }

    /**
     * Test that importing a template without a name throws import_exception.
     */
    public function test_import_missing_name_throws(): void {
        $this->expectException(import_exception::class);
        $this->template->import(1, ['pages' => []]);
    }

    /**
     * Test that importing a valid template inserts a record.
     */
    public function test_import_inserts_template_record(): void {
        $this->preventResetByRollback();
        global $DB;
        $before = $DB->count_records('customcert_templates');
        $this->template->import(1, [
            'name' => 'Imported template',
            'pages' => [],
        ]);
        $after = $DB->count_records('customcert_templates');
        $this->assertSame($before + 1, $after);
        $record = $DB->get_record('customcert_templates', ['name' => 'Imported template']);
        $this->assertNotFalse($record);
        $this->assertSame(1, (int) $record->contextid);
        $this->assertSame(1000000, (int) $record->timecreated);
    }

    /**
     * Test that importing a template with pages inserts page records.
     */
    public function test_import_with_pages_inserts_page_records(): void {
        $this->preventResetByRollback();
        global $DB;
        $this->template->import(1, [
            'name' => 'Template with pages',
            'pages' => [
                [
                    'width' => 210,
                    'height' => 297,
                    'leftmargin' => 10,
                    'rightmargin' => 10,
                    'sequence' => 1,
                    'elements' => [],
                ],
            ],
        ]);
        $this->assertSame(1, $DB->count_records('customcert_pages'));
    }

    /**
     * Test that export returns template data with pages key.
     */
    public function test_export_returns_template_data(): void {
        global $DB;
        $tid = $DB->insert_record('customcert_templates', [
            'name' => 'My template',
            'contextid' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        $data = $this->template->export($tid);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertSame('My template', $data['name']);
        $this->assertSame([], $data['pages']);
    }

    /**
     * Test that export includes pages when they exist.
     */
    public function test_export_includes_pages(): void {
        global $DB;
        $tid = $DB->insert_record('customcert_templates', [
            'name' => 'Template with pages',
            'contextid' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        $DB->insert_record('customcert_pages', [
            'templateid' => $tid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 10,
            'rightmargin' => 10,
            'sequence' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        $data = $this->template->export($tid);
        $this->assertCount(1, $data['pages']);
        $this->assertArrayHasKey('width', $data['pages'][0]);
    }
}
