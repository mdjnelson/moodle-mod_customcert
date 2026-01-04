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
 * Fixture element class used for discovery tests via class_alias.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_element_fixture extends element {
    /**
     * Render the element to PDF (no-op in fixture).
     *
     * @param pdf $pdf The PDF instance
     * @param bool $preview Preview mode flag
     * @param stdClass $user The user record
     * @param element_renderer|null $renderer Optional renderer
     * @return void
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
    }

    /**
     * Render the element to HTML (empty in fixture).
     *
     * @param element_renderer|null $renderer Optional renderer
     * @return string HTML
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }

    /**
     * Return form field definitions (empty in fixture).
     *
     * @return array
     */
    public function get_form_fields(): array {
        return [];
    }

    /**
     * Normalise data for persistence (empty in fixture).
     *
     * @param stdClass $formdata
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        return [];
    }
}
