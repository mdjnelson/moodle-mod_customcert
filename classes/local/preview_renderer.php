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
 * Orchestrator for V2 preview rendering (registry -> factory -> renderer).
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\local;

use coding_exception;
use dml_exception;
use mod_customcert\element\element_bootstrap;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_renderer;
use mod_customcert\service\element_repository;
use mod_customcert\service\html_renderer;
use mod_customcert\service\pdf_renderer;
use pdf;
use stdClass;

/**
 * Preview orchestrator for the new element pipeline.
 *
 * This class is BC-safe and does not affect normal runtime; callers must
 * explicitly invoke it to use the new path.
 */
final class preview_renderer {
    /** @var element_factory */
    private element_factory $factory;

    /** @var element_renderer */
    private element_renderer $pdfrenderer;

    /** @var element_renderer */
    private element_renderer $htmlrenderer;

    /** @var element_repository */
    private element_repository $repository;

    /**
     * Constructor with optional DI for factory.
     *
     * @param element_factory|null $factory Optional injected factory (useful for tests)
     * @param element_repository|null $repository Optional injected factory (useful for tests)
     */
    public function __construct(?element_factory $factory = null, ?element_repository $repository = null) {
        if ($factory) {
            $this->factory = $factory;
        } else {
            $registry = new element_registry();
            element_bootstrap::register_defaults($registry);
            $this->factory = new element_factory($registry);
        }
        $this->repository = $repository ?? new element_repository($this->factory);
        $this->pdfrenderer = new pdf_renderer();
        $this->htmlrenderer = new html_renderer();
    }

    /**
     * Render a page's elements into the supplied PDF for preview.
     *
     * @param int $pageid
     * @param pdf $pdf
     * @param stdClass $user
     * @return void
     * @throws dml_exception
     * @throws coding_exception
     */
    public function render_pdf_page(int $pageid, pdf $pdf, stdClass $user): void {
        global $DB;

        // Load the page record.
        $page = $DB->get_record('customcert_pages', ['id' => $pageid], '*', MUST_EXIST);

        // Determine orientation.
        $orientation = ($page->width > $page->height) ? 'L' : 'P';

        // IMPORTANT: add page BEFORE rendering elements. TCPDF requires a page to exist
        // before any write operations; otherwise, methods like writeHTMLCell() can error.
        $pdf->AddPage($orientation, [$page->width, $page->height]);

        // Set margins (match legacy behaviour).
        $pdf->SetMargins($page->leftmargin ?? 0, 0, $page->rightmargin ?? 0);
        // Reset cursor to top-left after adding the page and setting margins.
        $pdf->SetXY(0, 0);

        // Load elements via repository and render.
        $elements = $this->repository->load_by_page_id($pageid);
        if ($this->pdfrenderer instanceof pdf_renderer) {
            $this->pdfrenderer->set_pdf($pdf);
        }
        foreach ($elements as $element) {
            $this->pdfrenderer->render_pdf($element, $pdf, true, $user);
        }
    }

    /**
     * Render a page's elements into HTML for design-time preview.
     *
     * @param int $pageid
     * @return string Concatenated HTML of all elements.
     * @throws dml_exception
     * @throws coding_exception
     */
    public function render_html_page(int $pageid): string {
        global $DB;

        $html = '';
        $elements = $this->repository->load_by_page_id($pageid);
        foreach ($elements as $element) {
            $html .= $this->htmlrenderer->render_html($element);
        }
        return $html;
    }
}
