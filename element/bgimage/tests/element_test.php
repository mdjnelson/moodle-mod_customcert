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
 * Unit tests for the bgimage element.
 *
 * @package    customcertelement_bgimage
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_bgimage;

use advanced_testcase;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use stdClass;

/**
 * Unit tests for the bgimage element.
 *
 * @package    customcertelement_bgimage
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
            'name' => 'Background image',
            'element' => 'bgimage',
            'data' => null,
            'font' => null,
            'fontsize' => null,
            'colour' => null,
            'posx' => 0,
            'posy' => 0,
            'width' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $override);
    }

    /**
     * Test that the element can be instantiated.
     *
     * @covers \customcertelement_bgimage\element
     */
    public function test_instantiation(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     *
     * @covers \customcertelement_bgimage\element
     */
    public function test_implements_interfaces(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test that validate() returns an empty array.
     *
     * @covers \customcertelement_bgimage\element::validate
     */
    public function test_validate_returns_empty_array(): void {
        $el = new element($this->make_record());
        $this->assertSame([], $el->validate([]));
    }

    /**
     * Test that render_html() returns empty string when no data is set.
     *
     * @covers \customcertelement_bgimage\element::render_html
     */
    public function test_render_html_empty_when_no_data(): void {
        $el = new element($this->make_record());
        $this->assertSame('', $el->render_html());
    }

    /**
     * Test that render_html() returns empty string when data has no filename.
     *
     * @covers \customcertelement_bgimage\element::render_html
     */
    public function test_render_html_empty_when_no_filename(): void {
        $el = new element($this->make_record([
            'data' => json_encode(['contextid' => 1, 'filearea' => 'image', 'itemid' => 0]),
        ]));
        $this->assertSame('', $el->render_html());
    }

    /**
     * Test that get_width() returns 0 (bgimage always auto-fits the page).
     *
     * @covers \customcertelement_bgimage\element::get_width
     */
    public function test_get_width_returns_zero(): void {
        $el = new element($this->make_record());
        $this->assertSame(0, $el->get_width());
    }

    /**
     * Test that get_height() returns 0 (bgimage always auto-fits the page).
     *
     * @covers \customcertelement_bgimage\element::get_height
     */
    public function test_get_height_returns_zero(): void {
        $el = new element($this->make_record());
        $this->assertSame(0, $el->get_height());
    }

    /**
     * Test that has_save_and_continue() returns true.
     *
     * @covers \customcertelement_bgimage\element::has_save_and_continue
     */
    public function test_has_save_and_continue(): void {
        $el = new element($this->make_record());
        $this->assertTrue($el->has_save_and_continue());
    }

    /**
     * Test that get_type() returns 'bgimage'.
     *
     * @covers \customcertelement_bgimage\element
     */
    public function test_get_type(): void {
        $el = new element($this->make_record());
        $this->assertSame('bgimage', $el->get_type());
    }
}
