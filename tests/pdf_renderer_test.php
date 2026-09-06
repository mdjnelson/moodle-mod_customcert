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
use coding_exception;
use context_system;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\pdf_renderer;
use pdf;

/**
 * Unit tests for the pdf_renderer class.
 *
 * pdf_renderer is already exercised indirectly through
 * pdf_generation_service::generate_pdf() (see pdf_generation_service_test.php), but that
 * only proves the overall rendering pipeline works — it never verifies pdf_renderer's own
 * contract in isolation (e.g. that render_content() requires set_pdf() first, or that
 * render_html() always returns an empty string). These tests cover that directly.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf_renderer_test extends advanced_testcase {
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
                'text' => 'Hello PDF',
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
     * render_content() must refuse to run before set_pdf() has been called.
     *
     * @covers \mod_customcert\service\pdf_renderer::render_content
     */
    public function test_render_content_throws_when_pdf_not_set(): void {
        $renderer = new pdf_renderer();
        $element = $this->create_text_element();

        $this->expectException(coding_exception::class);
        $renderer->render_content($element, 'Hello PDF');
    }

    /**
     * render_content() delegates to element_helper::render_content() once a pdf is set.
     *
     * @covers \mod_customcert\service\pdf_renderer::set_pdf
     * @covers \mod_customcert\service\pdf_renderer::render_content
     */
    public function test_render_content_delegates_once_pdf_is_set(): void {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $renderer = new pdf_renderer();
        $pdf = new pdf();
        $pdf->AddPage();
        $renderer->set_pdf($pdf);

        $element = $this->create_text_element();

        // Should not throw now that the pdf has been set.
        $renderer->render_content($element, 'Hello PDF');
        $this->assertDebuggingNotCalled();
    }

    /**
     * render_html() is not used by the PDF renderer; it must always return an empty string.
     *
     * @covers \mod_customcert\service\pdf_renderer::render_html
     */
    public function test_render_html_always_returns_empty_string(): void {
        $renderer = new pdf_renderer();
        $element = $this->create_text_element();

        $this->assertSame('', $renderer->render_html($element));
    }

    /**
     * render_pdf() delegates to the element's own render() method, passing itself through
     * as the renderer so the element can call back into render_content().
     *
     * @covers \mod_customcert\service\pdf_renderer::render_pdf
     */
    public function test_render_pdf_delegates_to_element_render(): void {
        global $CFG, $USER;
        require_once($CFG->libdir . '/pdflib.php');

        $this->setAdminUser();

        $renderer = new pdf_renderer();
        $pdf = new pdf();
        $pdf->AddPage();
        $renderer->set_pdf($pdf);

        $element = $this->create_text_element();

        $renderer->render_pdf($element, $pdf, true, $USER);
        $this->assertDebuggingNotCalled();
    }
}
