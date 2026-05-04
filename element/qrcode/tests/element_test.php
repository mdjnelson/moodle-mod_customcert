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
 * Unit tests for the qrcode element.
 *
 * @package    customcertelement_qrcode
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_qrcode;

use advanced_testcase;
use mod_customcert\element\constructable_element_interface;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use stdClass;

/**
 * Unit tests for the qrcode element.
 *
 * @package    customcertelement_qrcode
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
            'name' => 'QR code',
            'element' => 'qrcode',
            'data' => json_encode([
                'width' => 30,
                'height' => 30,
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
     * @covers \customcertelement_qrcode\element::from_record
     */
    public function test_from_record_returns_instance(): void {
        $el = element::from_record($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     *
     * @covers \customcertelement_qrcode\element
     */
    public function test_implements_interfaces(): void {
        $el = element::from_record($this->make_record());
        $this->assertInstanceOf(constructable_element_interface::class, $el);
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test that normalise_data() returns expected keys and values.
     *
     * @covers \customcertelement_qrcode\element::normalise_data
     */
    public function test_normalise_data_returns_expected_keys(): void {
        $el = element::from_record($this->make_record());
        $formdata = (object) [
            'width' => 50,
            'height' => 50,
        ];
        $result = $el->normalise_data($formdata);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
        $this->assertSame(50, $result['width']);
        $this->assertSame(50, $result['height']);
    }

    /**
     * Test that normalise_data() handles missing fields gracefully.
     *
     * @covers \customcertelement_qrcode\element::normalise_data
     */
    public function test_normalise_data_handles_missing_fields(): void {
        $el = element::from_record($this->make_record());
        $result = $el->normalise_data(new stdClass());
        $this->assertSame(0, $result['width']);
        $this->assertSame(0, $result['height']);
    }

    /**
     * Test that validate() returns an empty array.
     *
     * @covers \customcertelement_qrcode\element::validate
     */
    public function test_validate_returns_empty_array(): void {
        $el = element::from_record($this->make_record());
        $this->assertSame([], $el->validate([]));
    }

    /**
     * Test that render_html() returns empty string when no data is set.
     *
     * @covers \customcertelement_qrcode\element::render_html
     */
    public function test_render_html_empty_when_no_data(): void {
        $el = element::from_record($this->make_record(['data' => null]));
        $this->assertSame('', $el->render_html());
    }

    /**
     * Test that render_html() returns a non-empty string when data is set.
     *
     * @covers \customcertelement_qrcode\element::render_html
     */
    public function test_render_html_returns_string_with_data(): void {
        $el = element::from_record($this->make_record());
        $html = $el->render_html();
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    /**
     * Test that get_type() returns 'qrcode'.
     *
     * @covers \customcertelement_qrcode\element
     */
    public function test_get_type(): void {
        $el = element::from_record($this->make_record());
        $this->assertSame('qrcode', $el->get_type());
    }
}
