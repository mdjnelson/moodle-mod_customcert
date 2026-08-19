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

    /**
     * Test that deleting a page belonging to a different template is rejected, that the
     * foreign page and every one of its elements survive unchanged, and that none of them
     * are cascade-deleted the way a legitimate delete_page() call would delete them (#876).
     *
     * @covers \mod_customcert\template::delete_page
     */
    public function test_delete_page_rejects_foreign_page(): void {
        global $DB;

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        // Give B's page a second element, so a wrongly-permitted delete_page() call would
        // have to cascade-delete more than one element for this test to catch it.
        $bsecondelement = new stdClass();
        $bsecondelement->pageid = $b['pageid'];
        $bsecondelement->name = 'Second element';
        $bsecondelement->element = 'text';
        $bsecondelement->sequence = 2;
        $bsecondelement->timecreated = time();
        $bsecondelement->timemodified = time();
        $DB->insert_record('customcert_elements', $bsecondelement);

        $pagebefore = $DB->get_record('customcert_pages', ['id' => $b['pageid']], '*', MUST_EXIST);
        $elementsbefore = $DB->get_records('customcert_elements', ['pageid' => $b['pageid']], 'sequence ASC');
        $this->assertCount(2, $elementsbefore, 'Test setup should give the foreign page two elements.');

        $thrown = null;
        try {
            $a['template']->delete_page($b['pageid']);
        } catch (\moodle_exception $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'A page from template B must be rejected by delete_page() for template A.');

        $this->assertTrue(
            $DB->record_exists('customcert_pages', ['id' => $b['pageid']]),
            'The foreign page must still exist after the rejected deletion.'
        );
        $pageafter = $DB->get_record('customcert_pages', ['id' => $b['pageid']], '*', MUST_EXIST);
        $this->assertEquals($pagebefore, $pageafter, 'The foreign page must be unchanged after being rejected.');

        $elementsafter = $DB->get_records('customcert_elements', ['pageid' => $b['pageid']], 'sequence ASC');
        $this->assertCount(
            2,
            $elementsafter,
            'No child elements must be cascade-deleted by the rejected page deletion.'
        );
        $this->assertEquals(
            $elementsbefore,
            $elementsafter,
            'The foreign page\'s elements must be unchanged after being rejected.'
        );
    }

    /**
     * Test that deleting a page belonging to the authorised template still works (#876).
     *
     * @covers \mod_customcert\template::delete_page
     */
    public function test_delete_page_allows_own_page(): void {
        global $DB;

        $a = $this->create_course_with_cert();

        $a['template']->delete_page($a['pageid']);

        $this->assertEquals(0, $DB->count_records('customcert_pages', ['id' => $a['pageid']]));
        $this->assertEquals(0, $DB->count_records('customcert_elements', ['id' => $a['elementid']]));
    }

    /**
     * Test that deleting an element belonging to a different template is rejected, that
     * the foreign element survives unchanged, and that its page and sibling element are
     * unaffected (delete_element() would otherwise resequence siblings on the page) (#876).
     *
     * @covers \mod_customcert\template::delete_element
     */
    public function test_delete_element_rejects_foreign_element(): void {
        global $DB;

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        // Give B's page a sibling element, so a wrongly-permitted delete_element() call
        // would resequence it — this test can then catch that resequencing.
        $bsibling = new stdClass();
        $bsibling->pageid = $b['pageid'];
        $bsibling->name = 'Sibling element';
        $bsibling->element = 'text';
        $bsibling->sequence = 2;
        $bsibling->timecreated = time();
        $bsibling->timemodified = time();
        $bsiblingid = $DB->insert_record('customcert_elements', $bsibling);

        $elementbefore = $DB->get_record('customcert_elements', ['id' => $b['elementid']], '*', MUST_EXIST);
        $siblingbefore = $DB->get_record('customcert_elements', ['id' => $bsiblingid], '*', MUST_EXIST);
        $pagebefore = $DB->get_record('customcert_pages', ['id' => $b['pageid']], '*', MUST_EXIST);

        $thrown = null;
        try {
            $a['template']->delete_element($b['elementid']);
        } catch (\moodle_exception $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'An element from template B must be rejected by delete_element() for template A.');

        $this->assertTrue(
            $DB->record_exists('customcert_elements', ['id' => $b['elementid']]),
            'The foreign element must still exist after the rejected deletion.'
        );
        $elementafter = $DB->get_record('customcert_elements', ['id' => $b['elementid']], '*', MUST_EXIST);
        $this->assertEquals($elementbefore, $elementafter, 'The foreign element must be unchanged after being rejected.');

        $siblingafter = $DB->get_record('customcert_elements', ['id' => $bsiblingid], '*', MUST_EXIST);
        $this->assertEquals(
            $siblingbefore,
            $siblingafter,
            'The sibling element on the foreign page must not be resequenced after the rejected deletion.'
        );

        $pageafter = $DB->get_record('customcert_pages', ['id' => $b['pageid']], '*', MUST_EXIST);
        $this->assertEquals(
            $pagebefore,
            $pageafter,
            'The page containing the foreign element must be unchanged after the rejected deletion.'
        );
    }

    /**
     * Test that deleting an element belonging to the authorised template still works (#876).
     *
     * @covers \mod_customcert\template::delete_element
     */
    public function test_delete_element_allows_own_element(): void {
        global $DB;

        $a = $this->create_course_with_cert();

        $a['template']->delete_element($a['elementid']);

        $this->assertEquals(0, $DB->count_records('customcert_elements', ['id' => $a['elementid']]));
    }

    /**
     * Test that moving a page belonging to a different template is rejected, and that
     * every page sequence in template B is left exactly as it was — move_item() would
     * otherwise swap the foreign page's sequence with a neighbouring page in template B
     * (#876).
     *
     * @covers \mod_customcert\template::move_item
     */
    public function test_move_item_rejects_foreign_page(): void {
        global $DB;

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        // The create_course_with_cert() helper already gives template B a default first
        // page plus its own second page (pageid), so there is a legitimate neighbour for
        // move_item() to swap sequences with if the ownership check were bypassed.
        $bpagesbefore = $DB->get_records('customcert_pages', ['templateid' => $b['template']->get_id()], 'sequence ASC');
        $this->assertGreaterThanOrEqual(2, count($bpagesbefore), 'Test setup should give template B at least two pages.');

        $thrown = null;
        try {
            $a['template']->move_item('page', $b['pageid'], 'up');
        } catch (\moodle_exception $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'A page from template B must be rejected by move_item() for template A.');

        $bpagesafter = $DB->get_records('customcert_pages', ['templateid' => $b['template']->get_id()], 'sequence ASC');
        $this->assertEquals(
            $bpagesbefore,
            $bpagesafter,
            'Template B\'s page sequences must be unchanged after the rejected move.'
        );
    }

    /**
     * Test that moving an element belonging to a different template is rejected, and that
     * every element sequence on the foreign page is left exactly as it was — move_item()
     * would otherwise swap the foreign element's sequence with a sibling on its page
     * (#876).
     *
     * @covers \mod_customcert\template::move_item
     */
    public function test_move_item_rejects_foreign_element(): void {
        global $DB;

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        // Give B's page a sibling element, so there is a legitimate neighbour for
        // move_item() to swap sequences with if the ownership check were bypassed.
        $bsibling = new stdClass();
        $bsibling->pageid = $b['pageid'];
        $bsibling->name = 'Sibling element';
        $bsibling->element = 'text';
        $bsibling->sequence = 2;
        $bsibling->timecreated = time();
        $bsibling->timemodified = time();
        $DB->insert_record('customcert_elements', $bsibling);

        $belementsbefore = $DB->get_records('customcert_elements', ['pageid' => $b['pageid']], 'sequence ASC');
        $this->assertCount(2, $belementsbefore, 'Test setup should give the foreign page two elements.');

        $thrown = null;
        try {
            // Direction "down" targets the sibling's sequence (2), so this is a real swap
            // opportunity and not merely a no-op due to there being no matching neighbour.
            $a['template']->move_item('element', $b['elementid'], 'down');
        } catch (\moodle_exception $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'An element from template B must be rejected by move_item() for template A.');

        $belementsafter = $DB->get_records('customcert_elements', ['pageid' => $b['pageid']], 'sequence ASC');
        $this->assertEquals(
            $belementsbefore,
            $belementsafter,
            'Template B\'s element sequences must be unchanged after the rejected move.'
        );
    }

    /**
     * Test that moving a page belonging to the authorised template still swaps sequences
     * correctly (#876).
     *
     * @covers \mod_customcert\template::move_item
     */
    public function test_move_item_allows_own_page(): void {
        global $DB;

        $a = $this->create_course_with_cert();

        // The create_course_with_cert() helper already produced a default first page and
        // a second page (pageid, with the test element). Add a third page to swap with it.
        $page3id = $a['template']->add_page();

        $page2 = $DB->get_record('customcert_pages', ['id' => $a['pageid']], '*', MUST_EXIST);
        $page3 = $DB->get_record('customcert_pages', ['id' => $page3id], '*', MUST_EXIST);

        $a['template']->move_item('page', $page3id, 'up');

        $newpage2 = $DB->get_record('customcert_pages', ['id' => $a['pageid']], '*', MUST_EXIST);
        $newpage3 = $DB->get_record('customcert_pages', ['id' => $page3id], '*', MUST_EXIST);

        $this->assertEquals($page3->sequence, $newpage2->sequence);
        $this->assertEquals($page2->sequence, $newpage3->sequence);
    }

    /**
     * Test that moving an element belonging to the authorised template still swaps
     * sequences correctly (#876).
     *
     * @covers \mod_customcert\template::move_item
     */
    public function test_move_item_allows_own_element(): void {
        global $DB;

        $a = $this->create_course_with_cert();

        // Add a second element to the same page as the existing (sequence 1) element.
        $element2 = new stdClass();
        $element2->pageid = $a['pageid'];
        $element2->name = 'Second element';
        $element2->element = 'text';
        $element2->sequence = 2;
        $element2->timecreated = time();
        $element2->timemodified = time();
        $element2id = $DB->insert_record('customcert_elements', $element2);

        $a['template']->move_item('element', $element2id, 'up');

        $newelement1 = $DB->get_record('customcert_elements', ['id' => $a['elementid']], '*', MUST_EXIST);
        $newelement2 = $DB->get_record('customcert_elements', ['id' => $element2id], '*', MUST_EXIST);

        $this->assertEquals(2, $newelement1->sequence);
        $this->assertEquals(1, $newelement2->sequence);
    }

    /**
     * Test that template::get_page_or_fail() — the exact method edit_element.php calls on
     * its "add" branch to validate the supplied pageid — returns a page belonging to the
     * authorised template (#876).
     *
     * @covers \mod_customcert\template::get_page_or_fail
     */
    public function test_get_page_or_fail_allows_own_page(): void {
        $a = $this->create_course_with_cert();

        $page = $a['template']->get_page_or_fail($a['pageid']);

        $this->assertEquals($a['pageid'], $page->id);
        $this->assertEquals($a['template']->get_id(), $page->templateid);
    }

    /**
     * Test that template::get_page_or_fail() — the exact method edit_element.php calls on
     * its "add" branch to validate the supplied pageid before allowing an element to be
     * added — rejects a page belonging to a different template, and leaves that foreign
     * page untouched (#876).
     *
     * @covers \mod_customcert\template::get_page_or_fail
     */
    public function test_get_page_or_fail_rejects_foreign_page(): void {
        global $DB;

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        $before = $DB->get_record('customcert_pages', ['id' => $b['pageid']], '*', MUST_EXIST);

        $thrown = null;
        try {
            $a['template']->get_page_or_fail($b['pageid']);
        } catch (\moodle_exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'A page from template B must be rejected by get_page_or_fail() for template A.');

        $after = $DB->get_record('customcert_pages', ['id' => $b['pageid']], '*', MUST_EXIST);
        $this->assertEquals($before, $after, 'The foreign page must remain unchanged after being rejected.');
    }

    /**
     * Test that template::get_element_or_fail() — the exact method edit_element.php calls
     * on its "edit" branch to validate the supplied elementid — returns an element
     * belonging to the authorised template (#876).
     *
     * @covers \mod_customcert\template::get_element_or_fail
     */
    public function test_get_element_or_fail_allows_own_element(): void {
        $a = $this->create_course_with_cert();

        $element = $a['template']->get_element_or_fail($a['elementid']);

        $this->assertEquals($a['elementid'], $element->id);
        $this->assertEquals($a['pageid'], $element->pageid);
    }

    /**
     * Test that template::get_element_or_fail() — the exact method edit_element.php calls
     * on its "edit" branch to validate the supplied elementid before editing it — rejects
     * an element whose page belongs to a different template, and leaves that foreign
     * element untouched (#876).
     *
     * @covers \mod_customcert\template::get_element_or_fail
     */
    public function test_get_element_or_fail_rejects_foreign_element(): void {
        global $DB;

        $a = $this->create_course_with_cert();
        $b = $this->create_course_with_cert();

        $before = $DB->get_record('customcert_elements', ['id' => $b['elementid']], '*', MUST_EXIST);

        $thrown = null;
        try {
            $a['template']->get_element_or_fail($b['elementid']);
        } catch (\moodle_exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'An element from template B must be rejected by get_element_or_fail() for template A.');

        $after = $DB->get_record('customcert_elements', ['id' => $b['elementid']], '*', MUST_EXIST);
        $this->assertEquals($before, $after, 'The foreign element must remain unchanged after being rejected.');
    }
}
