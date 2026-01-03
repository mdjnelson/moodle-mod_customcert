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
 * This file contains the customcert element userfield's core interaction API.
 *
 * @package    customcertelement_userfield
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_userfield;

use availability_profile\condition;
use core_collator;
use core_user\fields;
use mod_customcert\element\field_type;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * The customcert element userfield's core interaction API.
 *
 * @package    customcertelement_userfield
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    element_interface,
    form_definable_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    validatable_element_interface
{
    /**
     * Define the configuration fields for this element.
     *
     * @return array
     */
    public function get_form_fields(): array {
        // Get the user profile fields.
        $userfields = [
            'firstname' => fields::get_display_name('firstname'),
            'lastname' => fields::get_display_name('lastname'),
            'username' => fields::get_display_name('username'),
            'email' => fields::get_display_name('email'),
            'city' => fields::get_display_name('city'),
            'country' => fields::get_display_name('country'),
            'url' => fields::get_display_name('url'),
            'idnumber' => fields::get_display_name('idnumber'),
            'institution' => fields::get_display_name('institution'),
            'department' => fields::get_display_name('department'),
            'phone1' => fields::get_display_name('phone1'),
            'phone2' => fields::get_display_name('phone2'),
            'address' => fields::get_display_name('address'),
        ];
        // Get the user custom fields.
        $arrcustomfields = condition::get_custom_profile_fields();
        $customfields = [];
        foreach ($arrcustomfields as $key => $customfield) {
            $customfields[$customfield->id] = $customfield->name;
        }
        // Combine the two.
        $fields = $userfields + $customfields;
        core_collator::asort($fields);

        return [
            'userfield' => [
                'type' => field_type::select,
                'label' => get_string('userfield', 'customcertelement_userfield'),
                'options' => $fields,
                'help' => ['userfield', 'customcertelement_userfield'],
                'type_param' => PARAM_ALPHANUM,
            ],
            // Standard controls expected by tests.
            'font' => [],
            'colour' => [],
            'width' => [],
            'refpoint' => [],
            'alignment' => [],
        ];
    }

    /**
     * Normalise userfield element data.
     *
     * @param stdClass $formdata Form submission data
     * @return array JSON-serialisable payload
     */
    public function normalise_data(stdClass $formdata): array {
        // Persist the selected user field identifier under the key 'userfield'.
        return ['userfield' => (string)($formdata->userfield ?? '')];
    }

    /**
     * Prepare the form by populating the userfield field from stored data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        $data = json_decode((string)$this->get_data());
        if (is_object($data) && isset($data->userfield)) {
            $mform->getElement('userfield')->setValue((string)$data->userfield);
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
        $value = $this->get_user_field_value($user, $preview);
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
     * @return string the html
     */
    public function render_html(?element_renderer $renderer = null): string {
        global $USER;

        $value = $this->get_user_field_value($USER, true);
        if ($renderer) {
            return (string) $renderer->render_content($this, $value);
        }

        return element_helper::render_html_content($this, $value);
    }


    /**
     * Helper function that returns the text.
     *
     * @param stdClass $user the user we are rendering this for
     * @param bool $preview Is this a preview?
     * @return string
     */
    protected function get_user_field_value(stdClass $user, bool $preview): string {
        global $CFG, $DB;

        // The user field to display.
        $raw = json_decode((string)$this->get_data());
        $field = is_object($raw) && isset($raw->userfield) ? $raw->userfield : '';
        // The value to display - we always want to show a value here so it can be repositioned.
        if ($preview) {
            $value = $field;
        } else {
            $value = '';
        }
        if (is_number($field)) { // Must be a custom user profile field.
            if ($field = $DB->get_record('user_info_field', ['id' => $field])) {
                // Found the field name, let's update the value to display.
                $value = $field->name;
                $file = $CFG->dirroot . '/user/profile/field/' . $field->datatype . '/field.class.php';
                if (file_exists($file)) {
                    require_once($CFG->dirroot . '/user/profile/lib.php');
                    require_once($file);
                    $class = "profile_field_{$field->datatype}";
                    $field = new $class($field->id, $user->id);
                    $value = $field->display_data();
                }
            }
        } else if (!empty($user->$field)) { // Field in the user table.
            $value = $user->$field;
        }

        $context = element_helper::get_context($this->get_id());
        return format_string($value, true, ['context' => $context]);
    }

    /**
     * Validate submitted form data for this element.
     * Core validations are handled by validation_service; no extra rules here.
     *
     * @param array $data
     * @return array<string,string>
     */
    public function validate(array $data): array {
        return [];
    }
}
