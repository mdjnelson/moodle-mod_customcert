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
 * This file contains the customcert date range element.
 *
 * @package    customcertelement_daterange
 * @copyright  2018 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_daterange;

use \mod_customcert\element_helper;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/lib/grade/constants.php');

/**
 * The customcert date range element.
 *
 * @package    customcertelement_daterange
 * @copyright  2018 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * Max recurring period in seconds.
     */
    const MAX_RECURRING_PERIOD = 31556926; // 12 months.

    /**
     * Current year placeholder string.
     */
    const CURRENT_YEAR_PLACEHOLDER = '{{current_year}}';

    /**
     * First year in a date range placeholder string.
     */
    const RANGE_FIRST_YEAR_PLACEHOLDER = '{{range_first_year}}';

    /**
     * Last year in a date range placeholder string.
     */
    const RANGE_LAST_YEAR_PLACEHOLDER = '{{range_last_year}}';

    /**
     * First year in a date range placeholder string.
     */
    const RECUR_RANGE_FIRST_YEAR_PLACEHOLDER = '{{recurring_range_first_year}}';

    /**
     * Last year in a date range placeholder string.
     */
    const RECUR_RANGE_LAST_YEAR_PLACEHOLDER = '{{recurring_range_last_year}}';

    /**
     * A year in the user's date.
     */
    const DATE_YEAR_PLACEHOLDER = '{{date_year}}';

    /**
     * Default max number of dateranges per element.
     */
    const DEFAULT_MAX_RANGES = 10;

    /**
     * Date - Issue
     */
    const DATE_ISSUE = 1;

    /**
     * Date - Completion
     */
    const DATE_COMPLETION = 2;

    /**
     * Date - Course start
     */
    const DATE_COURSE_START = 3;

    /**
     * Date - Course end
     */
    const DATE_COURSE_END = 4;

    /**
     * Date - Course grade date
     */
    const DATE_COURSE_GRADE = 5;

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \mod_customcert\edit_element_form $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        global $COURSE;

        // Get the possible date options.
        $dateoptions = array();
        $dateoptions[self::DATE_ISSUE] = get_string('issueddate', 'customcertelement_daterange');
        $dateoptions[self::DATE_COMPLETION] = get_string('completiondate', 'customcertelement_daterange');
        $dateoptions[self::DATE_COURSE_START] = get_string('coursestartdate', 'customcertelement_daterange');
        $dateoptions[self::DATE_COURSE_END] = get_string('courseenddate', 'customcertelement_daterange');
        $dateoptions[self::DATE_COURSE_GRADE] = get_string('coursegradedate', 'customcertelement_daterange');

        $dateoptions = $dateoptions + element_helper::get_grade_items($COURSE);

        $mform->addElement('select', 'dateitem', get_string('dateitem', 'customcertelement_daterange'), $dateoptions);
        $mform->addHelpButton('dateitem', 'dateitem', 'customcertelement_daterange');

        parent::render_form_elements($mform);

        $mform->addElement('header', 'dateranges', get_string('dateranges', 'customcertelement_daterange'));
        $mform->addElement('static', 'help', '', get_string('help', 'customcertelement_daterange'));
        $mform->addElement('static', 'placeholders', '', get_string('placeholders', 'customcertelement_daterange'));

        $mform->addElement('text', 'fallbackstring', get_string('fallbackstring', 'customcertelement_daterange'));
        $mform->addHelpButton('fallbackstring', 'fallbackstring', 'customcertelement_daterange');
        $mform->setType('fallbackstring', PARAM_NOTAGS);

        if (!$maxranges = get_config('customcertelement_daterange', 'maxranges')) {
            $maxranges = self::DEFAULT_MAX_RANGES;
        }

        if (!empty($this->get_data())) {
            if ($maxranges < $this->get_decoded_data()->numranges) {
                $maxranges = $this->get_decoded_data()->numranges;
            }
        }

        $mform->addElement('hidden', 'numranges', $maxranges);
        $mform->setType('numranges', PARAM_INT);

        for ($i = 0; $i < $maxranges; $i++) {

            $mform->addElement('static',
                $this->build_element_name('group', $i),
                get_string('daterange', 'customcertelement_daterange', $i + 1),
                ''
            );

            $mform->addElement(
                'checkbox',
                $this->build_element_name('enabled', $i),
                get_string('enable')
            );
            $mform->setType($this->build_element_name('enabled', $i), PARAM_BOOL);

            $mform->addElement(
                'date_selector',
                $this->build_element_name('startdate', $i),
                get_string('start', 'customcertelement_daterange')
            );
            $mform->setType($this->build_element_name('startdate', $i), PARAM_INT);

            $mform->addElement(
                'date_selector',
                $this->build_element_name('enddate', $i),
                get_string('end', 'customcertelement_daterange')
            );
            $mform->setType($this->build_element_name('enddate', $i), PARAM_INT);

            $mform->addElement(
                'checkbox',
                $this->build_element_name('recurring', $i),
                get_string('recurring', 'customcertelement_daterange')
            );
            $mform->setType($this->build_element_name('recurring', $i), PARAM_BOOL);

            $mform->addElement(
                'text',
                $this->build_element_name('datestring', $i),
                get_string('datestring', 'customcertelement_daterange'),
                ['class' => 'datestring']
            );
            $mform->setType($this->build_element_name('datestring', $i), PARAM_NOTAGS);

            $mform->disabledIf($this->build_element_name('startdate', $i), $this->build_element_name('enabled', $i), 'notchecked');
            $mform->disabledIf($this->build_element_name('enddate', $i), $this->build_element_name('enabled', $i), 'notchecked');
            $mform->disabledIf($this->build_element_name('recurring', $i), $this->build_element_name('enabled', $i), 'notchecked');
            $mform->disabledIf($this->build_element_name('datestring', $i), $this->build_element_name('enabled', $i), 'notchecked');
        }
    }

    /**
     * A helper function to build consistent form element name.
     *
     * @param string $name
     * @param string $num
     *
     * @return string
     */
    protected function build_element_name($name, $num) {
        return $name . $num;
    }

    /**
     * Get decoded data stored in DB.
     *
     * @return \stdClass
     */
    protected function get_decoded_data() {
        return json_decode($this->get_data());
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \mod_customcert\edit_element_form $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        if (!empty($this->get_data()) && !$mform->isSubmitted()) {
            $element = $mform->getElement('dateitem');
            $element->setValue($this->get_decoded_data()->dateitem);

            $element = $mform->getElement('fallbackstring');
            $element->setValue($this->get_decoded_data()->fallbackstring);

            $element = $mform->getElement('numranges');
            $numranges = $element->getValue();
            if ($numranges < $this->get_decoded_data()->numranges) {
                $element->setValue($this->get_decoded_data()->numranges);
            }

            foreach ($this->get_decoded_data()->dateranges as $key => $range) {
                $mform->setDefault($this->build_element_name('startdate', $key), $range->startdate);
                $mform->setDefault($this->build_element_name('enddate', $key), $range->enddate);
                $mform->setDefault($this->build_element_name('datestring', $key), $range->datestring);
                $mform->setDefault($this->build_element_name('recurring', $key), $range->recurring);
                $mform->setDefault($this->build_element_name('enabled', $key), $range->enabled);
            }
        }

        parent::definition_after_data($mform);
    }

    /**
     * Performs validation on the element values.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array the validation errors
     */
    public function validate_form_elements($data, $files) {
        $errors = parent::validate_form_elements($data, $files);

        // Check if at least one range is set.
        $error = get_string('error:enabled', 'customcertelement_daterange');
        for ($i = 0; $i < $data['numranges']; $i++) {
            if (!empty($data[$this->build_element_name('enabled', $i)])) {
                $error = '';
            }
        }

        if (!empty($error)) {
            $errors['help'] = $error;
        }

        // Check that datestring is set for enabled dataranges.
        for ($i = 0; $i < $data['numranges']; $i++) {
            $enabled = $this->build_element_name('enabled', $i);
            $datestring = $this->build_element_name('datestring', $i);
            if (!empty($data[$enabled]) && empty($data[$datestring])) {
                $name = $this->build_element_name('datestring', $i);
                $errors[$name] = get_string('error:datestring', 'customcertelement_daterange');
            }
        }

        for ($i = 0; $i < $data['numranges']; $i++) {
            $enabled = $this->build_element_name('enabled', $i);
            $recurring = $this->build_element_name('recurring', $i);
            $startdate = $this->build_element_name('startdate', $i);
            $enddate = $this->build_element_name('enddate', $i);
            $rangeperiod = $data[$enddate] - $data[$startdate];

            // Check that end date is correctly set.
            if (!empty($data[$enabled]) && $data[$startdate] >= $data[$enddate] ) {
                $errors[$this->build_element_name('enddate', $i)] = get_string('error:enddate', 'customcertelement_daterange');
            }

            // Check that recurring dateranges are not longer than 12 months.
            if (!empty($data[$recurring]) && $rangeperiod >= self::MAX_RECURRING_PERIOD ) {
                $errors[$this->build_element_name('enddate', $i)] = get_string('error:recurring', 'customcertelement_daterange');
            }
        }

        return $errors;
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        $arrtostore = array(
            'dateitem' => $data->dateitem,
            'fallbackstring' => $data->fallbackstring,
            'numranges' => 0,
            'dateranges' => [],
        );

        for ($i = 0; $i < $data->numranges; $i++) {
            $startdate = $this->build_element_name('startdate', $i);
            $enddate = $this->build_element_name('enddate', $i);
            $datestring = $this->build_element_name('datestring', $i);
            $recurring = $this->build_element_name('recurring', $i);
            $enabled = $this->build_element_name('enabled', $i);

            if (!empty($data->$datestring)) {
                $arrtostore['dateranges'][] = [
                    'startdate' => $data->$startdate,
                    'enddate' => $data->$enddate,
                    'datestring' => $data->$datestring,
                    'recurring' => !empty($data->$recurring),
                    'enabled' => !empty($data->$enabled),
                ];
                $arrtostore['numranges']++;
            }
        }

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

        $courseid = element_helper::get_courseid($this->id);
        $dateitem = $this->get_decoded_data()->dateitem;

        // If we are previewing this certificate then just show a demonstration date.
        if ($preview) {
            $date = time();
        } else {
            // Get the page.
            $page = $DB->get_record('customcert_pages', array('id' => $this->get_pageid()), '*', MUST_EXIST);
            // Get the customcert this page belongs to.
            $customcert = $DB->get_record('customcert', array('templateid' => $page->templateid), '*', MUST_EXIST);
            // Now we can get the issue for this user.
            $issue = $DB->get_record('customcert_issues', array('userid' => $user->id, 'customcertid' => $customcert->id),
                '*', MUST_EXIST);

            switch ($dateitem) {
                case self::DATE_ISSUE:
                    $date = $issue->timecreated;
                    break;

                case self::DATE_COMPLETION:
                    // Get the last completion date.
                    $sql = "SELECT MAX(c.timecompleted) as timecompleted
                          FROM {course_completions} c
                         WHERE c.userid = :userid
                           AND c.course = :courseid";
                    if ($timecompleted = $DB->get_record_sql($sql, array('userid' => $issue->userid, 'courseid' => $courseid))) {
                        if (!empty($timecompleted->timecompleted)) {
                            $date = $timecompleted->timecompleted;
                        }
                    }
                    break;

                case self::DATE_COURSE_START:
                    $date = $DB->get_field('course', 'startdate', array('id' => $courseid));
                    break;

                case self::DATE_COURSE_END:
                    $date = $DB->get_field('course', 'enddate', array('id' => $courseid));
                    break;

                case self::DATE_COURSE_GRADE:
                    $grade = element_helper::get_course_grade_info(
                        $courseid,
                        GRADE_DISPLAY_TYPE_DEFAULT, $user->id
                    );
                    if ($grade && !empty($grade->get_dategraded())) {
                        $date = $grade->get_dategraded();
                    }
                    break;

                default:
                    if (strpos($dateitem, 'gradeitem:') === 0) {
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
                    break;
            }
        }

        // Ensure that a date has been set.
        if (!empty($date)) {
            element_helper::render_content($pdf, $this, $this->get_daterange_string($date));
        }
    }

    /**
     * Get daterange string.
     *
     * @param int $date Unix stamp date.
     *
     * @return string
     */
    protected function get_daterange_string($date) {
        $matchedrange = null;
        $outputstring = '';
        $formatdata = [];
        $formatdata['date'] = $date;

        foreach ($this->get_decoded_data()->dateranges as $key => $range) {
            if ($this->is_recurring_range($range)) {
                if ($matchedrange = $this->get_matched_recurring_range($date, $range)) {
                    $outputstring = $matchedrange->datestring;
                    $formatdata['range'] = $range;
                    $formatdata['recurringrange'] = $matchedrange;
                    break;
                }
            } else {
                if ($this->is_date_in_range($date, $range)) {
                    $outputstring = $range->datestring;
                    $formatdata['range'] = $range;
                    break;
                }
            }
        }

        if (empty($outputstring) && !empty($this->get_decoded_data()->fallbackstring)) {
            $outputstring = $this->get_decoded_data()->fallbackstring;
        }

        return $this->format_date_string($outputstring, $formatdata);
    }

    /**
     * @param \stdClass $range Range object.
     *
     * @return bool
     */
    protected function is_recurring_range(\stdClass $range) {
        return !empty($range->recurring);
    }

    /**
     * Check if the provided date is in the date range.
     *
     * @param int $date Unix timestamp date to check.
     * @param \stdClass $range Range object.
     *
     * @return bool
     */
    protected function is_date_in_range($date, \stdClass $range) {
        return ($date >= $range->startdate && $date <= $range->enddate);
    }

    /**
     * Check if provided date is in the recurring date range.
     *
     * @param int $date Unix timestamp date to check.
     * @param \stdClass $range Range object.
     *
     * @return bool
     */
    protected function is_date_in_recurring_range($date, \stdClass $range) {
        $intdate = $this->build_number_from_date($date);
        $intstart = $this->build_number_from_date($range->startdate);
        $intend = $this->build_number_from_date($range->enddate);

        if (!$this->has_turn_of_the_year($range)) {
            if ($intdate >= $intstart && $intdate <= $intend) {
                return true;
            }
        } else {
            if ($intdate >= $intstart && $intdate >= $intend) {
                return true;
            }

            if ($intdate <= $intstart && $intdate <= $intend) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if provided recurring range has a turn of the year.
     *
     * @param \stdClass $reccurringrange Range object.
     *
     * @return bool
     */
    protected function has_turn_of_the_year(\stdClass $reccurringrange) {
        return date('Y', $reccurringrange->startdate) != date('Y', $reccurringrange->enddate);
    }

    /**
     * Check if provided date is in the start year of the recurring range with a turn of the year.
     *
     * @param int $date Unix timestamp date to check.
     * @param \stdClass $range Range object.
     *
     * @return bool
     */
    protected function in_start_year($date, \stdClass $range) {
        $intdate = $this->build_number_from_date($date);
        $intstart = $this->build_number_from_date($range->startdate);
        $intend = $this->build_number_from_date($range->enddate);

        return $intdate >= $intstart && $intdate >= $intend;
    }

    /**
     * Check if provided date is in the end year of the recurring range with a turn of the year.
     *
     * @param int $date Unix timestamp date to check.
     * @param \stdClass $range Range object.
     *
     * @return bool
     */
    protected function in_end_year($date, \stdClass $range) {
        $intdate = $this->build_number_from_date($date);
        $intstart = $this->build_number_from_date($range->startdate);
        $intend = $this->build_number_from_date($range->enddate);

        return $intdate <= $intstart && $intdate <= $intend;
    }

    /**
     * Return matched recurring date range.
     *
     * As recurring date ranges do not depend on the year,
     * we will use a date's year to build a new matched recurring date range with
     * start year and end year. This is required to replace placeholders like range_first_year and range_last_year.
     *
     * @param int $date Unix timestamp date to check.
     * @param \stdClass $range Range object.
     *
     * @return \stdClass || null
     */
    protected function get_matched_recurring_range($date, \stdClass $range) {
        if (!$this->is_date_in_recurring_range($date, $range)) {
            return null;
        }

        $matchedrage = clone $range;

        if ($this->has_turn_of_the_year($matchedrage)) {

            if ($this->in_start_year($date, $matchedrage)) {
                $startyear = date('Y', $date);
                $endyear = $startyear + 1;
                $matchedrage->startdate = strtotime(date('d.m.', $matchedrage->startdate) . $startyear);
                $matchedrage->enddate = strtotime(date('d.m.', $matchedrage->enddate) . $endyear);

                return $matchedrage;
            }

            if ($this->in_end_year($date, $matchedrage)) {
                $endyear = date('Y', $date);
                $startyear = $endyear - 1;
                $matchedrage->startdate = strtotime(date('d.m.', $matchedrage->startdate) . $startyear);
                $matchedrage->enddate = strtotime(date('d.m.', $matchedrage->enddate) . $endyear);

                return $matchedrage;
            }
        } else {
            $matchedrage->startdate = strtotime(date('d.m.', $matchedrage->startdate) . date('Y', $date));
            $matchedrage->enddate = strtotime(date('d.m.', $matchedrage->enddate) . date('Y', $date));

            return $matchedrage;
        }

        return null;
    }

    /**
     * Build number representation of the provided date.
     *
     * @param int $date Unix timestamp date to check.
     *
     * @return int
     */
    protected function build_number_from_date($date) {
        return (int)date('md', $date);
    }

    /**
     * Format date string based on different types of placeholders.
     *
     * @param array $formatdata A list of format data.
     *
     * @return string
     */
    protected function format_date_string($datestring, array $formatdata) {
        foreach ($this->get_placeholders() as $search => $replace) {
            $datestring = str_replace($search, $replace, $datestring);
        }

        if (!empty($formatdata['date'])) {
            foreach ($this->get_date_placeholders($formatdata['date']) as $search => $replace) {
                $datestring = str_replace($search, $replace, $datestring);
            }
        }

        if (!empty($formatdata['range'])) {
            foreach ($this->get_range_placeholders($formatdata['range']) as $search => $replace) {
                $datestring = str_replace($search, $replace, $datestring);
            }
        }

        if (!empty($formatdata['recurringrange'])) {
            foreach ($this->get_recurring_range_placeholders($formatdata['recurringrange']) as $search => $replace) {
                $datestring = str_replace($search, $replace, $datestring);
            }
        }

        return $datestring;
    }

    /**
     * Return a list of placeholders to replace in date string as search => $replace pairs.
     *
     * @return array
     */
    protected function get_placeholders() {
        return [
            self::CURRENT_YEAR_PLACEHOLDER => date('Y', time()),
        ];
    }

    /**
     * Return a list of user's date related placeholders to replace in date string as search => $replace pairs.

     * @param int $date Unix timestamp date to check.
     *
     * @return array
     */
    protected function get_date_placeholders($date) {
        return [
            self::DATE_YEAR_PLACEHOLDER => date('Y', $date),
        ];
    }

    /**
     * Return a list of range related placeholders to replace in date string as search => $replace pairs.
     *
     * @param \stdClass $range
     *
     * @return array
     */
    protected function get_range_placeholders(\stdClass $range) {
        return [
            self::RANGE_FIRST_YEAR_PLACEHOLDER => date('Y', $range->startdate),
            self::RANGE_LAST_YEAR_PLACEHOLDER => date('Y', $range->enddate),
        ];
    }

    /**
     * Return a list of recurring range s placeholders to replace in date string as search => $replace pairs.
     *
     * @param \stdClass $range
     *
     * @return array
     */
    protected function get_recurring_range_placeholders(\stdClass $range) {
        return [
            self::RECUR_RANGE_FIRST_YEAR_PLACEHOLDER => date('Y', $range->startdate),
            self::RECUR_RANGE_LAST_YEAR_PLACEHOLDER => date('Y', $range->enddate),
        ];
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

        return element_helper::render_html_content($this, get_string('preview', 'customcertelement_daterange', $this->get_name()));
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

        $data = $this->get_decoded_data();
        if ($newitem = \restore_dbops::get_backup_ids_record($restore->get_restoreid(), 'course_module', $data->dateitem)) {
            $data->dateitem = $newitem->newitemid;
            $DB->set_field('customcert_elements', 'data', $this->save_unique_data($data), array('id' => $this->get_id()));
        }
    }

}
