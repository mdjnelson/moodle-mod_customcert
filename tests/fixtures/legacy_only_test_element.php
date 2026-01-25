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

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\element;
use mod_customcert\service\element_renderer;
use pdf;
use stdClass;

/**
 * Test fixture: legacy-only element without constructable interface.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class legacy_only_test_element extends element {
    /**
     * Render the element to PDF (no-op in tests).
     *
     * @param pdf $pdf PDF engine
     * @param bool $preview Preview flag
     * @param stdClass $user User
     * @param element_renderer|null $renderer Optional renderer
     * @return void
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        // No-op for tests.
    }

    /**
     * Render the element to HTML (no-op in tests).
     *
     * @param element_renderer|null $renderer Optional renderer
     * @return string Empty HTML
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }
}
