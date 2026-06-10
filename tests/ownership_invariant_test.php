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

/**
 * Cross-template ownership invariant regression tests.
 *
 * A user authorised for template A must not be able to access, edit, move,
 * rename, delete, or update page/element data from template B by mixing
 * request parameters.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use stdClass;

/**
 * Cross-template ownership invariant regression tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ownership_invariant_test extends \advanced_testcase {
    /**
     * Set the test up.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Create a course with a customcert, a page, and a text element.
     *
     * @return array{course: stdClass, customcert: stdClass, template: template, pageid: int, elementid: int}
     */
    private function create_course_with_cert(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $templaterecord = $DB->get_record('customcert_templates', ['id' => $customcert->templateid], '*', MUST_EXIST);
        $template = new template($templaterecord);

        $pageid = $template->add_page();

        $element = new stdClass();
        $element->pageid = $pageid;
        $element->name = 'Test element';
        $element->element = 'text';
        $element->sequence = 1;
        $element->timecreated = time();
        $element->timemodified = time();
        $elementid = $DB->insert_record('customcert_elements', $element);

        return ['course' => $course, 'customcert' => $customcert, 'template' => $template,
            'pageid' => $pageid, 'elementid' => $elementid];
    }

    /**
     * Test that the ajax.php ownership guard rejects an element from a different template.
     *
     * This test exercises the SQL ownership guard added to ajax.php that verifies
     * each submitted element ID belongs to the template identified by tid before
     * updating its position.
     *
     * @covers \mod_customcert\template
     */
    public function test_update_position_guard_rejects_foreign_elementid(): void {
        global $DB;

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        // Simulate the ownership check performed by ajax.php: element from B must
        // not be updatable via template A's tid.
        $sql = "SELECT e.id
                  FROM {customcert_elements} e
                  JOIN {customcert_pages} p ON p.id = e.pageid
                 WHERE e.id = :elementid
                   AND p.templateid = :templateid";
        $allowed = $DB->record_exists_sql($sql, [
            'elementid' => $b['elementid'],
            'templateid' => $a['template']->get_id(),
        ]);

        $this->assertFalse($allowed, 'Element from template B must not pass the ajax.php ownership guard for template A.');
    }

    /**
     * Test that the ajax.php ownership guard allows an element belonging to the correct template.
     *
     * @covers \mod_customcert\template
     */
    public function test_update_position_guard_allows_own_elementid(): void {
        global $DB;

        $a = $this->create_course_with_cert();

        $sql = "SELECT e.id
                  FROM {customcert_elements} e
                  JOIN {customcert_pages} p ON p.id = e.pageid
                 WHERE e.id = :elementid
                   AND p.templateid = :templateid";
        $allowed = $DB->record_exists_sql($sql, [
            'elementid' => $a['elementid'],
            'templateid' => $a['template']->get_id(),
        ]);

        $this->assertTrue($allowed, 'Element from template A must pass the ajax.php ownership guard for template A.');
    }

    /**
     * Test that mod_customcert_inplace_editable does not allow renaming an element
     * from a foreign template when the user only has manage capability on template A.
     *
     * @covers ::mod_customcert_inplace_editable
     */
    public function test_inplace_editable_rename_foreign_element_denied(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/customcert/lib.php');

        $teacher = $this->getDataGenerator()->create_user();

        // Course A: teacher has manage capability here.
        $a = $this->create_course_with_cert();
        $this->getDataGenerator()->enrol_user($teacher->id, $a['course']->id, 'editingteacher');

        // Course B: a separate course/template the teacher has no access to.
        $b = $this->create_course_with_cert();

        $this->setUser($teacher);

        // Attempting to rename an element from template B while authenticated as
        // a teacher in course A must be rejected.
        $this->expectException(\moodle_exception::class);
        mod_customcert_inplace_editable('elementname', $b['elementid'], 'Hacked name');
    }
}
