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
 * Unit tests for mod_customcert\export\element.
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
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\export\template_appendix_manager_interface;

/**
 * Tests for export\element.
 *
 * @group      mod_customcert
 * @covers     \mod_customcert\export\element
 */
final class export_element_test extends advanced_testcase {
    /** @var clock */
    private clock $clock;
    /** @var template_import_logger_interface */
    private template_import_logger_interface $logger;
    /** @var template_appendix_manager_interface */
    private template_appendix_manager_interface $filemng;
    /** @var element */
    private element $element;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->clock = $this->createMock(clock::class);
        $this->clock->method('time')->willReturn(1000000);
        $this->logger = $this->createMock(template_import_logger_interface::class);
        $this->filemng = $this->createMock(template_appendix_manager_interface::class);
        $this->element = new element($this->clock, $this->logger, $this->filemng);
    }

    /**
     * Test that importing an element without a name throws import_exception.
     */
    public function test_import_missing_name_throws(): void {
        global $DB;
        $pageid = $DB->insert_record('customcert_pages', [
            'templateid' => 0,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 10,
            'rightmargin' => 10,
            'sequence' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        $this->expectException(import_exception::class);
        $this->element->import($pageid, ['element' => 'text', 'data' => '']);
    }

    /**
     * Test that importing an element with an unknown subplugin logs a warning and skips.
     */
    public function test_import_unknown_subplugin_logs_warning_and_skips(): void {
        global $DB;
        $pageid = $DB->insert_record('customcert_pages', [
            'templateid' => 0,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 10,
            'rightmargin' => 10,
            'sequence' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        // The null exporter logs a warning and skips insertion — no broken record should be stored.
        $this->logger->expects($this->once())->method('warning');
        $before = $DB->count_records('customcert_elements');
        $this->element->import($pageid, [
            'name' => 'Test element',
            'element' => 'nonexistentplugin99',
            'data' => [],
            'posx' => 10,
            'posy' => 20,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
        ]);
        $after = $DB->count_records('customcert_elements');
        $this->assertSame($before, $after);
    }

    /**
     * Test get_elementids_from_page returns int-typed IDs.
     */
    public function test_get_elementids_from_page_returns_ints(): void {
        global $DB;
        $pageid = $DB->insert_record('customcert_pages', [
            'templateid' => 0,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 10,
            'rightmargin' => 10,
            'sequence' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        $DB->insert_record('customcert_elements', [
            'pageid' => $pageid,
            'name' => 'el1',
            'element' => 'text',
            'data' => '',
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        $ids = element::get_elementids_from_page($pageid);
        $this->assertNotEmpty($ids);
        foreach ($ids as $id) {
            $this->assertIsInt($id);
        }
    }
}
