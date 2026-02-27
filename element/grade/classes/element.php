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
 * This file contains the customcert element grade's core interaction API.
 *
 * @package    customcertelement_grade
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_grade;

use grade_item;
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
use restore_customcert_activity_task;
use stdClass;
use mod_customcert\element\restorable_element_interface;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

// Legacy grade identifier was a global define; migrate to class constant below.

/**
 * The customcert element grade's core interaction API.
 *
 * @package    customcertelement_grade
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
    restorable_element_interface,
    validatable_element_interface
{
    /** @var string Course grade identifier. */
    public const string GRADE_COURSE = '0';

    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        global $COURSE;

        // Get the grade items we can display.
        $gradeitems = [];
        $gradeitems[self::GRADE_COURSE] = get_string('coursegrade', 'customcertelement_grade');
        $gradeitems = $gradeitems + element_helper::get_grade_items($COURSE);

        $mform->addElement('select', 'gradeitem', get_string('gradeitem', 'customcertelement_grade'), $gradeitems);
        $mform->addHelpButton('gradeitem', 'gradeitem', 'customcertelement_grade');

        $mform->addElement(
            'select',
            'gradeformat',
            get_string('gradeformat', 'customcertelement_grade'),
            self::get_grade_format_options()
        );
        $mform->setType('gradeformat', PARAM_INT);
        $mform->addHelpButton('gradeformat', 'gradeformat', 'customcertelement_grade');

        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    /**
     * Normalise grade element data.
     *
     * @param stdClass $formdata Form submission data
     * @return array JSON-serialisable payload
     */
    public function normalise_data(stdClass $formdata): array {
        return [
            'gradeitem' => (string)($formdata->gradeitem ?? ''),
            'gradeformat' => isset($formdata->gradeformat) ? (string)$formdata->gradeformat : '',
        ];
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
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param stdClass $user the user we are rengdering this for
     * @param element_renderer|null $renderer the renderer service
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        $courseid = element_helper::get_courseid($this->id);

        // Read the information stored in the database.
        $payload = $this->get_payload();
        if (empty($payload) || !isset($payload['gradeitem'])) {
            return; // Nothing to render if not configured.
        }
        $gradeitem = (string)$payload['gradeitem'];
        $gradeformat = isset($payload['gradeformat']) ? (int)$payload['gradeformat'] : GRADE_DISPLAY_TYPE_REAL;

        // If we are previewing this certificate then just show a demonstration grade.
        if ($preview) {
            $courseitem = grade_item::fetch_course_item($courseid);
            $grade = grade_format_gradevalue(100.0, $courseitem, true, $gradeformat);
        } else {
            if ($gradeitem === self::GRADE_COURSE) {
                $grade = element_helper::get_course_grade_info(
                    $courseid,
                    $gradeformat,
                    $user->id
                );
            } else if (strpos($gradeitem, 'gradeitem:') === 0) {
                $gradeitemid = substr($gradeitem, 10);
                $grade = element_helper::get_grade_item_info(
                    $gradeitemid,
                    $gradeformat,
                    $user->id
                );
            } else {
                $grade = element_helper::get_mod_grade_info(
                    $gradeitem,
                    $gradeformat,
                    $user->id
                );
            }

            if ($grade) {
                $grade = $grade->get_displaygrade();
            }
        }

        if ($renderer) {
            $renderer->render_content($this, $grade);
        } else {
            element_helper::render_content($pdf, $this, $grade);
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
        global $COURSE;

        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return '';
        }

        // Read the information stored in the database.
        $payload = $this->get_payload();
        $gradeformat = isset($payload['gradeformat']) ? (int)$payload['gradeformat'] : GRADE_DISPLAY_TYPE_REAL;

        $courseitem = grade_item::fetch_course_item($COURSE->id);

        $grade = grade_format_gradevalue(100.0, $courseitem, true, $gradeformat);

        if ($renderer) {
            return (string) $renderer->render_content($this, $grade);
        }

        return element_helper::render_html_content($this, $grade);
    }

    /**
     * Prepare the form by populating the gradeitem and gradeformat fields from stored data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        // Set the item and format for this element from stored data.
        $payload = $this->get_payload();
        if (isset($payload['gradeitem'])) {
            $mform->getElement('gradeitem')->setValue((string)$payload['gradeitem']);
        } else {
            // Legacy scalar in data.
            $value = $this->get_value();
            if ($value !== null && ctype_digit(trim($value))) {
                $mform->getElement('gradeitem')->setValue((string)$value);
            }
        }
        if (isset($payload['gradeformat'])) {
            $mform->getElement('gradeformat')->setValue((int)$payload['gradeformat']);
        }
    }

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the course module the grade element is pointing to as it will
     * have changed in the course restore.
     *
     * @param restore_customcert_activity_task $restore
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        global $DB;

        $data = $this->get_payload();
        if (empty($data) || empty($data['gradeitem'])) {
            return;
        }

        $isgradeitem = false;
        $oldid = $data['gradeitem'];
        if (str_starts_with($data['gradeitem'], 'gradeitem:')) {
            $isgradeitem = true;
            $oldid = str_replace('gradeitem:', '', $data['gradeitem']);
        }

        $itemname = $isgradeitem ? 'grade_item' : 'course_module';
        // Use the restore task mapping API instead of restore_dbops to allow unit testing without temp tables.
        $newid = $restore->get_mappingid($itemname, (int)$oldid);
        if ($newid) {
            $data['gradeitem'] = ($isgradeitem ? 'gradeitem:' : '') . $newid;
            $DB->set_field('customcert_elements', 'data', json_encode($data), ['id' => $this->get_id()]);
        }
    }

    /**
     * Helper function to return all the possible grade formats.
     *
     * @return array returns an array of grade formats
     */
    public static function get_grade_format_options() {
        $gradeformat = [];
        $gradeformat[GRADE_DISPLAY_TYPE_REAL] = get_string('gradepoints', 'customcertelement_grade');
        $gradeformat[GRADE_DISPLAY_TYPE_PERCENTAGE] = get_string('gradepercent', 'customcertelement_grade');
        $gradeformat[GRADE_DISPLAY_TYPE_LETTER] = get_string('gradeletter', 'customcertelement_grade');

        return $gradeformat;
    }
}
