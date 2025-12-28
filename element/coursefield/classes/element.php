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
 * This file contains the customcert element coursefield's core interaction API.
 *
 * @package    customcertelement_coursefield
 * @copyright  2019 Catalyst IT
 * @author     Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_coursefield;

use core_collator;
use core_course\customfield\course_handler;
use mod_customcert\element\field_type;
use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * The customcert element coursefield's core interaction API.
 *
 * @package    customcertelement_coursefield
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements element_interface, form_definable_interface, preparable_form_interface {
    /**
     * Define the configuration fields for this element.
     *
     * @return array
     */
    public function get_form_fields(): array {
        // Get the course fields.
        $coursefields = [
            'fullname' => get_string('fullnamecourse'),
            'shortname' => get_string('shortnamecourse'),
            'idnumber' => get_string('idnumbercourse'),
        ];
        // Get the course custom fields.
        $handler = course_handler::create();
        $customfields = $handler->get_fields();
        $arrcustomfields = [];
        foreach ($customfields as $field) {
            $arrcustomfields[$field->get('id')] = $field->get_formatted_name();
        }

        // Combine the two.
        $fields = $coursefields + $arrcustomfields;
        core_collator::asort($fields);

        return [
            'coursefield' => [
                'type' => field_type::select,
                'label' => get_string('coursefield', 'customcertelement_coursefield'),
                'options' => $fields,
                'help' => ['coursefield', 'customcertelement_coursefield'],
                'type_param' => PARAM_ALPHANUM,
            ],
            // Standard fields expected on this form.
            'font' => [],
            'colour' => [],
            'width' => [],
            'refpoint' => [],
            'alignment' => [],
        ];
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param stdClass $data the form data
     * @return string the text
     */
    public function save_unique_data($data) {
        return $data->coursefield;
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
        $courseid = element_helper::get_courseid($this->id);
        $course = get_course($courseid);
        $value = $this->get_course_field_value($course, $preview);

        if ($renderer) {
            $renderer->render_content($this, $value);
        } else {
            element_helper::render_content($pdf, $this, $value);
        }
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @param element_renderer|null $renderer the renderer service
     */
    public function render_html(?element_renderer $renderer = null): string {
        global $COURSE;

        $value = $this->get_course_field_value($COURSE, true);
        if ($renderer) {
            return (string) $renderer->render_content($this, $value);
        }

        return element_helper::render_html_content($this, $value);
    }

    /**
     * Prepare the form by populating the coursefield field from stored data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        if (!empty($this->get_data())) {
            $mform->getElement('coursefield')->setValue($this->get_data());
        }
    }

    /**
     * Helper function that returns the field value in a human-readable format.
     *
     * @param stdClass $course the course we are rendering this for
     * @param bool $preview Is this a preview?
     * @return string
     */
    protected function get_course_field_value(stdClass $course, bool $preview): string {
        // The user field to display.
        $field = $this->get_data();
        // The value to display - we always want to show a value here so it can be repositioned.
        if ($preview) {
            $value = $field;
        } else {
            $value = '';
        }
        if (is_number($field)) { // Must be a custom course profile field.
            $handler = course_handler::create();
            $data = $handler->get_instance_data($course->id, true);
            if ($preview && empty($data[$field]->export_value())) {
                $fields = $handler->get_fields();
                $value = $fields[$field]->get('shortname');
            } else if (!empty($data[$field])) {
                $value = $data[$field]->export_value();
            }
        } else if (!empty($course->$field)) { // Field in the course table.
            $value = $course->$field;
        }

        $context = element_helper::get_context($this->get_id());
        return format_string($value, true, ['context' => $context]);
    }
}
