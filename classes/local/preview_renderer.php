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
use mod_customcert\service\element_factory;
use mod_customcert\service\element_renderer;
use mod_customcert\service\element_repository;
use mod_customcert\service\html_renderer;
use mod_customcert\service\pdf_renderer;
use mod_customcert\service\page_repository;
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

    /** @var page_repository */
    private page_repository $pages;
    /**
     * Create a preview_renderer with default dependencies.
     *
     * @return self
     */
    public static function create(): self {
        $factory = element_factory::build_with_defaults();
        return new self($factory, new element_repository($factory), new page_repository());
    }

    /**
     * Constructor.
     *
     * @param element_factory $factory
     * @param element_repository $repository
     * @param page_repository $pages
     */
    public function __construct(
        element_factory $factory,
        element_repository $repository,
        page_repository $pages,
    ) {
        $this->factory = $factory;
        $this->repository = $repository;
        $this->pages = $pages;
        $this->pdfrenderer = new pdf_renderer();
        $this->htmlrenderer = new html_renderer();
    }

    /**
     * Render a page's elements into the supplied PDF for preview.
     *
     * @param int $pageid
     * @param pdf $pdf
     * @param stdClass $user
     * @param bool $preview When true, renders elements in preview mode (non-persistent)
     * @return void
     * @throws dml_exception
     * @throws coding_exception
     */
    public function render_pdf_page(int $pageid, pdf $pdf, stdClass $user, bool $preview = true): void {
        // Load the page record.
        $page = $this->pages->get_by_id_or_fail($pageid);

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
            $this->pdfrenderer->render_pdf($element, $pdf, $preview, $user);
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
        $html = '';
        $elements = $this->repository->load_by_page_id($pageid);
        foreach ($elements as $element) {
            $html .= $this->htmlrenderer->render_html($element);
        }
        return $html;
    }
}
