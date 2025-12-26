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
 * This file contains the customcert element border's core interaction API.
 *
 * @package    customcertelement_border
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_border;

use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;
use TCPDF_COLORS;

/**
 * The customcert element border's core interaction API.
 *
 * @package    customcertelement_border
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements element_interface, form_definable_interface, preparable_form_interface {
    /**
     * Returns the configured border width for this element.
     *
     * The Border element stores its stroke width directly in the element 'data'
     * column (as a scalar), not in the standard 'width' column. Override the
     * getter so edit forms populated via edit_element_form pick up the saved
     * value correctly when editing.
     *
     * @return int|null Width in mm, or null if not set.
     */
    public function get_width(): ?int {
        $data = $this->get_data();

        if ($data === null || $data === '') {
            return null;
        }

        // Data is stored as a scalar (the width value directly), not JSON.
        return (int) $data;
    }

    /**
     * Define the configuration fields for this element in the same order as before the refactor.
     *
     * @return array
     */
    public function get_form_fields(): array {
        // Width first, then Colour.
        return [
            'width' => [],
            'colour' => [],
        ];
    }

    /**
     * Prepare the form by populating the width field from stored data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        // Border stores width as a scalar in the data column.
        $data = $this->get_data();
        if (!empty($data)) {
            $mform->getElement('width')->setValue((int)$data);
        }
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param stdClass $user the user we are rendering this for
     * @param element_renderer|null $renderer the renderer service
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        $colour = TCPDF_COLORS::convertHTMLColorToDec($this->get_colour(), $colour);
        $pdf->SetLineStyle(['width' => $this->get_width() ?? 0, 'color' => $colour]);
        $pdf->Line(0, 0, $pdf->getPageWidth(), 0);
        $pdf->Line($pdf->getPageWidth(), 0, $pdf->getPageWidth(), $pdf->getPageHeight());
        $pdf->Line(0, $pdf->getPageHeight(), $pdf->getPageWidth(), $pdf->getPageHeight());
        $pdf->Line(0, 0, 0, $pdf->getPageHeight());
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @param element_renderer|null $renderer the renderer service
     * @return string the html
     */
    public function render_html(?element_renderer $renderer = null): string {
        if ($renderer) {
            return (string) $renderer->render_content($this, '');
        }

        return '';
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        // Persist a plain scalar width in the data column for compatibility with pre-refactor behaviour.
        return isset($data->width) ? (int)$data->width : 0;
    }
}
