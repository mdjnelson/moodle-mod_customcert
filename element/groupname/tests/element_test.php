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
 * Unit tests for the groupname element.
 *
 * @package    customcertelement_groupname
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_groupname;

use advanced_testcase;
use context_module;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use stdClass;

/**
 * Unit tests for the groupname element.
 *
 * @package    customcertelement_groupname
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
            'name' => 'Group name',
            'element' => 'groupname',
            'data' => json_encode([
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 0,
            ]),
            'posx' => 10,
            'posy' => 10,
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
     * @covers \customcertelement_groupname\element::__construct
     */
    public function test_constructor_returns_instance(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     *
     * @covers \customcertelement_groupname\element
     */
    public function test_implements_interfaces(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test that normalise_data() returns expected keys.
     *
     * @covers \customcertelement_groupname\element::normalise_data
     */
    public function test_normalise_data_returns_expected_keys(): void {
        $el = new element($this->make_record());
        $formdata = (object) [
            'font' => 'helvetica',
            'fontsize' => 14,
            'colour' => '#ff0000',
            'width' => 100,
        ];
        $result = $el->normalise_data($formdata);
        $this->assertSame('helvetica', $result['font']);
        $this->assertSame(14, $result['fontsize']);
        $this->assertSame('#ff0000', $result['colour']);
        $this->assertSame(100, $result['width']);
    }

    /**
     * Test that normalise_data() handles missing fields gracefully.
     *
     * @covers \customcertelement_groupname\element::normalise_data
     */
    public function test_normalise_data_handles_missing_fields(): void {
        $el = new element($this->make_record());
        $result = $el->normalise_data(new stdClass());
        $this->assertSame('', $result['font']);
        $this->assertSame(0, $result['fontsize']);
        $this->assertSame('', $result['colour']);
        $this->assertSame(0, $result['width']);
    }

    /**
     * Test that validate() returns an empty array.
     *
     * @covers \customcertelement_groupname\element::validate
     */
    public function test_validate_returns_empty_array(): void {
        $el = new element($this->make_record());
        $this->assertSame([], $el->validate([]));
    }

    /**
     * Test that get_group_name() returns the group name for a user in a group.
     *
     * @covers \customcertelement_groupname\element::get_group_name
     */
    public function test_get_group_name_returns_group_name(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Test Group']);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);

        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = $DB->get_record(
            'customcert_templates',
            ['contextid' => context_module::instance($customcert->cmid)->id]
        );

        $page = (object) [
            'templateid' => $template->id,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $page->id = $DB->insert_record('customcert_pages', $page);

        $record = $this->make_record(['pageid' => $page->id]);
        $record->id = $DB->insert_record('customcert_elements', $record);

        $el = new element($record);
        $result = $this->call_get_group_name($el, $user);
        $this->assertSame('Test Group', $result);
    }

    /**
     * Test that get_group_name() returns empty string when user is in no groups.
     *
     * @covers \customcertelement_groupname\element::get_group_name
     */
    public function test_get_group_name_returns_empty_when_no_group(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = $DB->get_record(
            'customcert_templates',
            ['contextid' => context_module::instance($customcert->cmid)->id]
        );

        $page = (object) [
            'templateid' => $template->id,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $page->id = $DB->insert_record('customcert_pages', $page);

        $record = $this->make_record(['pageid' => $page->id]);
        $record->id = $DB->insert_record('customcert_elements', $record);

        $el = new element($record);
        $result = $this->call_get_group_name($el, $user);
        $this->assertSame('', $result);
    }

    /**
     * Test that get_group_name() returns correct group names for two students:
     * one in two groups and one in a single group.
     *
     * @covers \customcertelement_groupname\element::get_group_name
     */
    public function test_get_group_name_returns_correct_names_for_multiple_students(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student1->id, $course->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id);

        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group Alpha']);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group Beta']);

        // Student 1 is in both groups.
        $this->getDataGenerator()->create_group_member(['groupid' => $group1->id, 'userid' => $student1->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group2->id, 'userid' => $student1->id]);

        // Student 2 is only in group 1.
        $this->getDataGenerator()->create_group_member(['groupid' => $group1->id, 'userid' => $student2->id]);

        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = $DB->get_record(
            'customcert_templates',
            ['contextid' => context_module::instance($customcert->cmid)->id]
        );

        $page = (object) [
            'templateid' => $template->id,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $page->id = $DB->insert_record('customcert_pages', $page);

        $record = $this->make_record(['pageid' => $page->id]);
        $record->id = $DB->insert_record('customcert_elements', $record);

        $el = new element($record);

        // Check student 1 (in 2 groups): both group names should appear.
        $result1 = $this->call_get_group_name($el, $student1);
        $this->assertStringContainsString('Group Alpha', $result1);
        $this->assertStringContainsString('Group Beta', $result1);

        // Check student 2 (in 1 group): only Group Alpha should appear.
        $result2 = $this->call_get_group_name($el, $student2);
        $this->assertStringContainsString('Group Alpha', $result2);
        $this->assertStringNotContainsString('Group Beta', $result2);
    }

    /**
     * Helper to call the protected get_group_name() method via reflection.
     *
     * @param element $el
     * @param stdClass $user
     * @return string
     */
    private function call_get_group_name(element $el, stdClass $user): string {
        $method = new \ReflectionMethod(element::class, 'get_group_name');
        $method->setAccessible(true);
        return $method->invoke($el, $user);
    }

    /**
     * Test that get_type() returns 'groupname'.
     *
     * @covers \customcertelement_groupname\element
     */
    public function test_get_type(): void {
        $el = new element($this->make_record());
        $this->assertSame('groupname', $el->get_type());
    }
}
