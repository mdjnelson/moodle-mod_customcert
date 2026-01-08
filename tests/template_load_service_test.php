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

declare(strict_types=1);

namespace mod_customcert;

use context_course;
use mod_customcert\event\template_updated;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_load_service;
use mod_customcert\service\template_repository;

/**
 * Tests for template_load_service behaviour.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\service\template_load_service
 */
final class template_load_service_test extends \advanced_testcase {
    /** @var template_repository */
    private template_repository $templates;

    /** @var page_repository */
    private page_repository $pages;

    /** @var template_load_service */
    private template_load_service $service;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->templates = new template_repository();
        $this->pages = new page_repository();
        $this->service = new template_load_service($this->templates, $this->pages);
    }

    /**
     * Ensures replace() overwrites target pages/elements and fires template_updated for module contexts.
     *
     * @covers ::replace
     */
    public function test_replace_overwrites_pages_and_elements(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        // Source template with two pages and elements.
        $sourceid = $this->templates->create((object) [
            'name' => 'Source',
            'contextid' => $context->id,
        ]);
        $srcpage1 = $this->pages->create((object) [
            'templateid' => $sourceid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);
        $srcpage2 = $this->pages->create((object) [
            'templateid' => $sourceid,
            'width' => 700,
            'height' => 500,
            'leftmargin' => 5,
            'rightmargin' => 5,
            'sequence' => 2,
        ]);

        $now = time();
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $srcpage1,
            'name' => 'Text1',
            'element' => 'text',
            'data' => json_encode(['value' => 'Hello']),
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $srcpage2,
            'name' => 'Text2',
            'element' => 'text',
            'data' => json_encode(['value' => 'World']),
            'posx' => 10,
            'posy' => 20,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Target template with one existing page/element that should be removed.
        $targetid = $this->templates->create((object) [
            'name' => 'Target',
            'contextid' => $context->id,
        ]);
        $targetpage = $this->pages->create((object) [
            'templateid' => $targetid,
            'width' => 600,
            'height' => 400,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $targetpage,
            'name' => 'Old',
            'element' => 'text',
            'data' => json_encode(['value' => 'Old']),
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $sink = $this->redirectEvents();
        $this->service->replace($targetid, $sourceid);
        $events = $sink->get_events();

        // Validate target now matches source structure (2 pages, elements copied).
        $pages = array_values($this->pages->list_by_template($targetid));
        $this->assertCount(2, $pages);
        $this->assertSame([1, 2], array_map(static fn($p) => (int) $p->sequence, $pages));

        $elementspage1 = $DB->get_records('customcert_elements', ['pageid' => $pages[0]->id], 'sequence ASC');
        $elementspage2 = $DB->get_records('customcert_elements', ['pageid' => $pages[1]->id], 'sequence ASC');
        $this->assertCount(1, $elementspage1);
        $this->assertCount(1, $elementspage2);
        $this->assertSame('Text1', reset($elementspage1)->name);
        $this->assertSame('Text2', reset($elementspage2)->name);

        // Ensure template_updated fired for course context.
        $hasupdate = array_filter($events, static fn($e) => $e instanceof template_updated);
        $this->assertNotEmpty($hasupdate);
    }
}
