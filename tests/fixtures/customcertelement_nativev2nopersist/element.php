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
 * Fixture: native third-party element with no custom form persistence.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_nativev2nopersist;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\renderable_element_interface;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Native third-party fixture without custom form persistence.
 */
final class element extends base_element implements
    form_element_interface,
    renderable_element_interface {
    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    #[\Override]
    public function build_form(MoodleQuickForm $mform): void {
        unset($mform);
    }

    /**
     * Render the element into a PDF context.
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @return void
     */
    #[\Override]
    public function render(pdf $pdf, bool $preview, stdClass $user): void {
        unset($pdf, $preview, $user);
    }

    /**
     * Render the element in HTML for the designer.
     *
     * @return string
     */
    #[\Override]
    public function render_html(): string {
        return '';
    }
}
