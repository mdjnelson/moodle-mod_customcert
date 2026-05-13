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
 * Minimal test element fixture for upgrade/getter tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\element;
use mod_customcert\service\element_renderer;
use stdClass;

/**
 * A minimal concrete element subclass used to test getter behaviour after migration.
 *
 * render() and render_html() are no-ops; all logic under test lives in the base class.
 */
final class minimal_test_element extends element {
    /**
     * Render into TCPDF (no-op).
     *
     * @param \pdf $pdf The PDF object to render.
     * @param bool $preview Indicates whether the render is for preview purposes.
     * @param stdClass $user The user object that contains user-specific data for rendering.
     * @param element_renderer|null $renderer An optional element renderer for custom rendering logic.
     * @return void
     */
    public function render(
        \pdf $pdf,
        bool $preview,
        stdClass $user,
        ?element_renderer $renderer = null
    ): void {
        // No-op.
    }

    /**
     * Render HTML (no-op).
     *
     * @param element_renderer|null $renderer The optional renderer to render the HTML content.
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }
}
