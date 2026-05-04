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
 * Unit tests for the userfield element.
 *
 * @package    customcertelement_userfield
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_userfield;

use advanced_testcase;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use stdClass;

/**
 * Unit tests for the userfield element.
 *
 * @package    customcertelement_userfield
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
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
            'name' => 'User field',
            'element' => 'userfield',
            'data' => json_encode([
                'userfield' => 'email',
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
     * Test that from_record() returns an instance of element.
     *
     * @covers \customcertelement_userfield\element
     */
    public function test_instantiation(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     *
     * @covers \customcertelement_userfield\element
     */
    public function test_implements_interfaces(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test that normalise_data() returns expected keys and values.
     *
     * @covers \customcertelement_userfield\element::normalise_data
     */
    public function test_normalise_data_returns_expected_keys(): void {
        $el = new element($this->make_record());
        $formdata = (object) [
            'userfield' => 'city',
            'font' => 'helvetica',
            'fontsize' => 14,
            'colour' => '#ff0000',
            'width' => 100,
        ];
        $result = $el->normalise_data($formdata);
        $this->assertSame('city', $result['userfield']);
        $this->assertSame('helvetica', $result['font']);
        $this->assertSame(14, $result['fontsize']);
        $this->assertSame('#ff0000', $result['colour']);
        $this->assertSame(100, $result['width']);
    }

    /**
     * Test that normalise_data() handles missing fields gracefully.
     *
     * @covers \customcertelement_userfield\element::normalise_data
     */
    public function test_normalise_data_handles_missing_fields(): void {
        $el = new element($this->make_record());
        $result = $el->normalise_data(new stdClass());
        $this->assertSame('', $result['userfield']);
        $this->assertSame('', $result['font']);
        $this->assertSame(0, $result['fontsize']);
        $this->assertSame('', $result['colour']);
        $this->assertSame(0, $result['width']);
    }

    /**
     * Test that validate() returns an empty array.
     *
     * @covers \customcertelement_userfield\element::validate
     */
    public function test_validate_returns_empty_array(): void {
        $el = new element($this->make_record());
        $this->assertSame([], $el->validate([]));
    }

    /**
     * Helper to create a real DB-backed element record.
     *
     * @param array $override
     * @return \stdClass
     */
    private function make_db_record(array $override = []): \stdClass {
        global $DB;
        $contextid = \context_system::instance()->id;
        $template = (object) [
            'name' => 'Test', 'contextid' => $contextid,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $template->id = $DB->insert_record('customcert_templates', $template);
        $page = (object) [
            'templateid' => $template->id, 'width' => 210, 'height' => 297,
            'leftmargin' => 0, 'rightmargin' => 0,
            'sequence' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $page->id = $DB->insert_record('customcert_pages', $page);
        $record = $this->make_record(array_merge(['pageid' => $page->id], $override));
        $record->id = $DB->insert_record('customcert_elements', $record);
        return $record;
    }

    /**
     * Test that render_html() returns the user's email in preview mode.
     *
     * @covers \customcertelement_userfield\element::render_html
     */
    public function test_render_html_returns_user_field_value(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'test@example.com']);
        $this->setUser($user);

        $el = new element($this->make_db_record());
        $html = $el->render_html();
        $this->assertIsString($html);
        $this->assertStringContainsString('test@example.com', $html);
    }

    /**
     * Test that render_html() returns the field name when user field is empty.
     *
     * @covers \customcertelement_userfield\element::render_html
     */
    public function test_render_html_returns_field_name_when_empty(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['city' => '']);
        $this->setUser($user);

        $el = new element($this->make_db_record([
            'data' => json_encode([
                'userfield' => 'city',
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 0,
            ]),
        ]));
        $html = $el->render_html();
        $this->assertIsString($html);
        // In preview mode with empty field, the field name 'city' is shown.
        $this->assertStringContainsString('city', $html);
    }

    /**
     * Test that get_type() returns 'userfield'.
     *
     * @covers \customcertelement_userfield\element
     */
    public function test_get_type(): void {
        $el = new element($this->make_record());
        $this->assertSame('userfield', $el->get_type());
    }
}
