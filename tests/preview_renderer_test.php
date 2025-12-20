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
 * Tests for the preview HTML renderer.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\local\preview_renderer;

/**
 * Unit tests for the preview_renderer class.
 *
 * This test suite validates the functionality and expected behavior of the
 * preview_renderer class, ensuring its methods are working as intended under
 * various conditions.
 */
final class preview_renderer_test extends advanced_testcase {
    /**
     * Tests that the render_html_page method returns a string when rendering an empty page.
     *
     * This test verifies that the rendering method provides a result that is of type string,
     * even for a page with no elements or content. Additionally, it ensures the returned
     * string is not null and adheres to expected behavior for an empty page scenario.
     *
     * @covers \mod_customcert\local\preview_renderer::render_html_page
     * @return void
     */
    public function test_render_html_page_returns_string_for_empty_page(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a bare template and page record directly to avoid heavy setup.
        $templateid = $DB->insert_record('customcert_templates', (object) [
            'name' => 'Temp Template',
            'contextid' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $pageid = $DB->insert_record('customcert_pages', (object) [
            'templateid' => $templateid,
            'width' => 210, // Default A4 width (mm).
            'height' => 297, // Default A4 height (mm).
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $preview = new preview_renderer();
        $html = $preview->render_html_page((int)$pageid);

        $this->assertIsString($html);
        // For an empty page (no elements) we expect empty string or minimal markup from renderers.
        $this->assertNotNull($html);
    }
}
