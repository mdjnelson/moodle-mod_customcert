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
 * This file contains the customcert element coursename's core interaction API.
 *
 * @package    customcertelement_coursename
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_coursename;

use mod_customcert\element\field_type;
use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\dynamic_selects_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * The customcert element coursename's core interaction API.
 *
 * @package    customcertelement_coursename
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    dynamic_selects_interface,
    element_interface,
    form_definable_interface,
    preparable_form_interface
{
    /**
     * The course short name.
     */
    public const int COURSE_SHORT_NAME = 1;

    /**
     * The course fullname.
     */
    public const int COURSE_FULL_NAME = 2;

    /**
     * Define the configuration fields for this element.
     *
     * @return array
     */
    public function get_form_fields(): array {
        return [
            'coursenamedisplay' => [
                'type' => field_type::select,
                'label' => get_string('coursenamedisplay', 'customcertelement_coursename'),
                'help' => ['coursenamedisplay', 'customcertelement_coursename'],
                'type_param' => PARAM_INT,
            ],
            // Standard fields expected on this form (include colour picker etc.).
            'font' => [],
            'colour' => [],
            'width' => [],
            'refpoint' => [],
            'alignment' => [],
        ];
    }

    /**
     * Advertise dynamic selects to be populated centrally by the form service.
     *
     * @return array
     */
    public function get_dynamic_selects(): array {
        return [
            'coursenamedisplay' => [self::class, 'get_course_name_display_options'],
        ];
    }

    /**
     * Ensures the coursenamedisplay select shows the stored value on edit and
     * options are refreshed each render.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        // Preselect the stored value if present (options populated centrally).
        $value = $this->get_selected_display_field();
        if ($value !== null && $mform->elementExists('coursenamedisplay')) {
            $mform->getElement('coursenamedisplay')->setValue($value);
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
        return $data->coursenamedisplay;
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
        element_helper::render_content($pdf, $this, $this->get_course_name_detail());
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
        return element_helper::render_html_content($this, $this->get_course_name_detail());
    }


    /**
     * Helper function that returns the selected course name detail (i.e. name or short description) for display.
     *
     * @return string
     */
    protected function get_course_name_detail(): string {
        $courseid = element_helper::get_courseid($this->get_id());
        $course = get_course($courseid);
        $context = element_helper::get_context($this->get_id());

        // The name field to display.
        $field = $this->get_selected_display_field() ?? self::COURSE_FULL_NAME;
        // The name value to display.
        $value = $course->fullname;
        if ($field == self::COURSE_SHORT_NAME) {
            $value = $course->shortname;
        }

        return format_string($value, true, ['context' => $context]);
    }

    /**
     * Helper function to return all the possible name display options.
     *
     * @return array returns an array of name options
     */
    public static function get_course_name_display_options(): array {
        return [
            self::COURSE_FULL_NAME => get_string('coursefullname', 'customcertelement_coursename'),
            self::COURSE_SHORT_NAME => get_string('courseshortname', 'customcertelement_coursename'),
        ];
    }

    /**
     * Resolve the selected display field from stored data, handling JSON-wrapped scalars.
     *
     * @return int|null One of COURSE_FULL_NAME or COURSE_SHORT_NAME, or null if not set.
     */
    private function get_selected_display_field(): ?int {
        $raw = $this->get_data();
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && array_key_exists('value', $decoded)) {
                return (int)$decoded['value'];
            }
            if (ctype_digit(trim($raw))) {
                return (int)$raw;
            }
        }
        // Already numeric.
        if (is_int($raw)) {
            return $raw;
        }
        return null;
    }
}
