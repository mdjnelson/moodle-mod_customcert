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
 * Unit tests for the userpicture element.
 *
 * @package    customcertelement_userpicture
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_userpicture;

use advanced_testcase;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use stdClass;
use pdf;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Unit tests for the userpicture element.
 *
 * @package    customcertelement_userpicture
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\customcertelement_userpicture\element::class)]
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
            'name' => 'User picture',
            'element' => 'userpicture',
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
     * Test that the element can be instantiated.
     */
    public function test_instantiation(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     */
    public function test_implements_interfaces(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertNotInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test that normalise_data() returns width and height.
     */
    public function test_normalise_data_returns_expected_keys(): void {
        $el = new element($this->make_record());
        $formdata = (object) ['width' => 50, 'height' => 50];
        $result = $el->normalise_data($formdata);
        $this->assertSame(50, $result['width']);
        $this->assertSame(50, $result['height']);
    }

    /**
     * Test that normalise_data() handles missing fields gracefully.
     */
    public function test_normalise_data_handles_missing_fields(): void {
        $el = new element($this->make_record());
        $result = $el->normalise_data(new stdClass());
        $this->assertSame(0, $result['width']);
        $this->assertSame(0, $result['height']);
    }

    /**
     * Test that render_html() returns empty string when no data is set.
     */
    public function test_render_html_empty_when_no_data(): void {
        $el = new element($this->make_record());
        $this->assertSame('', $el->render_html());
    }

    /**
     * Test that render_html() returns an img tag when data is set.
     */
    public function test_render_html_returns_img_tag(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $el = new element($this->make_record([
            'data' => json_encode(['width' => 0, 'height' => 0]),
        ]));
        $html = $el->render_html();
        $this->assertIsString($html);
        $this->assertStringContainsString('<img', $html);
    }

    /**
     * Test that get_name() returns the element name.
     */
    public function test_get_name(): void {
        $el = new element($this->make_record());
        $this->assertSame('User picture', $el->get_name());
    }

    /**
     * Test that render() draws the default picture on the PDF in preview mode when the user has none.
     */
    public function test_render_preview_draws_default_picture_on_pdf(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $el = new element($this->make_record(['data' => json_encode(['width' => 30, 'height' => 30])]));

        $pdf = $this->getMockBuilder(pdf::class)->disableOriginalConstructor()->onlyMethods(['Image'])->getMock();
        $pdf->expects($this->once())->method('Image');

        $el->render($pdf, true, $user);
    }

    /**
     * Test that render() draws nothing outside preview mode when the user has no picture.
     */
    public function test_render_without_picture_draws_nothing(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $el = new element($this->make_record(['data' => json_encode(['width' => 30, 'height' => 30])]));

        $pdf = $this->getMockBuilder(pdf::class)->disableOriginalConstructor()->onlyMethods(['Image'])->getMock();
        $pdf->expects($this->never())->method('Image');

        $el->render($pdf, false, $user);
    }

    /**
     * Test that render_html() wraps the picture in the styled designer container.
     */
    public function test_render_html_wraps_picture_in_styled_container(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $el = new element($this->make_record(['data' => json_encode(['width' => 30, 'height' => 30])]));

        $html = $el->render_html();

        $this->assertStringStartsWith('<div style="font-family:', $html);
        $this->assertStringContainsString('font-size: 12pt;', $html);
        $this->assertStringContainsString('<img src=', $html);
        $this->assertStringContainsString('width: 30mm; height: 30mm', $html);
        $this->assertStringEndsWith('</div>', $html);
    }
}
