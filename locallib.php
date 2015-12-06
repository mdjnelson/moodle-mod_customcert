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
 * Customcert module internal API.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @var string the print protection variable
 */
define('PROTECTION_PRINT', 'print');

/**
 * @var string the modify protection variable
 */
define('PROTECTION_MODIFY', 'modify');

/**
 * @var string the copy protection variable
 */
define('PROTECTION_COPY', 'copy');

/**
 * @var int the number of issues that will be displayed on each page in the report
 * If you want to display all customcerts on a page set this to 0.
 */
define('CUSTOMCERT_PER_PAGE', 20);

/**
 * @var int the max number of issues to display
 */
define('CUSTOMCERT_MAX_PER_PAGE', 300);

/**
 * @var int the top-left of element
 */
define('CUSTOMCERT_REF_POINT_TOPLEFT', 0);

/**
 * @var int the top-center of element
 */
define('CUSTOMCERT_REF_POINT_TOPCENTER', 1);

/**
 * @var int the top-left of element
 */
define('CUSTOMCERT_REF_POINT_TOPRIGHT', 2);

/**
 * Handles setting the protection field for the customcert
 *
 * @param stdClass $data
 * @return string the value to insert into the protection field
 */
function customcert_set_protection($data) {
    $protection = array();

    if (!empty($data->protection_print)) {
        $protection[] = PROTECTION_PRINT;
    }
    if (!empty($data->protection_modify)) {
        $protection[] = PROTECTION_MODIFY;
    }
    if (!empty($data->protection_copy)) {
        $protection[] = PROTECTION_COPY;
    }

    // Return the protection string.
    return implode(', ', $protection);
}

/**
 * Handles uploading an image for the customcert module.
 *
 * @param int $draftitemid the draft area containing the files
 * @param int $contextid the context we are storing this image in
 */
function customcert_upload_imagefiles($draftitemid, $contextid) {
    // Save the file if it exists that is currently in the draft area.
    file_save_draft_area_files($draftitemid, $contextid, 'mod_customcert', 'image', 0);
}

/**
 * Return the list of possible elements to add.
 *
 * @return array the list of images that can be used.
 */
function customcert_get_elements() {
    global $CFG;

    // Array to store the element types.
    $options = array();

    // Check that the directory exists.
    $elementdir = "$CFG->dirroot/mod/customcert/element";
    if (file_exists($elementdir)) {
        // Get directory contents.
        $elementfolders = new DirectoryIterator($elementdir);
        // Loop through the elements folder.
        foreach ($elementfolders as $elementfolder) {
            // If it is not a directory or it is '.' or '..', skip it.
            if (!$elementfolder->isDir() || $elementfolder->isDot()) {
                continue;
            }
            // Check that the standard class file exists, if not we do
            // not want to display it as an option as it will not work.
            $foldername = $elementfolder->getFilename();
            $classfile = "$elementdir/$foldername/lib.php";
            if (file_exists($classfile)) {
                // Need to require this file in case if we choose to add this element.
                require_once($classfile);
                $component = "customcertelement_{$foldername}";
                $options[$foldername] = get_string('pluginname', $component);
            }
        }
    }

    core_collator::asort($options);
    return $options;
}

/**
 * Return the list of possible fonts to use.
 */
function customcert_get_fonts() {
    global $CFG;

    // Array to store the available fonts.
    $options = array();

    // Location of fonts in Moodle.
    $fontdir = "$CFG->dirroot/lib/tcpdf/fonts";
    // Check that the directory exists.
    if (file_exists($fontdir)) {
        // Get directory contents.
        $fonts = new DirectoryIterator($fontdir);
        // Loop through the font folder.
        foreach ($fonts as $font) {
            // If it is not a file, or either '.' or '..', or
            // the extension is not php, or we can not open file,
            // skip it.
            if (!$font->isFile() || $font->isDot() || ($font->getExtension() != 'php')) {
                continue;
            }
            // Set the name of the font to null, the include next should then set this
            // value, if it is not set then the file does not include the necessary data.
            $name = null;
            // Some files include a display name, the include next should then set this
            // value if it is present, if not then $name is used to create the display name.
            $displayname = null;
            // Some of the TCPDF files include files that are not present, so we have to
            // suppress warnings, this is the TCPDF libraries fault, grrr.
            @include("$fontdir/$font");
            // If no $name variable in file, skip it.
            if (is_null($name)) {
                continue;
            }
            // Remove the extension of the ".php" file that contains the font information.
            $filename = basename($font, ".php");
            // Check if there is no display name to use.
            if (is_null($displayname)) {
                // Format the font name, so "FontName-Style" becomes "Font Name - Style".
                $displayname = preg_replace("/([a-z])([A-Z])/", "$1 $2", $name);
                $displayname = preg_replace("/([a-zA-Z])-([a-zA-Z])/", "$1 - $2", $displayname);
            }
            $options[$filename] = $displayname;
        }
        ksort($options);
    }

    return $options;
}

/**
 * Return the list of possible font sizes to use.
 */
function customcert_get_font_sizes() {
    // Array to store the sizes.
    $sizes = array();

    for ($i = 1; $i <= 60; $i++) {
        $sizes[$i] = $i;
    }

    return $sizes;
}

/**
 * Handles saving page data.
 *
 * @param stdClass $data the customcert data
 */
function customcert_save_page_data($data) {
    global $DB;

    // Set the time to a variable.
    $time = time();

    // Get the existing pages and save the page data.
    if ($pages = $DB->get_records('customcert_pages', array('customcertid' => $data->id))) {
        // Loop through existing pages.
        foreach ($pages as $page) {
            // Get the name of the fields we want from the form.
            $width = 'pagewidth_' . $page->id;
            $height = 'pageheight_' . $page->id;
            $margin = 'pagemargin_' . $page->id;
            // Create the page data to update the DB with.
            $p = new stdClass();
            $p->id = $page->id;
            $p->width = $data->$width;
            $p->height = $data->$height;
            $p->margin = $data->$margin;
            $p->timemodified = $time;
            // Update the page.
            $DB->update_record('customcert_pages', $p);
        }
    }
}

/**
 * Returns an instance of the element class.
 *
 * @param stdClass $element the element
 * @return stdClass|bool returns the instance of the element class, or false if element
 *         class does not exists.
 */
function customcert_get_element_instance($element) {
    global $CFG;

    $classfile = "$CFG->dirroot/mod/customcert/element/{$element->element}/lib.php";
    // Ensure this necessary file exists.
    if (file_exists($classfile)) {
        require_once($classfile);
        $classname = "customcert_element_{$element->element}";
        return new $classname($element);
    }

    return false;
}

/**
 * Handles adding another page to the customcert.
 *
 * @param stdClass $data the form data
 */
function customcert_add_page($data) {
    global $DB;

    // Set the page number to 1 to begin with.
    $pagenumber = 1;
    // Get the max page number.
    $sql = "SELECT MAX(pagenumber) as maxpage
              FROM {customcert_pages} cp
             WHERE cp.customcertid = :customcertid";
    if ($maxpage = $DB->get_record_sql($sql, array('customcertid' => $data->id))) {
        $pagenumber = $maxpage->maxpage + 1;
    }

    // Store time in a variable.
    $time = time();

    // New page creation.
    $page = new stdClass();
    $page->customcertid = $data->id;
    $page->width = '210';
    $page->height = '297';
    $page->pagenumber = $pagenumber;
    $page->timecreated = $time;
    $page->timemodified = $time;

    // Insert the page.
    $DB->insert_record('customcert_pages', $page);
}

/**
 * Handles deleting an element from the customcert.
 *
 * @param int $elementid the customcert page
 */
function customcert_delete_element($elementid) {
    global $DB;

    // Ensure element exists and delete it.
    $element = $DB->get_record('customcert_elements', array('id' => $elementid), '*', MUST_EXIST);

    // Get an instance of the element class.
    if ($e = customcert_get_element_instance($element)) {
        $e->delete_element();
    } else {
        // The plugin files are missing, so just remove the entry from the DB.
        $DB->delete_records('customcert_elements', array('id' => $elementid));
    }

    // Now we want to decrease the sequence numbers of the elements
    // that are greater than the element we deleted.
    $sql = "UPDATE {customcert_elements}
               SET sequence = sequence - 1
             WHERE pageid = :pageid
               AND sequence > :sequence";
    $DB->execute($sql, array('pageid' => $element->pageid, 'sequence' => $element->sequence));
}

/**
 * Handles deleting a page from the customcert.
 *
 * @param int $pageid the customcert page
 */
function customcert_delete_page($pageid) {
    global $DB;

    // Get the page.
    $page = $DB->get_record('customcert_pages', array('id' => $pageid), '*', MUST_EXIST);

    // Delete this page.
    $DB->delete_records('customcert_pages', array('id' => $page->id));

    // The element may have some extra tasks it needs to complete to completely delete itself.
    if ($elements = $DB->get_records('customcert_elements', array('pageid' => $page->id))) {
        foreach ($elements as $element) {
            // Get an instance of the element class.
            if ($e = customcert_get_element_instance($element)) {
                $e->delete_element();
            } else {
                // The plugin files are missing, so just remove the entry from the DB.
                $DB->delete_records('customcert_elements', array('id' => $element->id));
            }
        }
    }

    // Now we want to decrease the page number values of
    // the pages that are greater than the page we deleted.
    $sql = "UPDATE {customcert_pages}
               SET pagenumber = pagenumber - 1
             WHERE customcertid = :customcertid
               AND pagenumber > :pagenumber";
    $DB->execute($sql, array('customcertid' => $page->customcertid, 'pagenumber' => $page->pagenumber));
}

/**
 * Returns a list of all the templates.
 *
 * @return bool returns true if success, false otherwise
 */
function customcert_get_templates() {
    global $DB;

    return $DB->get_records_menu('customcert_template', array(), 'name ASC', 'id, name');
}

/**
 * Get the time the user has spent in the course.
 *
 * @param int $courseid
 * @return int the total time spent in seconds
 */
function customcert_get_course_time($courseid) {
    global $CFG, $DB, $USER;

    $logmanager = get_log_manager();
    $readers = $logmanager->get_readers();
    $enabledreaders = get_config('tool_log', 'enabled_stores');
    $enabledreaders = explode(',', $enabledreaders);

    // Go through all the readers until we find one that we can use.
    foreach ($enabledreaders as $enabledreader) {
        $reader = $readers[$enabledreader];
        if ($reader instanceof \logstore_legacy\log\store) {
            $logtable = 'log';
            $coursefield = 'course';
            $timefield = 'time';
            break;
        } else if ($reader instanceof \core\log\sql_internal_reader) {
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
    $params = array('userid' => $USER->id, 'courseid' => $courseid);
    $totaltime = 0;
    if ($logs = $DB->get_recordset_sql($sql, $params)) {
        foreach ($logs as $log) {
            if (!isset($login)) {
                // For the first time $login is not set so the first log is also the first login
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
            // Now the actual log became the previous log for the next cycle
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
 * @param stdClass $cm the course module
 * @param int $page offset
 * @param int $perpage total per page
 * @return stdClass the users
 */
function customcert_get_issues($customcertid, $groupmode, $cm, $page, $perpage) {
    global $DB;

    // Get the conditional SQL.
    list($conditionssql, $conditionsparams) = customcert_get_conditional_issues_sql($cm, $groupmode);

    // If it is empty then return an empty array.
    if (empty($conditionsparams)) {
        return array();
    }

    // Add the conditional SQL and the customcertid to form all used parameters.
    $allparams = $conditionsparams + array('customcertid' => $customcertid);

    // Return the issues.
    $sql = "SELECT u.*, ci.code, ci.timecreated
              FROM {user} u
        INNER JOIN {customcert_issues} ci
                ON u.id = ci.userid
             WHERE u.deleted = 0
               AND ci.customcertid = :customcertid
                   $conditionssql
          ORDER BY " . $DB->sql_fullname();
    return $DB->get_records_sql($sql, $allparams, $page * $perpage, $perpage);
}

/**
 * Returns the total number of issues for a given customcert.
 *
 * @param int $customcertid
 * @param stdClass $cm the course module
 * @param bool $groupmode the group mode
 * @return int the number of issues
 */
function customcert_get_number_of_issues($customcertid, $cm, $groupmode) {
    global $DB;

    // Get the conditional SQL.
    list($conditionssql, $conditionsparams) = customcert_get_conditional_issues_sql($cm, $groupmode);

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
 * @param stdClass $cm the course module
 * @param bool $groupmode are we in group mode ?
 * @return array the conditional variables
 */
function customcert_get_conditional_issues_sql($cm, $groupmode) {
    global $DB, $USER;

    // Get all users that can manage this customcert to exclude them from the report.
    $context = context_module::instance($cm->id);
    $conditionssql = '';
    $conditionsparams = array();

    // Get all users that can manage this certificate to exclude them from the report.
    $certmanagers = array_keys(get_users_by_capability($context, 'mod/certificate:manage', 'u.id'));
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
 * Generates a 10-digit code of random letters and numbers.
 *
 * @return string
 */
function customcert_generate_code() {
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
 * Generate the PDF for the specified customcert and user.
 *
 * @param stdClass $customcert
 * @param bool $preview true if it is a preview, false otherwise
 */
function customcert_generate_pdf($customcert, $preview = false) {
    global $CFG, $DB;

    require_once($CFG->libdir . '/pdflib.php');

    // Get the pages for the customcert, there should always be at least one page for each customcert.
    if ($pages = $DB->get_records('customcert_pages', array('customcertid' => $customcert->id), 'pagenumber ASC')) {
        // Create the pdf object.
        $pdf = new pdf();
        if (!empty($customcert->protection)) {
            $protection = explode(', ', $customcert->protection);
            $pdf->SetProtection($protection);
        }
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetTitle($customcert->name);
        $pdf->SetAutoPageBreak(true, 0);
        // Remove full-stop at the end, if it exists, to avoid "..pdf" being created and being filtered by clean_filename.
        $filename = rtrim($customcert->name, '.');
        $filename = clean_filename($filename . '.pdf');
        // Loop through the pages and display their content.
        foreach ($pages as $page) {
            // Add the page to the PDF.
            if ($page->width > $page->height) {
                $orientation = 'L';
            } else {
                $orientation = 'P';
            }
            $pdf->AddPage($orientation, array($page->width, $page->height));
            $pdf->SetMargins(0, 0, $page->margin);
            // Get the elements for the page.
            if ($elements = $DB->get_records('customcert_elements', array('pageid' => $page->id), 'sequence ASC')) {
                // Loop through and display.
                foreach ($elements as $element) {
                    // Get an instance of the element class.
                    if ($e = customcert_get_element_instance($element)) {
                        $e->render($pdf, $preview);
                    }
                }
            }
        }
        $pdf->Output($filename, 'D');
    }
}

/**
 * Generate the report.
 *
 * @param stdClass $customcert
 * @param stdClass $users the list of users who have had a customcert issued
 * @param string $type
 */
function customcert_generate_report_file($customcert, $users, $type) {
    global $CFG, $COURSE;

    if ($type == 'ods') {
        require_once($CFG->libdir . '/odslib.class.php');
        $workbook = new MoodleODSWorkbook('-');
    } else if ($type == 'xls') {
        require_once($CFG->libdir . '/excellib.class.php');
        $workbook = new MoodleExcelWorkbook('-');
    }

    $filename = clean_filename($COURSE->shortname . ' ' . rtrim($customcert->name, '.') . '.' . $type);

    // Send HTTP headers.
    $workbook->send($filename);

    // Creating the first worksheet.
    $myxls = $workbook->add_worksheet(get_string('report', 'customcert'));

    // Print names of all the fields.
    $myxls->write_string(0, 0, get_string('lastname'));
    $myxls->write_string(0, 1, get_string('firstname'));
    $myxls->write_string(0, 2, get_string('idnumber'));
    $myxls->write_string(0, 3, get_string('group'));
    $myxls->write_string(0, 4, get_string('receiveddate', 'customcert'));
    $myxls->write_string(0, 5, get_string('code', 'customcert'));

    // Generate the data for the body of the spreadsheet.
    $row = 1;
    if ($users) {
        foreach ($users as $user) {
            $myxls->write_string($row, 0, $user->lastname);
            $myxls->write_string($row, 1, $user->firstname);
            $studentid = (!empty($user->idnumber)) ? $user->idnumber : ' ';
            $myxls->write_string($row, 2, $studentid);
            $ug2 = '';
            if ($usergrps = groups_get_all_groups($COURSE->id, $user->id)) {
                foreach ($usergrps as $ug) {
                    $ug2 = $ug2 . $ug->name;
                }
            }
            $myxls->write_string($row, 3, $ug2);
            $myxls->write_string($row, 4, userdate($user->timecreated));
            $myxls->write_string($row, 5, $user->code);
            $row++;
        }
    }
    // Close the workbook.
    $workbook->close();
}
