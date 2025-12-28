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
 * Preview renderer HTML test with a simple Text element.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use context_system;
use mod_customcert\local\preview_renderer;

/**
 * Tests that the preview HTML renderer produces output with a single Text element.
 */
final class preview_renderer_with_text_test extends advanced_testcase {
    /**
     * Ensure non-empty HTML is returned for a page containing a Text element.
     *
     * @covers \mod_customcert\local\preview_renderer::render_html_page
     */
    public function test_render_html_page_with_text_element(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a minimal template.
        $template = (object) [
            'name' => 'Test Template',
            'contextid' => context_system::instance()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $template->id = $DB->insert_record('customcert_templates', $template, true);

        // Create a page.
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
        $page->id = $DB->insert_record('customcert_pages', $page, true);

        // Create a Text element record using JSON data for content/visuals.
        $element = (object) [
            'element' => 'text',
            'pageid' => $page->id,
            'name' => 'Text',
            'data' => json_encode([
                'value' => 'Hello world',
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 0,
            ]),
            'posx' => 10,
            'posy' => 10,
            'refpoint' => 1,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $element->id = $DB->insert_record('customcert_elements', $element, true);

        // Render HTML via the preview orchestrator.
        $preview = new preview_renderer();
        $html = $preview->render_html_page((int)$page->id);

        $this->assertIsString($html);
        $this->assertNotEmpty($html, 'Expected non-empty HTML for a page containing a Text element.');
    }
}
