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
 * Simple PDF renderer (scaffolding only; not wired yet).
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\element\element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element_helper;
use pdf;
use stdClass;

/**
 * Simple PDF renderer.
 */
final class pdf_renderer implements element_renderer {
    /** @var pdf|null */
    private ?pdf $pdf = null;

    /**
     * Set the PDF.
     *
     * @param pdf $pdf
     */
    public function set_pdf(pdf $pdf): void {
        $this->pdf = $pdf;
    }

    /**
     * Renders PDF.
     *
     * @param element_interface $element
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @return void
     */
    public function render_pdf(element_interface $element, pdf $pdf, bool $preview, stdClass $user): void {
        if ($element instanceof renderable_element_interface) {
            $element->render($pdf, $preview, $user, $this);
            return;
        }

        // If adapter, delegate to wrapped legacy element's render().
        if ($element instanceof legacy_element_adapter) {
            $legacy = $element->get_inner();
            $legacy->render($pdf, $preview, $user, $this);
            return;
        }
    }

    /**
     * Common behaviour for rendering specified content on the pdf.
     *
     * @param element_interface $element the customcert element
     * @param string $content the content to render
     */
    public function render_content(element_interface $element, string $content): void {
        if ($this->pdf === null) {
            throw new \coding_exception('PDF object not set in pdf_renderer');
        }
        element_helper::render_content($this->pdf, $element, $content);
    }

    /**
     * Render HTML.
     *
     * @param element_interface $element
     * @return string
     */
    public function render_html(element_interface $element): string {
        return '';
    }
}
