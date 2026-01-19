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
 * This file contains the customcert element teachername's core interaction API.
 *
 * @package    customcertelement_teachername
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_teachername;

use context_system;
use mod_customcert\element\constructable_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\form_buildable_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * The customcert element teachername's core interaction API.
 *
 * @package    customcertelement_teachername
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    constructable_element_interface,
    element_interface,
    form_buildable_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    validatable_element_interface
{
    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        $mform->addElement(
            'select',
            'teacher',
            get_string('teacher', 'customcertelement_teachername'),
            $this->get_list_of_teachers()
        );
        $mform->addHelpButton('teacher', 'teacher', 'customcertelement_teachername');

        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    /**
     * Normalise teacher name element data.
     *
     * @param stdClass $formdata Form submission data
     * @return array JSON-serialisable payload
     */
    public function normalise_data(stdClass $formdata): array {
        return ['teacher' => (string)($formdata->teacher ?? '')];
    }

    /**
     * Ensures the teacher select shows the stored value on edit and options are refreshed each render.
     *
     * @param MoodleQuickForm $mform
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        // Preselect stored teacher id if present.
        $payload = $this->get_payload();
        if (isset($payload['teacher'])) {
            $mform->getElement('teacher')->setValue((int)$payload['teacher']);
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
        global $DB;

        $payload = $this->get_payload();
        if (!isset($payload['teacher'])) {
            return;
        }
        $teacher = $DB->get_record('user', ['id' => (int)$payload['teacher']]);
        $teachername = fullname($teacher);

        if ($renderer) {
            $renderer->render_content($this, $teachername);
        } else {
            element_helper::render_content($pdf, $this, $teachername);
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
        global $DB;

        $payload = $this->get_payload();
        if (!isset($payload['teacher'])) {
            return '';
        }
        $teacher = $DB->get_record('user', ['id' => (int)$payload['teacher']]);
        $teachername = fullname($teacher);

        if ($renderer) {
            return (string) $renderer->render_content($this, $teachername);
        }

        return element_helper::render_html_content($this, $teachername);
    }

    /**
     * Helper function to return the teachers for this course.
     *
     * @return array the list of teachers
     */
    protected function get_list_of_teachers() {
        global $PAGE;

        // Return early if we are in a site template.
        if ($PAGE->context->id == context_system::instance()->id) {
            return [];
        }

        // The list of teachers to return.
        $teachers = [];

        // Now return all users who can manage the customcert in this context.
        if ($users = get_enrolled_users($PAGE->context, 'mod/customcert:manage')) {
            foreach ($users as $user) {
                $teachers[$user->id] = fullname($user);
            }
        }

        return $teachers;
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

    /**
     * Build an element instance from a DB record.
     *
     * @param stdClass $record Raw DB row from customcert_elements.
     * @return static
     */
    public static function from_record(stdClass $record): static {
        return new static($record);
    }
}
