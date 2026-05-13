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
use mod_customcert\element\stylable_element_interface;
use mod_customcert\service\element_renderer;
use stdClass;

/**
 * Unit tests for the userpicture element.
 *
 * @package    customcertelement_userpicture
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
     *
     * @covers \customcertelement_userpicture\element
     */
    public function test_instantiation(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     *
     * @covers \customcertelement_userpicture\element
     */
    public function test_implements_interfaces(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test that normalise_data() returns width and height.
     *
     * @covers \customcertelement_userpicture\element::normalise_data
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
     *
     * @covers \customcertelement_userpicture\element::normalise_data
     */
    public function test_normalise_data_handles_missing_fields(): void {
        $el = new element($this->make_record());
        $result = $el->normalise_data(new stdClass());
        $this->assertSame(0, $result['width']);
        $this->assertSame(0, $result['height']);
    }

    /**
     * Test that validate() returns an empty array.
     *
     * @covers \customcertelement_userpicture\element::validate
     */
    public function test_validate_returns_empty_array(): void {
        $el = new element($this->make_record());
        $this->assertSame([], $el->validate([]));
    }

    /**
     * Test that render_html() returns empty string when no data is set.
     *
     * @covers \customcertelement_userpicture\element::render_html
     */
    public function test_render_html_empty_when_no_data(): void {
        $el = new element($this->make_record());
        $this->assertSame('', $el->render_html());
    }

    /**
     * Test that render_html() returns an img tag when data is set.
     *
     * @covers \customcertelement_userpicture\element::render_html
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
     *
     * @covers \customcertelement_userpicture\element
     */
    public function test_get_name(): void {
        $el = new element($this->make_record());
        $this->assertSame('User picture', $el->get_name());
    }

    /**
     * PDF regression: render() must never fall back to the HTML render path when
     * a pdf_renderer passes itself in as the renderer argument.
     *
     * @covers \customcertelement_userpicture\element::render
     */
    public function test_render_with_pdf_renderer_does_not_call_render_html(): void {
        $el = new element($this->make_record());

        // Build a spy renderer that fails the test if render_html() is ever called.
        $renderer = new class implements element_renderer {
            /** @var bool */
            public bool $called = false;
            /**
             * Render the element into a PDF context.
             *
             * @param \mod_customcert\element\renderable_element_interface $element
             * @param \pdf $pdf
             * @param bool $preview
             * @param \stdClass $user
             * @return void
             */
            public function render_pdf(
                \mod_customcert\element\renderable_element_interface $element,
                \pdf $pdf,
                bool $preview,
                \stdClass $user
            ): void {
                $element->render($pdf, $preview, $user, $this);
            }
            /**
             * Render the element into HTML; records that it was called.
             *
             * @param \mod_customcert\element\renderable_element_interface $element
             * @return string
             */
            public function render_html(\mod_customcert\element\renderable_element_interface $element): string {
                $this->called = true;
                return '';
            }
            /**
             * Render common content (no-op spy).
             *
             * @param stylable_element_interface $element The element. Must also implement layout_element_interface.
             * @param string $content
             * @return void
             */
            public function render_content(
                stylable_element_interface $element,
                string $content
            ): void {
            }
        };

        $pdf = $this->getMockBuilder(\pdf::class)->disableOriginalConstructor()->getMock();
        $el->render($pdf, false, new stdClass(), $renderer);

        $this->assertFalse($renderer->called, 'render() must not call render_html() on the renderer');
    }
}
