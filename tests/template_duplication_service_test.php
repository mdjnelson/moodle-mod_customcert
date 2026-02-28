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
 * Unit tests for the template duplication service.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\service\template_duplication_service
 */

declare(strict_types=1);

namespace mod_customcert;

use context_course;
use mod_customcert\event\template_duplicated;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_duplication_service;
use mod_customcert\service\template_repository;

/**
 * Tests for template_duplication_service behaviour.
 */
final class template_duplication_service_test extends \advanced_testcase {
    /** @var template_repository */
    private template_repository $templates;

    /** @var page_repository */
    private page_repository $pages;

    /** @var template_duplication_service */
    private template_duplication_service $service;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->templates = new template_repository();
        $this->pages = new page_repository();
        $factory = element_factory::build_with_defaults();
        $this->service = new template_duplication_service($this->templates, $this->pages, new element_repository($factory));
    }

    /**
     * Ensures duplication copies template, pages, elements and fires the duplicated event.
     *
     * @covers ::duplicate
     */
    public function test_duplicate_copies_pages_elements_and_triggers_event(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        $templateid = $this->templates->create((object) [
            'name' => 'Base',
            'contextid' => $context->id,
        ]);

        $pageid = $this->pages->create((object) [
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);

        $now = time();
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Text',
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

        $sink = $this->redirectEvents();
        $newtemplateid = $this->service->duplicate($templateid);
        $events = $sink->get_events();

        $duplicated = array_filter($events, static function ($event) {
            return $event instanceof template_duplicated;
        });
        $this->assertNotEmpty($duplicated, 'template_duplicated event should be fired.');

        $newtemplate = $this->templates->get_by_id_or_fail($newtemplateid);
        $this->assertSame('Base (copy)', $newtemplate->name);

        $newpages = array_values($this->pages->list_by_template($newtemplateid));
        $this->assertCount(1, $newpages);
        $this->assertSame(1, (int)$newpages[0]->sequence);

        $erepo = new element_repository(element_factory::build_with_defaults());

        $elements = array_values($erepo->list_by_page((int)$newpages[0]->id));
        $this->assertCount(1, $elements);
        $this->assertSame('Text', $elements[0]->name);
        $this->assertSame('text', $elements[0]->element);
    }
}
