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
use mod_customcert\element\field_type;
use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use restore_customcert_activity_task;
use stdClass;

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
class element extends base_element implements element_interface, form_definable_interface, preparable_form_interface {
    /** @var string Course grade identifier. */
    public const GRADE_COURSE = '0';
    /**
     * Define the configuration fields for this element.
     *
     * @return array
     */
    public function get_form_fields(): array {
        global $COURSE;

        // Get the grade items we can display.
        $gradeitems = [];
        $gradeitems[self::GRADE_COURSE] = get_string('coursegrade', 'customcertelement_grade');
        $gradeitems = $gradeitems + element_helper::get_grade_items($COURSE);

        return [
            'gradeitem' => [
                'type' => field_type::select,
                'label' => get_string('gradeitem', 'customcertelement_grade'),
                'options' => $gradeitems,
                'help' => ['gradeitem', 'customcertelement_grade'],
            ],
            'gradeformat' => [
                'type' => field_type::select,
                'label' => get_string('gradeformat', 'customcertelement_grade'),
                'options' => self::get_grade_format_options(),
                'help' => ['gradeformat', 'customcertelement_grade'],
                'type_param' => PARAM_INT,
            ],
            // Standard controls expected on Grade forms.
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
     * @param stdClass $data the form data.
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        // Persist the selected grade item id and grade format as strings in JSON.
        return json_encode([
            'gradeitem' => (string)$data->gradeitem,
            'gradeformat' => (string)($data->gradeformat ?? ''),
        ]);
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

        // Decode the information stored in the database (JSON only post-migration).
        $decoded = json_decode((string)$this->get_data());
        if (!is_object($decoded) || !isset($decoded->gradeitem)) {
            return; // Nothing to render if not configured.
        }
        $gradeitem = (string)$decoded->gradeitem;
        $gradeformat = isset($decoded->gradeformat) ? (int)$decoded->gradeformat : GRADE_DISPLAY_TYPE_REAL;

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

        // Decode the information stored in the database (JSON only post-migration).
        $decoded = json_decode((string)$this->get_data());
        $gradeformat = (is_object($decoded) && isset($decoded->gradeformat)) ? (int)$decoded->gradeformat : GRADE_DISPLAY_TYPE_REAL;

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
        // Set the item and format for this element from stored data (scalar or JSON).
        $raw = $this->get_data();
        if ($raw === null || $raw === '') {
            return;
        }
        $gradeitem = null;
        $gradeformat = null;
        if (is_string($raw)) {
            $decoded = json_decode($raw);
            if (is_object($decoded)) {
                $gradeitem = $decoded->gradeitem ?? ($decoded->value ?? null);
                $gradeformat = $decoded->gradeformat ?? null;
            } else if (ctype_digit(trim($raw))) {
                // Legacy scalar id stored directly in data.
                $gradeitem = $raw;
            }
        }
        if ($gradeitem !== null && $mform->elementExists('gradeitem')) {
            $mform->getElement('gradeitem')->setValue((string)$gradeitem);
        }
        if ($gradeformat !== null && $mform->elementExists('gradeformat')) {
            $mform->getElement('gradeformat')->setValue((int)$gradeformat);
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
    public function after_restore($restore) {
        global $DB;

        $gradeinfo = json_decode($this->get_data());

        $isgradeitem = false;
        $oldid = $gradeinfo->gradeitem;
        if (str_starts_with($gradeinfo->gradeitem, 'gradeitem:')) {
            $isgradeitem = true;
            $oldid = str_replace('gradeitem:', '', $gradeinfo->gradeitem);
        }

        $itemname = $isgradeitem ? 'grade_item' : 'course_module';
        if ($newitem = \restore_dbops::get_backup_ids_record($restore->get_restoreid(), $itemname, $oldid)) {
            $gradeinfo->gradeitem = '';
            if ($isgradeitem) {
                $gradeinfo->gradeitem = 'gradeitem:';
            }
            $gradeinfo->gradeitem = $gradeinfo->gradeitem . $newitem->newitemid;
            $DB->set_field('customcert_elements', 'data', $this->save_unique_data($gradeinfo), ['id' => $this->get_id()]);
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
