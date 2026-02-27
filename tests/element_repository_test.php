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
 * Unit tests for the element repository.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\service\element_repository
 */

declare(strict_types=1);

namespace mod_customcert;

use context_course;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_repository;

/**
 * Tests for element_repository list behaviour and ordering.
 */
final class element_repository_test extends \advanced_testcase {
    /**
     * Repository under test.
     *
     * @var element_repository
     */
    private element_repository $repo;

    /**
     * Template repository for setup.
     *
     * @var template_repository
     */
    private template_repository $trepo;

    /**
     * Page repository for setup.
     *
     * @var page_repository
     */
    private page_repository $prepo;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $registry = new element_registry();
        $factory = new element_factory($registry);
        $this->repo = new element_repository($factory);
        $this->trepo = new template_repository();
        $this->prepo = new page_repository();
    }

    /**
     * Ensures list_by_page orders by sequence then id.
     *
     * @covers ::list_by_page
     */
    public function test_list_by_page_orders_by_sequence_then_id(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        $templateid = $this->trepo->create((object) [
            'name' => 'T',
            'contextid' => $context->id,
        ]);

        $pageid = $this->prepo->create((object) [
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);

        $now = time();
        // Sequences 2,2,1 to verify sequence ASC then id ASC tie-breaker.
        $e1 = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'First',
            'element' => 'text',
            'data' => null,
            'posx' => null,
            'posy' => null,
            'refpoint' => null,
            'alignment' => 'L',
            'sequence' => 2,
            'timecreated' => $now,
            'timemodified' => $now,
        ], true);
        $e2 = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Second',
            'element' => 'text',
            'data' => null,
            'posx' => null,
            'posy' => null,
            'refpoint' => null,
            'alignment' => 'L',
            'sequence' => 2,
            'timecreated' => $now,
            'timemodified' => $now,
        ], true);
        $e3 = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Third',
            'element' => 'text',
            'data' => null,
            'posx' => null,
            'posy' => null,
            'refpoint' => null,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ], true);

        $records = array_values($this->repo->list_by_page($pageid));
        $this->assertCount(3, $records);
        $this->assertSame([$e3, $e1, $e2], array_map(static fn($r) => (int) $r->id, $records));
    }

    /**
     * Ensures list_by_page filters to the given page.
     *
     * @covers ::list_by_page
     */
    public function test_list_by_page_filters_by_pageid(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        $templateid = $this->trepo->create((object) [
            'name' => 'T2',
            'contextid' => $context->id,
        ]);

        $page1 = $this->prepo->create((object) [
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);
        $page2 = $this->prepo->create((object) [
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 2,
        ]);

        $now = time();
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $page1,
            'name' => 'On page1',
            'element' => 'text',
            'data' => null,
            'posx' => null,
            'posy' => null,
            'refpoint' => null,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $otherid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $page2,
            'name' => 'On page2',
            'element' => 'text',
            'data' => null,
            'posx' => null,
            'posy' => null,
            'refpoint' => null,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ], true);

        $records = array_values($this->repo->list_by_page($page1));
        $this->assertCount(1, $records);
        $this->assertSame([$page1], [(int) $records[0]->pageid]);
        $this->assertNotSame($otherid, (int) $records[0]->id);
    }

    /**
     * Ensures delete() removes the DB record and fires element_deleted event.
     *
     * @covers ::delete
     */
    public function test_delete_removes_record_and_fires_event(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        $templateid = $this->trepo->create((object) [
            'name' => 'T',
            'contextid' => $context->id,
        ]);

        $pageid = $this->prepo->create((object) [
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'To delete',
            'element' => 'text',
            'data' => null,
            'posx' => null,
            'posy' => null,
            'refpoint' => null,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $repo = new element_repository(element_factory::build_with_defaults());
        $element = $repo->load_by_page_id($pageid)[0];

        $sink = $this->redirectEvents();
        $result = $this->repo->delete($element);
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $elementid]));

        $eventclasses = array_map(fn($e) => get_class($e), $events);
        $this->assertContains(\mod_customcert\event\element_deleted::class, $eventclasses);
    }
}
