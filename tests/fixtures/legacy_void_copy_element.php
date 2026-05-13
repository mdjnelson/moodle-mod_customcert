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
 * Fixture: legacy element whose copy_element() override returns void (no return statement).
 *
 * Simulates a third-party element plugin written before 5.2 whose copy_element()
 * does not return anything. The copy should be treated as success (not failure).
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\tests\fixtures;

use mod_customcert\element;
use pdf;
use mod_customcert\service\element_renderer;

/**
 * Legacy element fixture whose copy_element() returns void/null.
 */
class legacy_void_copy_element extends element {
    /**
     * Render method uses the required 5.2 typed signature.
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param \stdClass $user
     * @param element_renderer|null $renderer
     */
    public function render(pdf $pdf, bool $preview, \stdClass $user, ?element_renderer $renderer = null): void {
        // No-op fixture.
    }

    /**
     * Render HTML method uses the required 5.2 typed signature.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }

    /**
     * Old-style copy_element — no return statement (void-returning legacy implementation).
     *
     * @param mixed $data
     */
    public function copy_element($data) {
        // Do some copy work but return nothing — simulates old third-party code.
    }

    /**
     * Whether this element can be added.
     *
     * @return bool
     */
    public static function can_add(): bool {
        return true;
    }
}
