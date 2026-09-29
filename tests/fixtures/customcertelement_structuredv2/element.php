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
 * Fixture: third-party element fully migrated to Element System v2.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_structuredv2;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Third-party element with a custom form persistence contract.
 */
final class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    renderable_element_interface {
    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        unset($mform);
    }

    /**
     * Normalise submitted form data into the structured payload this plugin persists.
     *
     * @param stdClass $formdata
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        return ['value' => (string) ($formdata->value ?? '')];
    }

    /**
     * Render the element into a PDF context.
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
     * Render the element in HTML for the designer.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        unset($renderer);
        return (string) $this->get_value();
    }
}
