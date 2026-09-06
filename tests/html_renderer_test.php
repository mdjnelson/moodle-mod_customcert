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

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use context_system;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\html_renderer;
use pdf;

/**
 * Unit tests for the html_renderer class.
 *
 * html_renderer is already exercised indirectly through
 * preview_renderer::render_html_page() (see preview_renderer_test.php), but that only
 * proves the overall designer-preview pipeline works — it never verifies html_renderer's
 * own contract in isolation (e.g. that render_pdf() is a genuine no-op). These tests cover
 * that directly.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class html_renderer_test extends advanced_testcase {
    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        parent::setUp();
    }

    /**
     * Create a real text element instance backed by a DB record.
     *
     * @return renderable_element_interface
     */
    private function create_text_element(): renderable_element_interface {
        global $DB;

        $templateid = $DB->insert_record('customcert_templates', (object) [
            'name' => 'Test template',
            'contextid' => context_system::instance()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $pageid = $DB->insert_record('customcert_pages', (object) [
            'templateid' => $templateid,
            'width' => 210,
            'height' => 297,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'text',
            'name' => 'Test element',
            'data' => json_encode([
                'text' => 'Hello HTML',
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 0,
            ]),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $record = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);

        return element_factory::build_with_defaults()->create_from_record($record);
    }

    /**
     * render_pdf() must be a genuine no-op: passing a pdf object with no page added must not
     * throw, which it would if the renderer tried to actually write content to it.
     *
     * @covers \mod_customcert\service\html_renderer::render_pdf
     */
    public function test_render_pdf_is_a_noop(): void {
        global $CFG, $USER;
        require_once($CFG->libdir . '/pdflib.php');

        $renderer = new html_renderer();
        $pdf = new pdf();
        $element = $this->create_text_element();

        // No AddPage() call: if render_pdf() were not a no-op, writing to a pageless
        // pdf would throw.
        $renderer->render_pdf($element, $pdf, true, $USER);
        $this->assertDebuggingNotCalled();
    }

    /**
     * render_html() delegates to the element's own render_html() method.
     *
     * @covers \mod_customcert\service\html_renderer::render_html
     */
    public function test_render_html_delegates_to_element(): void {
        $renderer = new html_renderer();
        $element = $this->create_text_element();

        $html = $renderer->render_html($element);

        $this->assertIsString($html);
        $this->assertStringContainsString('Hello HTML', $html);
    }

    /**
     * render_content() delegates to element_helper::render_html_content().
     *
     * @covers \mod_customcert\service\html_renderer::render_content
     */
    public function test_render_content_delegates_to_element_helper(): void {
        $renderer = new html_renderer();
        $element = $this->create_text_element();

        $html = $renderer->render_content($element, 'Sample content');

        $this->assertIsString($html);
        $this->assertStringContainsString('Sample content', $html);
        $this->assertStringContainsString('<div', $html);
    }
}
