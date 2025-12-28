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
 * This file contains the customcert element text's core interaction API.
 *
 * @package    customcertelement_text
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_text;

use mod_customcert\element\field_type;
use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * The customcert element text's core interaction API.
 *
 * @package    customcertelement_text
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    element_interface,
    form_definable_interface,
    preparable_form_interface,
    renderable_element_interface
{
    /**
     * Define the configuration fields for this element.
     *
     * @return array
     */
    public function get_form_fields(): array {
        return [
            'text' => [
                'type' => field_type::textarea,
                'label' => get_string('text', 'customcertelement_text'),
                'help' => ['text', 'customcertelement_text'],
                'type_param' => PARAM_RAW,
            ],
            // Standard fields used by Text before the refactor.
            'font' => [],
            'colour' => [],
            'posx' => [],
            'posy' => [],
            'width' => [],
            'refpoint' => [],
            'alignment' => [],
        ];
    }

    /**
     * Prepare the form by populating the text field from stored data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        // Text content is stored in JSON data under the 'value' key once visuals are merged.
        $raw = $this->get_data();
        if ($raw === null || $raw === '') {
            return;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw);
            if (is_object($decoded) && property_exists($decoded, 'value')) {
                $mform->getElement('text')->setValue((string)$decoded->value);
                return;
            }
            // Fallback for plain string storage (before visuals were merged).
            $mform->getElement('text')->setValue($raw);
        }
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param stdClass $data the form data
     * @return string the text
     */
    public function save_unique_data($data) {
        return $data->text;
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
        if ($renderer) {
            $renderer->render_content($this, $this->get_text());
        } else {
            element_helper::render_content($pdf, $this, $this->get_text());
        }
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
            return (string) $renderer->render_content($this, $this->get_text());
        }

        return element_helper::render_html_content($this, $this->get_text());
    }


    /**
     * Helper function that returns the text.
     *
     * @return string
     */
    protected function get_text(): string {
        $context = element_helper::get_context($this->get_id());
        $raw = $this->get_data();
        $content = '';
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw);
            if (is_object($decoded) && property_exists($decoded, 'value')) {
                $content = (string)$decoded->value;
            } else {
                $content = $raw;
            }
        }
        return format_text($content, FORMAT_HTML, ['context' => $context]);
    }
}
