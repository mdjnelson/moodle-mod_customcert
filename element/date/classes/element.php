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
 * This file contains the customcert element date's core interaction API.
 *
 * @package    customcertelement_date
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_date;

use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\field_type;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use MoodleQuickForm;
use pdf;
use restore_customcert_activity_task;
use stdClass;
use mod_customcert\service\element_renderer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/grade/constants.php');

/**
 * The customcert element date's core interaction API.
 *
 * @package    customcertelement_date
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements element_interface, form_definable_interface, preparable_form_interface {
    /** @var string Course grade date identifier. */
    public const string DATE_COURSE_GRADE = '0';

    /** @var string Issue date identifier. */
    public const string DATE_ISSUE = '-1';

    /** @var string Completion date identifier. */
    public const string DATE_COMPLETION = '-2';

    /** @var string Course start date identifier. */
    public const string DATE_COURSE_START = '-3';

    /** @var string Course end date identifier. */
    public const string DATE_COURSE_END = '-4';

    /** @var string Current date identifier. */
    public const string DATE_CURRENT_DATE = '-5';

    /** @var string Enrolment start date identifier. */
    public const string DATE_ENROLMENT_START = '-6';

    /** @var string Enrolment end date identifier. */
    public const string DATE_ENROLMENT_END = '-7';

    /**
     * Define the configuration fields for this element.
     *
     * @return array
     */
    public function get_form_fields(): array {
        global $CFG, $COURSE;

        // Get the possible date options.
        $dateoptions = [];
        $dateoptions[self::DATE_ISSUE] = get_string('issueddate', 'customcertelement_date');
        $dateoptions[self::DATE_CURRENT_DATE] = get_string('currentdate', 'customcertelement_date');
        $completionenabled = $CFG->enablecompletion && ($COURSE->id == SITEID || $COURSE->enablecompletion);
        if ($completionenabled) {
            $dateoptions[self::DATE_COMPLETION] = get_string('completiondate', 'customcertelement_date');
        }
        $dateoptions[self::DATE_ENROLMENT_START] = get_string('enrolmentstartdate', 'customcertelement_date');
        $dateoptions[self::DATE_ENROLMENT_END] = get_string('enrolmentenddate', 'customcertelement_date');
        $dateoptions[self::DATE_COURSE_START] = get_string('coursestartdate', 'customcertelement_date');
        $dateoptions[self::DATE_COURSE_END] = get_string('courseenddate', 'customcertelement_date');
        $dateoptions[self::DATE_COURSE_GRADE] = get_string('coursegradedate', 'customcertelement_date');
        $dateoptions = $dateoptions + element_helper::get_grade_items($COURSE);

        return [
            'dateitem' => [
                'type' => field_type::select,
                'label' => get_string('dateitem', 'customcertelement_date'),
                'options' => $dateoptions,
                'help' => ['dateitem', 'customcertelement_date'],
            ],
            'dateformat' => [
                'type' => field_type::select,
                'label' => get_string('dateformat', 'customcertelement_date'),
                'options' => element_helper::get_date_formats(),
                'help' => ['dateformat', 'customcertelement_date'],
            ],
            // Standard controls expected on Date forms.
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
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data): string {
        // Array of data we will be storing in the database.
        $arrtostore = [
            'dateitem' => $data->dateitem,
            'dateformat' => $data->dateformat,
        ];

        // Encode these variables before saving into the DB.
        return json_encode($arrtostore);
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

        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        $courseid = element_helper::get_courseid($this->id);

        // Decode the information stored in the database.
        $dateinfo = json_decode($this->get_data());
        $dateitem = $dateinfo->dateitem;
        $dateformat = $dateinfo->dateformat;

        // If we are previewing this certificate then just show a demonstration date.
        if ($preview) {
            $date = time();
        } else {
            // Get the page.
            $page = $DB->get_record('customcert_pages', ['id' => $this->get_pageid()], '*', MUST_EXIST);
            // Get the customcert this page belongs to.
            $customcert = $DB->get_record('customcert', ['templateid' => $page->templateid], '*', MUST_EXIST);
            // Now we can get the issue for this user.
            $issue = $DB->get_record(
                'customcert_issues',
                ['userid' => $user->id, 'customcertid' => $customcert->id],
                '*',
                IGNORE_MULTIPLE
            );

            if ($dateitem == self::DATE_ISSUE) {
                $date = $issue->timecreated;
            } else if ($dateitem == self::DATE_CURRENT_DATE) {
                $date = time();
            } else if ($dateitem == self::DATE_COMPLETION) {
                // Get the last completion date.
                $sql = "SELECT MAX(c.timecompleted) as timecompleted
                          FROM {course_completions} c
                         WHERE c.userid = :userid
                           AND c.course = :courseid";
                if ($timecompleted = $DB->get_record_sql($sql, ['userid' => $issue->userid, 'courseid' => $courseid])) {
                    if (!empty($timecompleted->timecompleted)) {
                        $date = $timecompleted->timecompleted;
                    }
                }
            } else if ($dateitem == self::DATE_ENROLMENT_START) {
                // Get the enrolment start date.
                $sql = "SELECT ue.timestart FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid = e.id
                         WHERE e.courseid = :courseid
                           AND ue.userid = :userid";
                if ($timestart = $DB->get_record_sql($sql, ['userid' => $issue->userid, 'courseid' => $courseid])) {
                    if (!empty($timestart->timestart)) {
                        $date = $timestart->timestart;
                    } else {
                        $date = $timestart->timecreated;
                    }
                }
            } else if ($dateitem == self::DATE_ENROLMENT_END) {
                // Get the enrolment end date.
                $sql = "SELECT ue.timeend FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid = e.id
                         WHERE e.courseid = :courseid
                           AND ue.userid = :userid";
                if ($timeend = $DB->get_record_sql($sql, ['userid' => $issue->userid, 'courseid' => $courseid])) {
                    if (!empty($timeend->timeend)) {
                        $date = $timeend->timeend;
                    }
                }
            } else if ($dateitem == self::DATE_COURSE_START) {
                $date = $DB->get_field('course', 'startdate', ['id' => $courseid]);
            } else if ($dateitem == self::DATE_COURSE_END) {
                $date = $DB->get_field('course', 'enddate', ['id' => $courseid]);
            } else {
                if ($dateitem == self::DATE_COURSE_GRADE) {
                    $grade = element_helper::get_course_grade_info(
                        $courseid,
                        GRADE_DISPLAY_TYPE_DEFAULT,
                        $user->id
                    );
                } else if (strpos($dateitem, 'gradeitem:') === 0) {
                    $gradeitemid = substr($dateitem, 10);
                    $grade = element_helper::get_grade_item_info(
                        $gradeitemid,
                        $dateitem,
                        $user->id
                    );
                } else {
                    $grade = element_helper::get_mod_grade_info(
                        $dateitem,
                        GRADE_DISPLAY_TYPE_DEFAULT,
                        $user->id
                    );
                }

                if ($grade && !empty($grade->get_dategraded())) {
                    $date = $grade->get_dategraded();
                }
            }
        }

        // Ensure that a date has been set.
        if (!empty($date)) {
            $content = element_helper::get_date_format_string($date, $dateformat);
            if ($renderer) {
                $renderer->render_content($this, $content);
            } else {
                element_helper::render_content($pdf, $this, $content);
            }
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
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return '';
        }

        // Decode the information stored in the database.
        $dateinfo = json_decode($this->get_data());
        $dateformat = $dateinfo->dateformat;

        $content = element_helper::get_date_format_string(time(), $dateformat);
        if ($renderer) {
            return (string) $renderer->render_content($this, $content);
        }

        return element_helper::render_html_content($this, $content);
    }

    /**
     * Prepare the form by populating the dateitem and dateformat fields from stored data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        // Set the item and format for this element.
        if (!empty($this->get_data())) {
            $dateinfo = json_decode($this->get_data());

            if (isset($dateinfo->dateitem)) {
                $mform->getElement('dateitem')->setValue($dateinfo->dateitem);
            }

            if (isset($dateinfo->dateformat)) {
                $mform->getElement('dateformat')->setValue($dateinfo->dateformat);
            }
        }
    }

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the course module the date element is pointing to as it will
     * have changed in the course restore.
     *
     * @param restore_customcert_activity_task $restore
     */
    public function after_restore($restore) {
        global $DB;

        $dateinfo = json_decode($this->get_data());

        $isgradeitem = false;
        $oldid = $dateinfo->dateitem;
        if (str_starts_with($dateinfo->dateitem, 'gradeitem:')) {
            $isgradeitem = true;
            $oldid = str_replace('gradeitem:', '', $dateinfo->dateitem);
        }

        $itemname = $isgradeitem ? 'grade_item' : 'course_module';
        if ($newitem = \restore_dbops::get_backup_ids_record($restore->get_restoreid(), $itemname, $oldid)) {
            $dateinfo->dateitem = '';
            if ($isgradeitem) {
                $dateinfo->dateitem = 'gradeitem:';
            }
            $dateinfo->dateitem = $dateinfo->dateitem . $newitem->newitemid;
            $DB->set_field('customcert_elements', 'data', $this->save_unique_data($dateinfo), ['id' => $this->get_id()]);
        }
    }

    /**
     * Helper function to return all the date formats.
     *
     * @return array the list of date formats
     */
    public static function get_date_formats() {
        debugging("The method customcertelement_date::get_date_formats is deprecated, " .
            "please use element_helper::get_date_formats() instead", DEBUG_DEVELOPER);
        return element_helper::get_date_formats();
    }
}
