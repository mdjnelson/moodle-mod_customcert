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

namespace customcertelement_date;

use mod_customcert\element_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Date - Course grade date
 */
define('CUSTOMCERT_DATE_COURSE_GRADE', '0');

/**
 * Date - Issue
 */
define('CUSTOMCERT_DATE_ISSUE', '-1');

/**
 * Date - Completion
 */
define('CUSTOMCERT_DATE_COMPLETION', '-2');

/**
 * Date - Course start
 */
define('CUSTOMCERT_DATE_COURSE_START', '-3');

/**
 * Date - Course end
 */
define('CUSTOMCERT_DATE_COURSE_END', '-4');

/**
 * Date - Current date
 */
define('CUSTOMCERT_DATE_CURRENT_DATE', '-5');

/**
 * Date - Enrollment start
 */
define('CUSTOMCERT_DATE_ENROLMENT_START', '-6');

/**
 * Date - Entrollment end
 */
define('CUSTOMCERT_DATE_ENROLMENT_END', '-7');

require_once($CFG->dirroot . '/lib/grade/constants.php');

/**
 * The customcert element date's core interaction API.
 *
 * @package    customcertelement_date
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        global $CFG, $COURSE;

        // Get the possible date options.
        $dateoptions = [];
        $dateoptions[CUSTOMCERT_DATE_ISSUE] = get_string('issueddate', 'customcertelement_date');
        $dateoptions[CUSTOMCERT_DATE_CURRENT_DATE] = get_string('currentdate', 'customcertelement_date');
        $completionenabled = $CFG->enablecompletion && ($COURSE->id == SITEID || $COURSE->enablecompletion);
        if ($completionenabled) {
            $dateoptions[CUSTOMCERT_DATE_COMPLETION] = get_string('completiondate', 'customcertelement_date');
        }
        $dateoptions[CUSTOMCERT_DATE_ENROLMENT_START] = get_string('enrolmentstartdate', 'customcertelement_date');
        $dateoptions[CUSTOMCERT_DATE_ENROLMENT_END] = get_string('enrolmentenddate', 'customcertelement_date');
        $dateoptions[CUSTOMCERT_DATE_COURSE_START] = get_string('coursestartdate', 'customcertelement_date');
        $dateoptions[CUSTOMCERT_DATE_COURSE_END] = get_string('courseenddate', 'customcertelement_date');
        $dateoptions[CUSTOMCERT_DATE_COURSE_GRADE] = get_string('coursegradedate', 'customcertelement_date');
        $dateoptions = $dateoptions + \mod_customcert\element_helper::get_grade_items($COURSE);

        $mform->addElement('select', 'dateitem', get_string('dateitem', 'customcertelement_date'), $dateoptions);
        $mform->addHelpButton('dateitem', 'dateitem', 'customcertelement_date');

        $mform->addElement('select', 'dateformat', get_string('dateformat', 'customcertelement_date'),
            element_helper::get_date_formats());
        $mform->addHelpButton('dateformat', 'dateformat', 'customcertelement_date');

        parent::render_form_elements($mform);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
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
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        global $DB;

        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        $courseid = \mod_customcert\element_helper::get_courseid($this->id);

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
            $issue = $DB->get_record('customcert_issues', ['userid' => $user->id, 'customcertid' => $customcert->id],
                '*', IGNORE_MULTIPLE);

            if ($dateitem == CUSTOMCERT_DATE_ISSUE) {
                $date = $issue->timecreated;
            } else if ($dateitem == CUSTOMCERT_DATE_CURRENT_DATE) {
                $date = time();
            } else if ($dateitem == CUSTOMCERT_DATE_COMPLETION) {
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
            } else if ($dateitem == CUSTOMCERT_DATE_ENROLMENT_START) {
                // Get the enrolment start date.
                $sql = "SELECT ue.timestart FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid = e.id
                         WHERE e.courseid = :courseid
                           AND ue.userid = :userid";
                if ($timestart = $DB->get_record_sql($sql, ['userid' => $issue->userid, 'courseid' => $courseid])) {
                    if (!empty($timestart->timestart)) {
                        $date = $timestart->timestart;
                    }
                }
            } else if ($dateitem == CUSTOMCERT_DATE_ENROLMENT_END) {
                // Get the enrolment end date.
                $sql = "SELECT ue.timeend FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid = e.id
                         WHERE e.courseid = :courseid
                           AND ue.userid = :userid";
                if ($timeend = $DB->get_record_sql($sql, ['userid' => $issue->userid, 'courseid' => $courseid])) {
                    if (!empty($timeend->timeend)) {
                        $date = $timeend->timeend;
                    }
                }
            } else if ($dateitem == CUSTOMCERT_DATE_COURSE_START) {
                $date = $DB->get_field('course', 'startdate', ['id' => $courseid]);
            } else if ($dateitem == CUSTOMCERT_DATE_COURSE_END) {
                $date = $DB->get_field('course', 'enddate', ['id' => $courseid]);
            } else {
                if ($dateitem == CUSTOMCERT_DATE_COURSE_GRADE) {
                    $grade = \mod_customcert\element_helper::get_course_grade_info(
                        $courseid,
                        GRADE_DISPLAY_TYPE_DEFAULT,
                        $user->id
                    );
                } else if (strpos($dateitem, 'gradeitem:') === 0) {
                    $gradeitemid = substr($dateitem, 10);
                    $grade = \mod_customcert\element_helper::get_grade_item_info(
                        $gradeitemid,
                        $dateitem,
                        $user->id
                    );
                } else {
                    $grade = \mod_customcert\element_helper::get_mod_grade_info(
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
            \mod_customcert\element_helper::render_content($pdf, $this, element_helper::get_date_format_string($date, $dateformat));
        }
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html() {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        // Decode the information stored in the database.
        $dateinfo = json_decode($this->get_data());
        $dateformat = $dateinfo->dateformat;

        return \mod_customcert\element_helper::render_html_content($this,
            element_helper::get_date_format_string(time(), $dateformat));
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        // Set the item and format for this element.
        if (!empty($this->get_data())) {
            $dateinfo = json_decode($this->get_data());

            $element = $mform->getElement('dateitem');
            $element->setValue($dateinfo->dateitem);

            $element = $mform->getElement('dateformat');
            $element->setValue($dateinfo->dateformat);
        }

        parent::definition_after_data($mform);
    }

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the course module the date element is pointing to as it will
     * have changed in the course restore.
     *
     * @param \restore_customcert_activity_task $restore
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
