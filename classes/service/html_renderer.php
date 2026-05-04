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
 * HTML renderer for the element designer preview.
 *
 * Implements {@see element_renderer} to render elements to HTML used by the
 * drag-and-drop designer UI. Elements that implement
 * {@see mod_customcert\element\renderable_element_interface} can delegate to
 * this renderer via their {@see render_html()} method. Legacy elements are
 * supported through the {@see mod_customcert\element\legacy_element_adapter}.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\stylable_element_interface;
use mod_customcert\element\layout_element_interface;
use mod_customcert\element_helper;
use pdf;
use stdClass;

/**
 * HTML renderer implementation.
 */
final class html_renderer implements element_renderer {
    /**
     * Renders PDF.
     *
     * No-op in the HTML renderer — PDF output is handled by pdf_renderer.
     *
     * @param renderable_element_interface $element
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @return void
     */
    public function render_pdf(renderable_element_interface $element, pdf $pdf, bool $preview, stdClass $user): void {
        // No-op in HTML renderer.
    }

    /**
     * Render HTML.
     *
     * @param renderable_element_interface $element
     * @return string
     */
    public function render_html(renderable_element_interface $element): string {
        return $element->render_html($this);
    }

    /**
     * Common behaviour for rendering specified content on the drag and drop page.
     *
     * @param stylable_element_interface $element the customcert element (must also implement layout_element_interface)
     * @param string $content the content to render
     * @return string the html
     */
    public function render_content(
        stylable_element_interface&layout_element_interface $element,
        string $content
    ): string {
        return element_helper::render_html_content($element, $content);
    }
}
