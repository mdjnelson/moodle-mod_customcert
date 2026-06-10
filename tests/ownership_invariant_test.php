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
 * Cross-course ownership invariant regression tests (#818).
 *
 * Audited paths:
 *  - item_move_service::move_item (page move, element move)
 *  - template_service::delete_element
 *  - template_service::delete_page
 *  - mod_customcert_inplace_editable (rename element)
 *  - element_repository::update_position via ajax.php ownership guard
 *
 * Already covered elsewhere (not duplicated here):
 *  - edit_element.php tid+id / tid+pageid mismatch → edit_element_test.php
 *  - external::save_element cross-template → external_test.php
 *  - external::get_element_html cross-template → external_test.php
 *  - mod_customcert_output_fragment_editelement cross-template → lib_test.php
 *
 * Primary invariant: a user authorised for template A must not be able to
 * access, move, delete, rename, or update page/element data from template B
 * by mixing request parameters.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use dml_missing_record_exception;
use invalid_parameter_exception;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\item_move_service;
use mod_customcert\service\template_service;

/**
 * Cross-course ownership invariant regression tests.
 *
 * @group mod_customcert
 */
final class ownership_invariant_test extends advanced_testcase {
    /**
     * Create a course with a customcert module, one page, and one text element.
     *
     * @return array{template: template, pageid: int, elementid: int}
     */
    private function create_course_with_cert(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $templaterecord = $DB->get_record('customcert_templates', ['id' => $customcert->templateid], '*', MUST_EXIST);
        $template = template::from_record($templaterecord);

        $pageid = template_service::create()->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Test element',
            'element' => 'text',
            'data' => json_encode(['text' => 'hello']),
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return ['course' => $course, 'template' => $template, 'pageid' => (int) $pageid, 'elementid' => (int) $elementid];
    }

    /**
     * Moving a page that belongs to template B while supplying template A must be rejected.
     *
     * @covers \mod_customcert\service\item_move_service::move_item
     */
    public function test_move_page_rejects_foreign_pageid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        $service = item_move_service::create();

        // Template A authorised, but pageid from template B.
        $this->expectException(dml_missing_record_exception::class);
        $service->move_item($a['template'], item_move_service::ITEM_PAGE, $b['pageid'], item_move_service::DIRECTION_UP);
    }

    /**
     * Moving a page that belongs to template A with the correct template must succeed (no exception).
     *
     * @covers \mod_customcert\service\item_move_service::move_item
     */
    public function test_move_page_same_template_does_not_throw(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $a = $this->create_course_with_cert();

        $service = item_move_service::create();

        // No second page to swap with, so the move is a no-op — but it must not throw.
        $service->move_item($a['template'], item_move_service::ITEM_PAGE, $a['pageid'], item_move_service::DIRECTION_UP);
        $this->assertTrue(true); // Reached without exception.
    }

    /**
     * Moving an element whose containing page belongs to template B while supplying template A must be rejected.
     *
     * @covers \mod_customcert\service\item_move_service::move_item
     */
    public function test_move_element_rejects_foreign_elementid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        $service = item_move_service::create();

        // Template A authorised, but elementid from template B.
        $this->expectException(dml_missing_record_exception::class);
        $service->move_item(
            $a['template'],
            item_move_service::ITEM_ELEMENT,
            $b['elementid'],
            item_move_service::DIRECTION_UP
        );
    }

    /**
     * Moving an element that belongs to template A with the correct template must succeed (no exception).
     *
     * @covers \mod_customcert\service\item_move_service::move_item
     */
    public function test_move_element_same_template_does_not_throw(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $a = $this->create_course_with_cert();

        $service = item_move_service::create();

        // Only one element, so the move is a no-op — but it must not throw.
        $service->move_item(
            $a['template'],
            item_move_service::ITEM_ELEMENT,
            $a['elementid'],
            item_move_service::DIRECTION_UP
        );
        $this->assertTrue(true);
    }

    /**
     * Deleting an element from template B while supplying template A must be rejected.
     *
     * @covers \mod_customcert\service\template_service::delete_element
     */
    public function test_delete_element_rejects_foreign_elementid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        $service = template_service::create();

        // Template A authorised, but elementid from template B.
        $this->expectException(invalid_parameter_exception::class);
        $service->delete_element($a['template'], $b['elementid']);
    }

    /**
     * Deleting an element that belongs to template A with the correct template must succeed.
     *
     * @covers \mod_customcert\service\template_service::delete_element
     */
    public function test_delete_element_same_template_succeeds(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $a = $this->create_course_with_cert();

        $service = template_service::create();
        $service->delete_element($a['template'], $a['elementid']);

        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $a['elementid']]));
    }

    /**
     * Deleting a page from template B while supplying template A must be rejected.
     *
     * @covers \mod_customcert\service\template_service::delete_page
     */
    public function test_delete_page_rejects_foreign_pageid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        $service = template_service::create();

        // Template A authorised, but pageid from template B.
        $this->expectException(invalid_parameter_exception::class);
        $service->delete_page($a['template'], $b['pageid']);
    }

    /**
     * Deleting a page that belongs to template A with the correct template must succeed.
     *
     * @covers \mod_customcert\service\template_service::delete_page
     */
    public function test_delete_page_same_template_succeeds(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $a = $this->create_course_with_cert();

        $service = template_service::create();
        $service->delete_page($a['template'], $a['pageid']);

        $this->assertFalse($DB->record_exists('customcert_pages', ['id' => $a['pageid']]));
    }

    /**
     * Renaming an element via inplace_editable resolves the template from the element itself,
     * so supplying a foreign elementid must not rename an element belonging to another template.
     *
     * The function looks up element→page→template dynamically, so the capability check is
     * performed against the template that actually owns the element. A user who only has
     * manage capability in Course A will fail the require_manage() check when the element
     * belongs to Course B.
     *
     * @covers \mod_customcert_inplace_editable
     */
    public function test_inplace_editable_rename_foreign_element_denied(): void {
        $this->resetAfterTest();

        // Course A: teacher is enrolled and has manage capability here.
        $a = $this->create_course_with_cert();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $a['course']->id, 'editingteacher');

        // Course B: a separate course/template the teacher has no access to.
        $b = $this->create_course_with_cert();

        $this->setUser($teacher);

        // Attempting to rename an element from Course B must throw — either a capability
        // exception or a redirect/login exception depending on enrolment state.
        $this->expectException(\moodle_exception::class);
        mod_customcert_inplace_editable('elementname', $b['elementid'], 'Hacked name');
    }

    /**
     * Renaming an element via inplace_editable succeeds when the user has manage capability
     * for the template that owns the element.
     *
     * @covers \mod_customcert_inplace_editable
     */
    public function test_inplace_editable_rename_own_element_succeeds(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Create cert in the course the teacher is enrolled in.
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $templaterecord = $DB->get_record('customcert_templates', ['id' => $customcert->templateid], '*', MUST_EXIST);
        $template = template::from_record($templaterecord);
        $pageid = template_service::create()->add_page($template);
        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Original name',
            'element' => 'text',
            'data' => json_encode(['text' => 'hello']),
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->setUser($teacher);

        mod_customcert_inplace_editable('elementname', $elementid, 'New name');

        $row = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $this->assertSame('New name', $row->name);
    }

    /**
     * update_position with a foreign elementid must be rejected after the ownership guard
     * added to ajax.php (get_for_template_or_fail called before update_position).
     *
     * This test exercises the repository method directly to confirm the guard throws.
     *
     * @covers \mod_customcert\service\element_repository::get_for_template_or_fail
     */
    public function test_update_position_guard_rejects_foreign_elementid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        $factory = new element_factory(new element_registry());
        $elementrepo = new element_repository($factory);

        // The ownership guard (get_for_template_or_fail) must throw when template A is
        // supplied with an element from template B.
        $this->expectException(dml_missing_record_exception::class);
        $elementrepo->get_for_template_or_fail($a['template']->get_id(), $b['elementid']);
    }

    /**
     * update_position with a matching templateid and elementid must succeed.
     *
     * @covers \mod_customcert\service\element_repository::update_position
     */
    public function test_update_position_same_template_succeeds(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $a = $this->create_course_with_cert();

        $factory = new element_factory(new element_registry());
        $elementrepo = new element_repository($factory);

        // Ownership guard passes, then position is updated.
        $elementrepo->get_for_template_or_fail($a['template']->get_id(), $a['elementid']);
        $elementrepo->update_position($a['elementid'], 42, 99, $a['template']->get_contextid());

        $row = $DB->get_record('customcert_elements', ['id' => $a['elementid']], '*', MUST_EXIST);
        $this->assertSame(42, (int) $row->posx);
        $this->assertSame(99, (int) $row->posy);
    }
}
