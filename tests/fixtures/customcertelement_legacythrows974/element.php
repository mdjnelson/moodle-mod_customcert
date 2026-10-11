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
 * Fixture: element whose constructor deliberately fails (#974).
 *
 * Used to prove that a broken plugin's constructor failure propagates (or is converted
 * to null by the tolerant factory entry points), rather than being silently swallowed.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_legacythrows974;

use mod_customcert\element\renderable_element_interface;
use mod_customcert\service\element_renderer;
use pdf;
use stdClass;

/**
 * Native v2 element with a deliberately broken constructor.
 */
class element extends \mod_customcert\element implements renderable_element_interface {
    /**
     * Constructor that always throws, simulating a broken third-party plugin.
     *
     * @param \stdClass $element
     */
    public function __construct($element) {
        unset($element);
        throw new \Exception('Deliberate constructor failure for #974 regression coverage.');
    }

    /**
     * Render the element into a PDF context. Unreachable: the constructor always throws.
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @param element_renderer|null $renderer
     * @return void
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        unset($pdf, $preview, $user, $renderer);
    }

    /**
     * Render the element in HTML. Unreachable: the constructor always throws.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        unset($renderer);
        return '';
    }
}
