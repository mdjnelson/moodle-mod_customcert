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
 * Provides functionality needed by customcert activities.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certificate.
 *
 * Helper functionality for certificates.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate {

    /**
     * @var string the print protection variable
     */
    const PROTECTION_PRINT = 'print';

    /**
     * @var string the modify protection variable
     */
    const PROTECTION_MODIFY = 'modify';

    /**
     * @var string the copy protection variable
     */
    const PROTECTION_COPY = 'copy';

    /**
     * @var int the number of issues that will be displayed on each page in the report
     *      If you want to display all customcerts on a page set this to 0.
     */
    const CUSTOMCERT_PER_PAGE = '50';

    /**
     * Handles setting the protection field for the customcert
     *
     * @param \stdClass $data
     * @return string the value to insert into the protection field
     */
    public static function set_protection($data) {
        $protection = array();

        if (!empty($data->protection_print)) {
            $protection[] = self::PROTECTION_PRINT;
        }
        if (!empty($data->protection_modify)) {
            $protection[] = self::PROTECTION_MODIFY;
        }
        if (!empty($data->protection_copy)) {
            $protection[] = self::PROTECTION_COPY;
        }

        // Return the protection string.
        return implode(', ', $protection);
    }

    /**
     * Handles uploading an image for the customcert module.
     *
     * @param int $draftitemid the draft area containing the files
     * @param int $contextid the context we are storing this image in
     * @param string $filearea indentifies the file area.
     */
    public static function upload_files($draftitemid, $contextid, $filearea = 'image') {
        global $CFG;

        // Save the file if it exists that is currently in the draft area.
        require_once($CFG->dirroot . '/lib/filelib.php');
        file_save_draft_area_files($draftitemid, $contextid, 'mod_customcert', $filearea, 0);
    }

    /**
     * Return the list of possible fonts to use.
     */
    public static function get_fonts() {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        $arrfonts = [];
        $pdf = new \pdf();
        $fontfamilies = $pdf->get_font_families();
        foreach ($fontfamilies as $fontfamily => $fontstyles) {
            foreach ($fontstyles as $fontstyle) {
                $fontstyle = strtolower($fontstyle);
                if ($fontstyle == 'r') {
                    $filenamewoextension = $fontfamily;
                } else {
                    $filenamewoextension = $fontfamily . $fontstyle;
                }
                $fullpath = \TCPDF_FONTS::_getfontpath() . $filenamewoextension;
                // Set the name of the font to null, the include next should then set this
                // value, if it is not set then the file does not include the necessary data.
                $name = null;
                // Some files include a display name, the include next should then set this
                // value if it is present, if not then $name is used to create the display name.
                $displayname = null;
                // Some of the TCPDF files include files that are not present, so we have to
                // suppress warnings, this is the TCPDF libraries fault, grrr.
                @include($fullpath . '.php');
                // If no $name variable in file, skip it.
                if (is_null($name)) {
                    continue;
                }
                // Check if there is no display name to use.
                if (is_null($displayname)) {
                    // Format the font name, so "FontName-Style" becomes "Font Name - Style".
                    $displayname = preg_replace("/([a-z])([A-Z])/", "$1 $2", $name);
                    $displayname = preg_replace("/([a-zA-Z])-([a-zA-Z])/", "$1 - $2", $displayname);
                }

                $arrfonts[$filenamewoextension] = $displayname;
            }
        }
        ksort($arrfonts);

        return $arrfonts;
    }

    /**
     * Return the list of possible font sizes to use.
     */
    public static function get_font_sizes() {
        // Array to store the sizes.
        $sizes = array();

        for ($i = 1; $i <= 200; $i++) {
            $sizes[$i] = $i;
        }

        return $sizes;
    }

    /**
     * Get the time the user has spent in the course.
     *
     * @param int $courseid
     * @param int $userid
     * @return int the total time spent in seconds
     */
    public static function get_course_time($courseid, $userid = 0) {
        global $CFG, $DB, $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers();
        $enabledreaders = get_config('tool_log', 'enabled_stores');
        if (empty($enabledreaders)) {
            return 0;
        }
        $enabledreaders = explode(',', $enabledreaders);

        // Go through all the readers until we find one that we can use.
        foreach ($enabledreaders as $enabledreader) {
            $reader = $readers[$enabledreader];
            if ($reader instanceof \logstore_legacy\log\store) {
                $logtable = 'log';
                $coursefield = 'course';
                $timefield = 'time';
                break;
            } else if ($reader instanceof \core\log\sql_internal_table_reader) {
                $logtable = $reader->get_internal_log_table_name();
                $coursefield = 'courseid';
                $timefield = 'timecreated';
                break;
            }
        }

        // If we didn't find a reader then return 0.
        if (!isset($logtable)) {
            return 0;
        }

        $sql = "SELECT id, $timefield
                  FROM {{$logtable}}
                 WHERE userid = :userid
                   AND $coursefield = :courseid
              ORDER BY $timefield ASC";
        $params = array('userid' => $userid, 'courseid' => $courseid);
        $totaltime = 0;
        if ($logs = $DB->get_recordset_sql($sql, $params)) {
            foreach ($logs as $log) {
                if (!isset($login)) {
                    // For the first time $login is not set so the first log is also the first login.
                    $login = $log->$timefield;
                    $lasthit = $log->$timefield;
                    $totaltime = 0;
                }
                $delay = $log->$timefield - $lasthit;
                if ($delay > ($CFG->sessiontimeout * 60)) {
                    // The difference between the last log and the current log is more than
                    // the timeout Register session value so that we have found a session!
                    $login = $log->$timefield;
                } else {
                    $totaltime += $delay;
                }
                // Now the actual log became the previous log for the next cycle.
                $lasthit = $log->$timefield;
            }

            return $totaltime;
        }

        return 0;
    }

    /**
     * Returns a list of issued customcerts.
     *
     * @param int $customcertid
     * @param bool $groupmode are we in group mode
     * @param \stdClass $cm the course module
     * @param int $limitfrom
     * @param int $limitnum
     * @param string $sort
     * @return array the users
     */
    public static function get_issues($customcertid, $groupmode, $cm, $limitfrom, $limitnum, $sort = '') {
        global $DB;

        // Get the conditional SQL.
        list($conditionssql, $conditionsparams) = self::get_conditional_issues_sql($cm, $groupmode);

        // If it is empty then return an empty array.
        if (empty($conditionsparams)) {
            return array();
        }

        // Add the conditional SQL and the customcertid to form all used parameters.
        $allparams = $conditionsparams + array('customcertid' => $customcertid);

        // Return the issues.
        $extrafields = get_extra_user_fields(\context_module::instance($cm->id));
        $ufields = \user_picture::fields('u', $extrafields);
        $sql = "SELECT $ufields, ci.id as issueid, ci.code, ci.timecreated
                  FROM {user} u
            INNER JOIN {customcert_issues} ci
                    ON u.id = ci.userid
                 WHERE u.deleted = 0
                   AND ci.customcertid = :customcertid
                       $conditionssql";
        if ($sort) {
            $sql .= "ORDER BY " . $sort;
        } else {
            $sql .= "ORDER BY " . $DB->sql_fullname();
        }

        return $DB->get_records_sql($sql, $allparams, $limitfrom, $limitnum);
    }

    /**
     * Returns the total number of issues for a given customcert.
     *
     * @param int $customcertid
     * @param \stdClass $cm the course module
     * @param bool $groupmode the group mode
     * @return int the number of issues
     */
    public static function get_number_of_issues($customcertid, $cm, $groupmode) {
        global $DB;

        // Get the conditional SQL.
        list($conditionssql, $conditionsparams) = self::get_conditional_issues_sql($cm, $groupmode);

        // If it is empty then return 0.
        if (empty($conditionsparams)) {
            return 0;
        }

        // Add the conditional SQL and the customcertid to form all used parameters.
        $allparams = $conditionsparams + array('customcertid' => $customcertid);

        // Return the number of issues.
        $sql = "SELECT COUNT(u.id) as count
                  FROM {user} u
            INNER JOIN {customcert_issues} ci
                    ON u.id = ci.userid
                 WHERE u.deleted = 0
                   AND ci.customcertid = :customcertid
                       $conditionssql";
        return $DB->count_records_sql($sql, $allparams);
    }

    /**
     * Returns an array of the conditional variables to use in the get_issues SQL query.
     *
     * @param \stdClass $cm the course module
     * @param bool $groupmode are we in group mode ?
     * @return array the conditional variables
     */
    public static function get_conditional_issues_sql($cm, $groupmode) {
        global $DB, $USER;

        // Get all users that can manage this customcert to exclude them from the report.
        $context = \context_module::instance($cm->id);
        $conditionssql = '';
        $conditionsparams = array();

        // Get all users that can manage this certificate to exclude them from the report.
        $certmanagers = array_keys(get_users_by_capability($context, 'mod/customcert:manage', 'u.id'));
        $certmanagers = array_merge($certmanagers, array_keys(get_admins()));
        list($sql, $params) = $DB->get_in_or_equal($certmanagers, SQL_PARAMS_NAMED, 'cert');
        $conditionssql .= "AND NOT u.id $sql \n";
        $conditionsparams += $params;

        if ($groupmode) {
            $canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);
            $currentgroup = groups_get_activity_group($cm);

            // If we are viewing all participants and the user does not have access to all groups then return nothing.
            if (!$currentgroup && !$canaccessallgroups) {
                return array('', array());
            }

            if ($currentgroup) {
                if (!$canaccessallgroups) {
                    // Guest users do not belong to any groups.
                    if (isguestuser()) {
                        return array('', array());
                    }

                    // Check that the user belongs to the group we are viewing.
                    $usersgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
                    if ($usersgroups) {
                        if (!isset($usersgroups[$currentgroup])) {
                            return array('', array());
                        }
                    } else { // They belong to no group, so return an empty array.
                        return array('', array());
                    }
                }

                $groupusers = array_keys(groups_get_members($currentgroup, 'u.*'));
                if (empty($groupusers)) {
                    return array('', array());
                }

                list($sql, $params) = $DB->get_in_or_equal($groupusers, SQL_PARAMS_NAMED, 'grp');
                $conditionssql .= "AND u.id $sql ";
                $conditionsparams += $params;
            }
        }

        return array($conditionssql, $conditionsparams);
    }

    /**
     * Get number of certificates for a user.
     *
     * @param int $userid
     * @return int
     */
    public static function get_number_of_certificates_for_user($userid) {
        global $DB;

        $sql = "SELECT COUNT(*)
                  FROM {customcert} c
            INNER JOIN {customcert_issues} ci
                    ON c.id = ci.customcertid
                 WHERE ci.userid = :userid";
        return $DB->count_records_sql($sql, array('userid' => $userid));
    }

    /**
     * Gets the certificates for the user.
     *
     * @param int $userid
     * @param int $limitfrom
     * @param int $limitnum
     * @param string $sort
     * @return array
     */
    public static function get_certificates_for_user($userid, $limitfrom, $limitnum, $sort = '') {
        global $DB;

        if (empty($sort)) {
            $sort = 'ci.timecreated DESC';
        }

        $sql = "SELECT c.id, c.name, co.fullname as coursename, ci.code, ci.timecreated
                  FROM {customcert} c
            INNER JOIN {customcert_issues} ci
                    ON c.id = ci.customcertid
            INNER JOIN {course} co
                    ON c.course = co.id
                 WHERE ci.userid = :userid
              ORDER BY $sort";
        return $DB->get_records_sql($sql, array('userid' => $userid), $limitfrom, $limitnum);
    }

    /**
     * Issues a certificate to a user.
     *
     * @param int $certificateid The ID of the certificate
     * @param int $userid The ID of the user to issue the certificate to
     * @return int The ID of the issue
     */
    public static function issue_certificate($certificateid, $userid) {
        global $DB;

        $issue = new \stdClass();
        $issue->userid = $userid;
        $issue->customcertid = $certificateid;
        $issue->code = self::generate_code();
        $issue->emailed = 0;
        $issue->timecreated = time();

        // Insert the record into the database.
        return $DB->insert_record('customcert_issues', $issue);
    }

    /**
     * Generates a 10-digit code of random letters and numbers.
     *
     * @return string
     */
    public static function generate_code() {
        global $DB;

        $uniquecodefound = false;
        $code = random_string(10);
        while (!$uniquecodefound) {
            if (!$DB->record_exists('customcert_issues', array('code' => $code))) {
                $uniquecodefound = true;
            } else {
                $code = random_string(10);
            }
        }

        return $code;
    }
    /**
     * Checks if a course is completed by current user.
     *
     * @param int $course The course to be checked.
     * @return bool true if completed, otherwise false
     */
    public static function is_course_completed($course){
        global $USER;
        $info = new \completion_info($course);
        return $info->is_course_complete($USER->id);
    }
    /**
     * Fetches the completions
     *
     * @param int $course The course to be checked.
     * @return bool true if completed, otherwise false
     */
    public static function get_completion_requirements($course) {
        global $USER, $PAGE;
        $info = new \completion_info($course);
        $completions = $info->get_completions($USER->id);
        $modinfo = get_fast_modinfo($course);

        // Prepare return object.
        $completionview = new stdClass();
        $completionview->aggregation_method = '';
        // Markup for prerequisites.
        $completionview->prerequisites = array();
        $completionview->hasprerequisites = false;
        $completionview->prerequisitesaggall = false;
        $completionview->prerequisitesaggany = false;
        $completionview->prerequisitescompleted = false;
        // Markup for activities.
        $completionview->sections = array();
        $completionview->hasactivities = false;
        $completionview->activitiesaggall = false;
        $completionview->activitiesaggany = false;
        $completionview->activitiescompleted = false;
        // Markup for Grade.
        $completionview->hasgrade = new stdClass();
        $completionview->hasgrade->enabled = false;
        $completionview->hasgrade->grade = '';
        $completionview->hasgrade->complete = '';
        // Markup for Date.
        $completionview->hasdate = false;
        $completionview->dates = array();
        // Markup for Duration.
        $completionview->hasduration = false;
        $completionview->durations = array();
        // Markup for SelfCompletion.
        $completionview->hasselfcompletion = false;
        $completionview->selfcompletions = array();
        // Markup for Role.
        $completionview->hasrole = false;
        $completionview->roles = array();
        $completionview->rolesaggall = false;
        $completionview->rolesaggany = false;
        $completionview->rolescompleted = false;
        // Markup for Unenrolment.
        $completionview->hasunenrol = false;
        $completionview->unenrol = array();

        // Check for aggregation method
        $overall = $info->get_aggregation_method();
        if ($overall == COMPLETION_AGGREGATION_ALL) {
            $completionview->aggregationmethod = get_string('criteriarequiredall', 'completion');
        } else {
            $completionview->aggregationmethod = get_string('criteriarequiredany', 'completion');
        }
        // Prepare markup for mustache template and get section infos
        for ($i = 0; $i <= count($modinfo->get_sections()); $i++) {
            $sectionname = get_section_name($course, $i);
            array_push($completionview->sections, ["name" => $sectionname, "criterias" => []]);
        }
        // Loop through completions and handle different criteria types.
        $activitycount = 0;
        $prerequisitescount = 0;
        $rolescount = 0;
        $activitycompletedcount = 0;
        $prerequisitescompletedcount = 0;
        $rolescompletedcount = 0;
        foreach ($completions as $key => $completion) {
            $criteria = $completion->get_criteria();
            if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_GRADE) {
                $completionview->hasgrade->enabled = true;
                $completionview->hasgrade->grade = $completion->get_status();
                $completionview->hasgrade->complete = $completion->is_complete();
            }
            if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {
                // $cmid and $cm needed to get belonging section
                ++$activitycount;
                $completionview->hasactivities = true;
                $cmid = $criteria->moduleinstance;
                $cm = $modinfo->get_cm($cmid);
                if ($info->get_aggregation_method($criteria->criteriatype) == COMPLETION_AGGREGATION_ALL) {
                    $completionview->activitiesaggall = true;
                } else {
                    $completionview->activitiesaggany = true;
                }
                $row = array();
                $row['type'] = ucfirst($criteria->module);
                $row['status'] = $completion->get_status();
                $row['complete'] = $completion->is_complete();
                if ($completion->is_complete()) {
                    ++$activitycompletedcount;
                }
                $row['timecompleted'] = $completion->timecompleted ?
                        userdate($completion->timecompleted, get_string('strftimedate', 'langconfig')) : 0;
                $details = $criteria->get_details($completion);
                $row['requirement'] = $details["requirement"];
                $row['criteria'] = $details['criteria'];
                array_push($completionview->sections[$cm->sectionnum]["criterias"], $row);
            }

            if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_COURSE) {
                $completionview->hasprerequisites = true;
                ++$prerequisitescount;
                if ($info->get_aggregation_method($criteria->criteriatype) == COMPLETION_AGGREGATION_ALL) {
                    $completionview->prerequisitesaggall = true;
                } else {
                    $completionview->prerequisitesaggany = true;
                }
                $row = array();
                $row['status'] = $completion->get_status();
                $row['complete'] = $completion->is_complete();
                if ($completion->is_complete()) {
                    ++$prerequisitescompletedcount;
                }
                $row['timecompleted'] = $completion->timecompleted ?
                        userdate($completion->timecompleted, get_string('strftimedate', 'langconfig')) : 0;
                $details = $criteria->get_details($completion);
                $row['requirement'] = $details["requirement"];
                $row['criteria'] = $details['criteria'];
                array_push($completionview->prerequisites, $row);
            }
            if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_DATE) {
                $completionview->hasdate = true;
                $row = array();
                $row['status'] = $completion->get_status();
                $row['complete'] = $completion->is_complete();
                $details = $criteria->get_details($completion);
                $row['type'] = $details['type'];
                $row['criteria'] = $details['criteria'];
                $row['requirement'] = $details['requirement'];
                $row['timecompleted'] = $completion->timecompleted ?
                        userdate($completion->timecompleted, get_string('strftimedate', 'langconfig')) : 0;
                array_push($completionview->dates, $row);
            }
            if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_ROLE) {
                $completionview->hasrole = true;
                ++$rolescount;
                if ($info->get_aggregation_method($criteria->criteriatype) == COMPLETION_AGGREGATION_ALL) {
                    $completionview->rolesaggall = true;
                } else {
                    $completionview->rolesaggany = true;
                }
                $row = array();
                $row['status'] = $completion->get_status();
                $row['complete'] = $completion->is_complete();
                if ($completion->is_complete()) {
                    ++$rolescompletedcount;
                }
                $details = $criteria->get_details($completion);
                $row['type'] = $details['type'];
                $row['criteria'] = $details['criteria'];
                $row['requirement'] = $details['requirement'];
                $row['timecompleted'] = $completion->timecompleted ?
                        userdate($completion->timecompleted, get_string('strftimedate', 'langconfig')) : 0;
                array_push($completionview->roles, $row);
            }
            if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_SELF) {
                $completionview->hasselfcompletion = true;
                $row = array();
                $row['complete'] = $completion->is_complete();
                $details = $criteria->get_details($completion);
                $row['type'] = $details['type'];
                $row['requirement'] = $details['requirement'];
                $row['timecompleted'] = $completion->timecompleted ?
                        userdate($completion->timecompleted, get_string('strftimedate', 'langconfig')) : 0;
                array_push($completionview->selfcompletions, $row);
            }
            if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_UNENROL) {
                $completionview->hasunenrol = true;
                $row = array();
                $row['complete'] = $completion->is_complete();
                $details = $criteria->get_details($completion);
                $row['type'] = $details['type'];
                $row['requirement'] = $details['requirement'];
                $row['timecompleted'] = $completion->timecompleted ?
                        userdate($completion->timecompleted, get_string('strftimedate', 'langconfig')) : 0;
                array_push($completionview->unenrol, $row);
            }
            if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_DURATION) {
                $completionview->hasduration = true;
                $row = array();
                $row['status'] = $completion->get_status();
                $row['complete'] = $completion->is_complete();
                $details = $criteria->get_details($completion);
                $row['type'] = $details['type'];
                $row['criteria'] = $details['criteria'];
                $row['requirement'] = $details['requirement'];
                $row['timecompleted'] = $completion->timecompleted ?
                        userdate($completion->timecompleted, get_string('strftimedate', 'langconfig')) : 0;
                array_push($completionview->durations, $row);
            }
        }
        // We need to decide now based on activity completion aggregation,
        // if activity criteria is completed.
        // 1. Case: All activities need to be completed.
        if ($completionview->activitiesaggall &&
                ($activitycount == $activitycompletedcount)) {
            $completionview->activitiescompleted = true;
        } // 2. Case: Any activity needs to be completed.
        else if ($completionview->activitiesaggany &&
                $activitycompletedcount) {
            $completionview->activitiescompleted = true;
        }
        // Otherwise $completionview->activites_completed remains false.

        // We need to decide based on course prerequisites completion aggregation,
        // if prerequisites are fulfilled.
        // 1. Case: All prerequisites need to be completed.
        if ($completionview->prerequisitesaggall &&
                ($prerequisitescount == $prerequisitescompletedcount)) {
            $completionview->prerequisitescompleted = true;
        } // 2. Case: Any prerequisite needs to be completed
        else if ($completionview->prerequisitesaggany &&
                $prerequisitescompletedcount) {
            $completionview->prerequisitescompleted = true;
        }
        // We need to decide based on manually completion by other roles,
        // if this criteria is fulfilled.
        // 1. Case: All roles need to confirm.
        if ($completionview->rolesaggall &&
                ($rolescount == $rolescompletedcount)) {
            $completionview->rolescompleted = true;
        } // 2. Case: Any role needs to confirm.
        else if ($completionview->rolesaggany &&
                $rolescompletedcount) {
            $completionview->rolescompleted = true;
        }

        // Remove sections from output if no activity/criteria was added.
        foreach ($completionview->sections as $key => $section) {
            if (!count($section["criterias"])) {
                unset($completionview->sections[$key]);
            }
        }
        $renderer = $PAGE->get_renderer('mod_customcert');
        // Reindex array_values for mustache templates
        $completionview->sections = array_values($completionview->sections);
        return $renderer->render_from_template('mod_customcert/completionview', $completionview);
    }
}
