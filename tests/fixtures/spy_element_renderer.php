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
 * Spy element renderer fixture for tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\stylable_element_interface;
use mod_customcert\service\element_renderer;

/**
 * A spy element_renderer that records whether render_html() was called.
 *
 * Used in PDF regression tests to assert that render() never falls back to the HTML path.
 */
final class spy_element_renderer implements element_renderer {
    /** @var bool Whether render_html() was called. */
    public bool $called = false;

    /**
     * Render the element into a PDF context.
     *
     * @param renderable_element_interface $element
     * @param \pdf $pdf
     * @param bool $preview
     * @param \stdClass $user
     * @return void
     */
    public function render_pdf(
        renderable_element_interface $element,
        \pdf $pdf,
        bool $preview,
        \stdClass $user
    ): void {
        $element->render($pdf, $preview, $user, $this);
    }

    /**
     * Render the element into HTML; records that it was called.
     *
     * @param renderable_element_interface $element
     * @return string
     */
    public function render_html(renderable_element_interface $element): string {
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
}
