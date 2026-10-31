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
 * Unit tests for the image element.
 *
 * @package    customcertelement_image
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_image;

use advanced_testcase;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use stdClass;
use context_system;
use pdf;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Unit tests for the image element.
 *
 * @package    customcertelement_image
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
            'name' => 'Image',
            'element' => 'image',
            'data' => null,
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
     * Test that the element can be instantiated from a record.
     *
     * @covers \customcertelement_image\element
     */
    public function test_instantiation(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     *
     * @covers \customcertelement_image\element
     */
    public function test_implements_interfaces(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertNotInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test that render_html() returns empty string when no data is set.
     *
     * @covers \customcertelement_image\element::render_html
     */
    public function test_render_html_empty_when_no_data(): void {
        $el = new element($this->make_record());
        $this->assertSame('', $el->render_html());
    }

    /**
     * Test that render_html() returns empty string when data has no filename.
     *
     * @covers \customcertelement_image\element::render_html
     */
    public function test_render_html_empty_when_no_filename(): void {
        $el = new element($this->make_record([
            'data' => json_encode(['width' => 100, 'height' => 50]),
        ]));
        $this->assertSame('', $el->render_html());
    }

    /**
     * Test that get_width() returns null when no data is set.
     *
     * @covers \customcertelement_image\element::get_width
     */
    public function test_get_width_null_when_no_data(): void {
        $el = new element($this->make_record());
        $this->assertNull($el->get_width());
    }

    /**
     * Test that get_width() returns the stored width value.
     *
     * @covers \customcertelement_image\element::get_width
     */
    public function test_get_width_returns_stored_value(): void {
        $el = new element($this->make_record([
            'data' => json_encode(['width' => 120, 'height' => 80]),
        ]));
        $this->assertSame(120, $el->get_width());
    }

    /**
     * Test that get_height() returns the stored height value.
     *
     * @covers \customcertelement_image\element::get_height
     */
    public function test_get_height_returns_stored_value(): void {
        $el = new element($this->make_record([
            'data' => json_encode(['width' => 120, 'height' => 80]),
        ]));
        $this->assertSame(80, $el->get_height());
    }

    /**
     * Test that has_save_and_continue() returns true.
     *
     * @covers \customcertelement_image\element::has_save_and_continue
     */
    public function test_has_save_and_continue(): void {
        $el = new element($this->make_record());
        $this->assertTrue($el->has_save_and_continue());
    }

    /**
     * Test that get_type() returns 'image'.
     *
     * @covers \customcertelement_image\element
     */
    public function test_get_type(): void {
        $el = new element($this->make_record());
        $this->assertSame('image', $el->get_type());
    }

    /**
     * Test that render() draws nothing on the PDF when no data is set.
     *
     * @covers \customcertelement_image\element::render
     */
    public function test_render_without_data_draws_nothing(): void {
        $el = new element($this->make_record());

        $pdf = $this->getMockBuilder(pdf::class)->disableOriginalConstructor()->onlyMethods(['Image', 'ImageSVG'])->getMock();
        $pdf->expects($this->never())->method('Image');
        $pdf->expects($this->never())->method('ImageSVG');

        $el->render($pdf, false, new stdClass());
    }

    /**
     * Test that render() draws a stored image on the PDF.
     *
     * @covers \customcertelement_image\element::render
     */
    public function test_render_with_stored_file_draws_image_on_pdf(): void {
        $this->resetAfterTest();
        $el = new element($this->make_record(['data' => $this->create_stored_image()]));

        $pdf = $this->getMockBuilder(pdf::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['Image', 'ImageSVG', 'SetAlpha'])
            ->getMock();
        $pdf->expects($this->once())->method('Image');
        $pdf->expects($this->never())->method('ImageSVG');

        $el->render($pdf, false, new stdClass());
    }

    /**
     * Test that render_html() wraps the image in the styled designer container.
     *
     * @covers \customcertelement_image\element::render_html
     */
    public function test_render_html_wraps_image_in_styled_container(): void {
        $this->resetAfterTest();
        $el = new element($this->make_record(['data' => $this->create_stored_image()]));

        $html = $el->render_html();

        $this->assertStringStartsWith('<div style="font-family:', $html);
        $this->assertStringContainsString('font-size: 12pt;', $html);
        $this->assertStringContainsString('<img src=', $html);
        $this->assertStringEndsWith('</div>', $html);
    }

    /**
     * Create a minimal 1x1 PNG in the system context image filearea.
     *
     * @return string the JSON element data referencing the stored file
     */
    private function create_stored_image(): string {
        $syscontextid = context_system::instance()->id;
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $syscontextid,
            'component' => 'mod_customcert',
            'filearea'  => 'image',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.png',
        ], base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));

        return json_encode([
            'contextid' => $syscontextid,
            'filearea'  => 'image',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.png',
            'width'     => 0,
            'height'    => 0,
        ]);
    }
}
