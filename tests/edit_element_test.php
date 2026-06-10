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
 * Request-level ownership tests for edit_element.php.
 *
 * These tests exercise the same ownership checks that edit_element.php performs
 * when processing the 'edit' and 'add' actions, using two separate course contexts
 * to model the real cross-course parameter-tampering scenario.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use context_course;
use dml_missing_record_exception;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_repository;

/**
 * Request-level ownership tests for edit_element.php.
 *
 * @group mod_customcert
 */
final class edit_element_test extends advanced_testcase {
    /**
     * Helper to create a course, template, page, and element.
     *
     * @return array [$templateid, $pageid, $elementid]
     */
    private function create_template_with_element(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $trepo = new template_repository();
        $templateid = $trepo->create((object)[
            'name' => 'T',
            'contextid' => $context->id,
        ]);
        $prepo = new page_repository();
        $pageid = $prepo->create((object)[
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);
        $elementid = $DB->insert_record('customcert_elements', (object)[
            'pageid' => $pageid,
            'name' => 'E',
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

        return [$templateid, $pageid, $elementid];
    }

    /**
     * Simulates the 'edit' action in edit_element.php: tid from course A, id from course B should fail.
     *
     * @covers \mod_customcert\service\element_repository::get_for_template_or_fail
     */
    public function test_edit_action_mismatched_tid_and_id_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Course A: provides the tid (template A).
        [$templateida] = $this->create_template_with_element();

        // Course B: provides the element id (element belongs to template B).
        [, , $elementidb] = $this->create_template_with_element();

        // Edit_element.php 'edit' path: get_for_template_or_fail($tid, $id).
        $elementrepo = new element_repository(new element_factory(new element_registry()));
        $this->expectException(dml_missing_record_exception::class);
        $elementrepo->get_for_template_or_fail($templateida, $elementidb);
    }

    /**
     * Simulates the 'add' action in edit_element.php: tid from course A, pageid from course B should fail.
     *
     * @covers \mod_customcert\service\page_repository::get_for_template_or_fail
     */
    public function test_add_action_mismatched_tid_and_pageid_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Course A: provides the tid (template A).
        [$templateida] = $this->create_template_with_element();

        // Course B: provides the pageid (page belongs to template B).
        [, $pageidb] = $this->create_template_with_element();

        // Edit_element.php 'add' path: get_for_template_or_fail($tid, $pageid).
        $pagerepo = new page_repository();
        $this->expectException(dml_missing_record_exception::class);
        $pagerepo->get_for_template_or_fail($templateida, $pageidb);
    }

    /**
     * Simulates the 'edit' action in edit_element.php: matching tid and id should succeed.
     *
     * @covers \mod_customcert\service\element_repository::get_for_template_or_fail
     */
    public function test_edit_action_matching_tid_and_id_succeeds(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$templateid, , $elementid] = $this->create_template_with_element();

        $elementrepo = new element_repository(new element_factory(new element_registry()));
        $record = $elementrepo->get_for_template_or_fail($templateid, $elementid);
        $this->assertSame($elementid, (int) $record->id);
    }

    /**
     * Simulates the 'add' action in edit_element.php: matching tid and pageid should succeed.
     *
     * @covers \mod_customcert\service\page_repository::get_for_template_or_fail
     */
    public function test_add_action_matching_tid_and_pageid_succeeds(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$templateid, $pageid] = $this->create_template_with_element();

        $pagerepo = new page_repository();
        $record = $pagerepo->get_for_template_or_fail($templateid, $pageid);
        $this->assertSame($pageid, (int) $record->id);
    }
}
