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
 * Rendering capability contract for element implementations.
 *
 * Elements implementing this interface can render themselves to two outputs:
 * - PDF output for issuance and preview (via {@see render()})
 * - HTML output for the drag-and-drop designer (via {@see render_html()})
 *
 * Implementations may optionally accept an {@see mod_customcert\service\element_renderer}
 * to delegate common rendering concerns while keeping the element logic focused.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

use mod_customcert\service\element_renderer;
use pdf;
use stdClass;

/**
 * Contract for elements that can render to PDF and HTML.
 *
 * Separates rendering concerns for PDF and HTML preview.
 */
interface renderable_element_interface {
    /**
     * Render the element into a PDF context.
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @param element_renderer|null $renderer
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void;

    /**
     * Render the element into HTML for the designer UI.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string;
}
