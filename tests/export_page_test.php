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
 * Unit tests for mod_customcert\export\page.
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
use mod_customcert\export\page;
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\export\template_appendix_manager_interface;

/**
 * Tests for export\page.
 *
 * @group      mod_customcert
 * @covers     \mod_customcert\export\page
 */
final class export_page_test extends advanced_testcase {
    /** @var clock */
    private clock $clock;
    /** @var page */
    private page $page;
    /** @var int */
    private int $templateid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        global $DB;
        $this->clock = $this->createMock(clock::class);
        $this->clock->method('time')->willReturn(1000000);
        $logger = $this->createMock(template_import_logger_interface::class);
        $filemng = $this->createMock(template_appendix_manager_interface::class);
        $element = new element($this->clock, $logger, $filemng);
        $this->page = new page($this->clock, $element);
        $this->templateid = $DB->insert_record('customcert_templates', [
            'name' => 'Test template',
            'contextid' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
    }

    /**
     * Test that importing a page inserts a record and delegates element import.
     */
    public function test_import_inserts_page_record(): void {
        global $DB;
        $before = $DB->count_records('customcert_pages');
        $this->page->import($this->templateid, [
            'width' => 210,
            'height' => 297,
            'leftmargin' => 15,
            'rightmargin' => 15,
            'sequence' => 1,
            'elements' => [],
        ]);
        $after = $DB->count_records('customcert_pages');
        $this->assertSame($before + 1, $after);
        $record = $DB->get_record('customcert_pages', ['templateid' => $this->templateid]);
        $this->assertSame(210, (int) $record->width);
        $this->assertSame(297, (int) $record->height);
        $this->assertSame(1000000, (int) $record->timecreated);
    }

    /**
     * Test that importing a page with an unknown subplugin element skips the element record.
     */
    public function test_import_with_unknown_element_skips_record(): void {
        global $DB;
        $this->page->import($this->templateid, [
            'width' => 210,
            'height' => 297,
            'leftmargin' => 15,
            'rightmargin' => 15,
            'sequence' => 1,
            'elements' => [
                [
                    'name' => 'My element',
                    'element' => 'nonexistentplugin99',
                    'data' => [],
                    'posx' => 5,
                    'posy' => 5,
                    'refpoint' => 0,
                    'alignment' => 'L',
                    'sequence' => 1,
                ],
            ],
        ]);
        // Unknown subplugin elements are skipped, so no element record should be inserted.
        $this->assertSame(0, $DB->count_records('customcert_elements'));
    }

    /**
     * Test get_pageids_from_template returns int-typed IDs.
     */
    public function test_get_pageids_from_template_returns_ints(): void {
        global $DB;
        $DB->insert_record('customcert_pages', [
            'templateid' => $this->templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 10,
            'rightmargin' => 10,
            'sequence' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        $ids = page::get_pageids_from_template($this->templateid);
        $this->assertNotEmpty($ids);
        foreach ($ids as $id) {
            $this->assertIsInt($id);
        }
    }

    /**
     * Test export returns page data with elements key.
     */
    public function test_export_returns_page_data(): void {
        global $DB;
        $pageid = $DB->insert_record('customcert_pages', [
            'templateid' => $this->templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 10,
            'rightmargin' => 10,
            'sequence' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        $data = $this->page->export($pageid);
        $this->assertArrayHasKey('width', $data);
        $this->assertArrayHasKey('height', $data);
        $this->assertArrayHasKey('elements', $data);
        $this->assertSame([], $data['elements']);
    }
}
