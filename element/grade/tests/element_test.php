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
 * Unit tests for the grade element.
 *
 * @package    customcertelement_grade
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_grade;

use advanced_testcase;
use context_module;
use grade_grade;
use grade_item;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;
use mod_customcert\template;
use pdf;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Unit tests for the grade element.
 *
 * @package    customcertelement_grade
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class element_test extends advanced_testcase {
    /**
     * Helper to build a minimal element DB record.
     *
     * @param array $override
     * @return stdClass
     */
    private function make_record(array $override = []): stdClass {
        return (object) array_merge([
            'id' => 1,
            'pageid' => 1,
            'name' => 'Grade',
            'element' => 'grade',
            'data' => json_encode([
                'gradeitem' => element::GRADE_COURSE,
                'gradeformat' => GRADE_DISPLAY_TYPE_REAL,
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 0,
            ]),
            'font' => null,
            'fontsize' => null,
            'colour' => null,
            'posx' => 10,
            'posy' => 10,
            'width' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $override);
    }

    /**
     * Test that the constructor returns an instance of element.
     *
     * @covers \customcertelement_grade\element::__construct
     */
    public function test_constructor_returns_instance(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     *
     * @covers \customcertelement_grade\element
     */
    public function test_implements_interfaces(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test the GRADE_COURSE constant.
     *
     * @covers \customcertelement_grade\element
     */
    public function test_grade_course_constant(): void {
        $this->assertSame('0', element::GRADE_COURSE);
    }

    /**
     * Test that normalise_data() returns expected keys and values.
     *
     * @covers \customcertelement_grade\element::normalise_data
     */
    public function test_normalise_data_returns_expected_keys(): void {
        $el = new element($this->make_record());
        $formdata = (object) [
            'gradeitem' => element::GRADE_COURSE,
            'gradeformat' => GRADE_DISPLAY_TYPE_PERCENTAGE,
            'font' => 'helvetica',
            'fontsize' => 14,
            'colour' => '#ff0000',
            'width' => 100,
        ];
        $result = $el->normalise_data($formdata);
        $this->assertSame(element::GRADE_COURSE, $result['gradeitem']);
        $this->assertSame((string)GRADE_DISPLAY_TYPE_PERCENTAGE, $result['gradeformat']);
        $this->assertSame('helvetica', $result['font']);
        $this->assertSame(14, $result['fontsize']);
        $this->assertSame('#ff0000', $result['colour']);
        $this->assertSame(100, $result['width']);
    }

    /**
     * Test that normalise_data() handles missing fields gracefully.
     *
     * @covers \customcertelement_grade\element::normalise_data
     */
    public function test_normalise_data_handles_missing_fields(): void {
        $el = new element($this->make_record());
        $result = $el->normalise_data(new stdClass());
        $this->assertSame('', $result['gradeitem']);
        $this->assertSame('', $result['gradeformat']);
        $this->assertSame('', $result['font']);
        $this->assertSame(0, $result['fontsize']);
        $this->assertSame('', $result['colour']);
        $this->assertSame(0, $result['width']);
    }

    /**
     * Test that validate() returns an empty array.
     *
     * @covers \customcertelement_grade\element::validate
     */
    public function test_validate_returns_empty_array(): void {
        $el = new element($this->make_record());
        $this->assertSame([], $el->validate([]));
    }

    /**
     * Test that render_html() returns empty string when no data is set.
     *
     * @covers \customcertelement_grade\element::render_html
     */
    public function test_render_html_empty_when_no_data(): void {
        $el = new element($this->make_record(['data' => null]));
        $this->assertSame('', $el->render_html());
    }

    /**
     * Test that render_html() returns a non-empty string with a real course.
     *
     * @covers \customcertelement_grade\element::render_html
     */
    public function test_render_html_returns_grade_value(): void {
        $this->resetAfterTest();
        global $COURSE;

        $course = $this->getDataGenerator()->create_course();
        $COURSE = $course;

        $el = new element($this->make_record());
        $html = $el->render_html();
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    /**
     * Test that get_type() returns 'grade'.
     *
     * @covers \customcertelement_grade\element
     */
    public function test_get_type(): void {
        $el = new element($this->make_record());
        $this->assertSame('grade', $el->get_type());
    }

    /**
     * Helper to create a full customcert setup and return [elementid, customcertid, courseid].
     *
     * @param string $gradeitem
     * @return array{int, int, int}
     */
    private function create_customcert_setup(string $gradeitem): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = $DB->get_record(
            'customcert_templates',
            ['contextid' => context_module::instance($customcert->cmid)->id]
        );
        $template = template::from_record((new template_repository())->get_by_id_or_fail((int)$template->id));
        $service = template_service::create();
        $pageid = $service->add_page($template);
        $rec = new stdClass();
        $rec->name = 'Grade';
        $rec->element = 'grade';
        $rec->pageid = $pageid;
        $rec->data = json_encode([
            'gradeitem' => $gradeitem,
            'gradeformat' => (string)GRADE_DISPLAY_TYPE_REAL,
        ]);
        $rec->sequence = element_helper::get_element_sequence($pageid);
        $rec->timecreated = time();
        $rec->id = $DB->insert_record('customcert_elements', $rec);
        return [(int)$rec->id, (int)$customcert->id, (int)$course->id];
    }

    /**
     * Test render() uses mod grade when gradeitem is a cmid (get_mod_grade_info path).
     *
     * @covers \customcertelement_grade\element::render
     */
    public function test_render_mod_grade(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $gi = grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assign->id, 'courseid' => $course->id,
        ]);

        $time = time();
        $grade = new grade_grade();
        $grade->itemid = $gi->id;
        $grade->userid = $student->id;
        $grade->rawgrade = 75;
        $grade->finalgrade = 75;
        $grade->rawgrademax = 100;
        $grade->rawgrademin = 0;
        $grade->timecreated = $time;
        $grade->timemodified = $time;
        $grade->insert();

        [$elementid, $customcertid] = $this->create_customcert_setup((string)$assign->cmid);
        $DB->set_field(
            'customcert_elements',
            'data',
            json_encode(['gradeitem' => (string)$assign->cmid, 'gradeformat' => (string)GRADE_DISPLAY_TYPE_REAL]),
            ['id' => $elementid]
        );
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);

        $rec = $DB->get_record('customcert_elements', ['id' => $elementid]);
        $el = new element($rec);
        $user = $DB->get_record('user', ['id' => $student->id]);

        $pdf = new pdf();
        $pdf->AddPage();
        $el->render($pdf, false, $user);
        $this->assertTrue(true);
    }

    /**
     * Test render() uses course grade when gradeitem is GRADE_COURSE.
     *
     * @covers \customcertelement_grade\element::render
     */
    public function test_render_course_grade(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $coursegradeitem = grade_item::fetch_course_item($course->id);

        $time = time();
        $grade = new grade_grade();
        $grade->itemid = $coursegradeitem->id;
        $grade->userid = $student->id;
        $grade->rawgrade = 80;
        $grade->finalgrade = 80;
        $grade->rawgrademax = 100;
        $grade->rawgrademin = 0;
        $grade->timecreated = $time;
        $grade->timemodified = $time;
        $grade->insert();

        [$elementid, $customcertid] = $this->create_customcert_setup(element::GRADE_COURSE);
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);

        $rec = $DB->get_record('customcert_elements', ['id' => $elementid]);
        $el = new element($rec);
        $user = $DB->get_record('user', ['id' => $student->id]);

        $pdf = new pdf();
        $pdf->AddPage();
        $el->render($pdf, false, $user);
        $this->assertTrue(true);
    }

    /**
     * Test render() uses grade item when gradeitem is gradeitem:N.
     *
     * @covers \customcertelement_grade\element::render
     */
    public function test_render_gradeitem(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $gi = $this->getDataGenerator()->create_grade_item(['itemname' => 'Manual item', 'courseid' => $course->id]);
        $gi = grade_item::fetch(['id' => $gi->id]);

        $time = time();
        $grade = new grade_grade();
        $grade->itemid = $gi->id;
        $grade->userid = $student->id;
        $grade->rawgrade = 60;
        $grade->finalgrade = 60;
        $grade->rawgrademax = 100;
        $grade->rawgrademin = 0;
        $grade->timecreated = $time;
        $grade->timemodified = $time;
        $grade->insert();

        $gradeitem = 'gradeitem:' . $gi->id;
        [$elementid, $customcertid] = $this->create_customcert_setup($gradeitem);
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);

        $rec = $DB->get_record('customcert_elements', ['id' => $elementid]);
        $el = new element($rec);
        $user = $DB->get_record('user', ['id' => $student->id]);

        $pdf = new pdf();
        $pdf->AddPage();
        $el->render($pdf, false, $user);
        $this->assertTrue(true);
    }
}
