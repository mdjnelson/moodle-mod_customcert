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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Customcert module core interaction API.
 *
 * @package    mod_customcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

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
 * Add customcert instance.
 *
 * @param stdClass $customcert
 * @param stdClass $mform
 * @return int new customcert instance id
 */
function customcert_add_instance($data, $mform) {
    global $DB;

    $data->protection = customcert_set_protection($data);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('customcert', $data);

    // Add a page to this customcert.
    customcert_add_page($data);

    return $data->id;
}

/**
 * Update customcert instance.
 *
 * @param stdClass $data
 * @param stdClass $mform
 * @return bool true
 */
function customcert_update_instance($data, $mform) {
    global $DB;

    $data->protection = customcert_set_protection($data);
    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record('customcert', $data);
}

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
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id
 * @return bool true if successful
 */
function customcert_delete_instance($id) {
    global $DB;

    // Ensure the customcert exists.
    if (!$DB->get_record('customcert', array('id' => $id))) {
        return false;
    }

    // Get the course module as it is used when deleting files.
    if (!$cm = get_coursemodule_from_instance('customcert', $id)) {
        return false;
    }

    // Delete the customcert instance.
    if (!$DB->delete_records('customcert', array('id' => $id))) {
        return false;
    }

    // Delete the elements.
    $sql = "SELECT e.*
            FROM {customcert_elements} e
            INNER JOIN {customcert_pages} p
            ON e.pageid = p.id
            WHERE p.customcertid = :customcertid";
    if ($elements = $DB->get_records_sql($sql, array('customcertid' => $id))) {
        foreach ($elements as $element) {
            // Get an instance of the element class.
            if ($e = customcert_get_element_instance($element)) {
                return $e->delete_element();
            }
        }
    }

    // Delete the pages.
    if (!$DB->delete_records('customcert_pages', array('customcertid' => $id))) {
        return false;
    }

    // Delete the customcert issues.
    if (!$DB->delete_records('customcert_issues', array('customcertid' => $id))) {
        return false;
    }

    // Delete any files associated with the customcert.
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    return true;
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified customcert
 * and clean up any related data.
 *
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function customcert_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'customcert');
    $status = array();

    if (!empty($data->reset_customcert)) {
        $sql = "SELECT cert.id
                FROM {customcert} cert
                WHERE cert.course = :courseid";
        $DB->delete_records_select('customcert_issues', "customcertid IN ($sql)", array('courseid' => $data->courseid));
        $status[] = array('component' => $componentstr, 'item' => get_string('customcertremoved', 'customcert'), 'error' => false);
    }

    // Updating dates - shift may be negative too.
    if ($data->timeshift) {
        shift_course_mod_dates('customcert', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component' => $componentstr, 'item' => get_string('datechanged'), 'error' => false);
    }

    return $status;
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the customcert.
 *
 * @param $mform form passed by reference
 */
function customcert_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'customcertheader', get_string('modulenameplural', 'customcert'));
    $mform->addElement('advcheckbox', 'reset_customcert', get_string('deletissuedcustomcerts', 'customcert'));
}

/**
 * Course reset form defaults.
 *
 * @param stdClass $course
 * @return array
 */
function customcert_reset_course_form_defaults($course) {
    return array('reset_customcert' => 1);
}

/**
 * Returns information about received customcert.
 * Used for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $customcert
 * @return stdClass the user outline object
 */
function customcert_user_outline($course, $user, $mod, $customcert) {
    global $DB;

    $result = new stdClass();
    if ($issue = $DB->get_record('customcert_issues', array('customcertid' => $customcert->id, 'userid' => $user->id))) {
        $result->info = get_string('issued', 'customcert');
        $result->time = $issue->timecreated;
    } else {
        $result->info = get_string('notissued', 'customcert');
    }

    return $result;
}

/**
 * Returns information about received customcert.
 * Used for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $page
 * @return string the user complete information
 */
function customcert_user_complete($course, $user, $mod, $customcert) {
   global $DB, $OUTPUT;

   if ($issue = $DB->get_record('customcert_issues', array('customcertid' => $customcert->id, 'userid' => $user->id))) {
        echo $OUTPUT->box_start();
        echo get_string('issued', 'customcert') . ": ";
        echo userdate($issue->timecreated);
        customcert_print_user_files($customcert->id, $user->id);
        echo '<br />';
        echo $OUTPUT->box_end();
    } else {
        print_string('notissuedyet', 'customcert');
    }
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function customcert_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

/**
 * Used for course participation report (in case customcert is added).
 *
 * @return array
 */
function customcert_get_view_actions() {
    return array('view', 'view all', 'view report');
}

/**
 * Used for course participation report (in case customcert is added).
 *
 * @return array
 */
function customcert_get_post_actions() {
    return array('received');
}

/**
 * Function to be run periodically according to the moodle cron.
 */
function customcert_cron () {
    return true;
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called.
 *
 * @param settings_navigation $settings
 * @param navigation_node $customcertnode
 */
function customcert_extend_settings_navigation(settings_navigation $settings, navigation_node $customcertnode) {
    global $PAGE, $CFG;

    $keys = $customcertnode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/customcert:manage', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('editcustomcert', 'customcert'),
                new moodle_url('/mod/customcert/edit.php', array('cmid' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_customcert_edit',
                new pix_icon('t/edit', ''));
        $customcertnode->add_node($node, $beforekey);
    }

    return $customcertnode->trim_if_empty();
}

/**
 * Return the list of possible images to use.
 *
 * @return array the list of images that can be used.
 */
function customcert_get_images() {
    // Create file storage object.
    $fs = get_file_storage();

    // The array used to store the images.
    $arrfiles = array();
    $arrfiles[0] = get_string('noimage', 'customcert');
    if ($files = $fs->get_area_files(context_system::instance()->id, 'mod_customcert', 'image', false, 'filename', false)) {
        foreach ($files as $hash => $file) {
            $arrfiles[$hash] = $file->get_filename();
        }
    }

    return $arrfiles;
}

/**
 * Handles uploading an image for the customcert module.
 *
 * @param stdClass $draftiemtid the draft area containing the files
 */
function customcert_upload_imagefiles($draftitemid) {
    // Get the filestorage object.
    $fs = get_file_storage();

    // Save the file if it exists that is currently in the draft area.
    file_save_draft_area_files($draftitemid, context_system::instance()->id, 'mod_customcert', 'image', 0);
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
    $elementdir = "$CFG->dirroot/mod/customcert/elements";
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
    global $CFG, $DB;

    // Set the time to a variable.
    $time = time();

    // Get the existing pages and save the page data.
    if ($pages = $DB->get_records('customcert_pages', array('customcertid' => $data->id))) {
        // Loop through existing pages.
        foreach ($pages as $page) {
            // Get the name of the fields we want from the form.
            $orientation = 'orientation_' . $page->id;
            $width = 'width_' . $page->id;
            $height = 'height_' . $page->id;
            // Create the page data to update the DB with.
            $p = new stdClass();
            $p->id = $page->id;
            $p->orientation = $data->$orientation;
            $p->width = $data->$width;
            $p->height = $data->$height;
            $p->timemodified = $time;
            // Update the page.
            $DB->update_record('customcert_pages', $p);
            // Get the elements for the page.
            if ($elements = $DB->get_records('customcert_elements', array('pageid' => $page->id))) {
                // Loop through the elements.
                foreach ($elements as $element) {
                    // Get an instance of the element class.
                    if ($e = customcert_get_element_instance($element)) {
                        $e->save_form_elements($data);
                    }
                }
            }
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

    $classfile = "$CFG->dirroot/mod/customcert/elements/{$element->element}/lib.php";
    // Ensure this necessary file exists.
    if (file_exists($classfile)) {
        require_once($classfile);
        $classname = "customcert_element_{$element->element}";
        return new $classname($element);
    }

    return false;
}

/**
 * Handles adding another element to a page in the customcert.
 *
 * @param string $element the name of the element
 * @param int $pageid the page id we are saving it to
 */
function customcert_add_element($element, $pageid) {
    global $CFG;

    $classname = "customcert_element_{$element}";
    $classname::add_element($element, $pageid);
}

/**
 * Handles adding another page to the customcert.
 *
 * @param stdClass $data the form data
 */
function customcert_add_page($data) {
    global $DB;

    // If no pageid is passed then we are creating the first page.
    if (empty($data->pageid)) {
        $pagenumber = 1;
    } else { // Create a page after an existing one.
        // Get the page we are inserting the new one after.
        $currentpage = $DB->get_record('customcert_pages', array('id' => $data->pageid), '*', MUST_EXIST);

        // Increase the page numbers of the pages that are going
        // to be in front of the new page we are creating
        $sql = "UPDATE {customcert_pages}
                SET pagenumber = pagenumber + 1
                WHERE customcertid = :customcertid
                AND pagenumber > :pagenumber";
        $DB->execute($sql, array('customcertid' => $currentpage->customcertid,
                                 'pagenumber' => $currentpage->pagenumber));

        $pagenumber = $currentpage->pagenumber + 1;
    }

    // Store time in a variable.
    $time = time();

    // New page creation.
    $page = new stdClass();
    $page->customcertid = $data->id;
    $page->orientation = 'P';
    $page->width = '210';
    $page->height = '297';
    $page->pagenumber = $pagenumber;
    $page->timecreated = $time;
    $page->timemodified = $time;

    // Insert the page.
    $DB->insert_record('customcert_pages', $page);
}

/**
 * Handles deleting a page from the customcert.
 *
 * @param int $pageid the customcert page
 */
function customcert_delete_page($pageid) {
    global $CFG, $DB;

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
            }
        }
    }

    // Now we want to decrease the page number values of
    // the pages that are greater than the page we deleted.
    $sql = "UPDATE {customcert_pages}
            SET pagenumber = pagenumber - 1
            WHERE customcertid = :customcertid
            AND pagenumber > :pagenumber";
    $DB->execute($sql, array('customcertid' => $page->customcertid,
                             'pagenumber' => $page->pagenumber));
}

/**
 * Returns a list of all the templates.
 *
 * @param stdClass $element the element
 * @param bool returns true if success, false otherwise
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
    global $CFG, $USER;

    set_time_limit(0);

    $totaltime = 0;
    $sql = "l.course = :courseid AND l.userid = :userid";
    if ($logs = get_logs($sql, array('courseid' => $courseid, 'userid' => $USER->id), 'l.time ASC', '', '', $totalcount)) {
        foreach ($logs as $log) {
            if (!isset($login)) {
                // For the first time $login is not set so the first log is also the first login.
                $login = $log->time;
                $lasthit = $log->time;
                $totaltime = 0;
            }
            $delay = $log->time - $lasthit;
            if ($delay > ($CFG->sessiontimeout * 60)) {
                // The difference between the last log and the current log is more than
                // the timeout register session value meaning we have found a session.
                $login = $log->time;
            } else {
                $totaltime += $delay;
            }
            // Now the actual log became the previous log for the next cycle.
            $lasthit = $log->time;
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
    global $CFG, $DB;

    list($conditionssql, $conditionsparams) = customcert_get_conditional_issues_sql($cm, $groupmode);

    // Get all the users that have customcerts issued, should only be one issue per user for a customcert
    $allparams = $conditionsparams + array('customcertid' => $customcertid);

    $users = $DB->get_records_sql("SELECT u.*, ci.code, ci.timecreated
                                   FROM {user} u
                                   INNER JOIN {customcert_issues} ci
                                   ON u.id = ci.userid
                                   WHERE u.deleted = 0
                                   AND ci.customcertid = :customcertid
                                   $conditionssql
                                   ORDER BY " . $DB->sql_fullname(),
                                   $allparams,
                                   $page * $perpage,
                                   $perpage);


    return $users;
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

    list($conditionssql, $conditionsparams) = customcert_get_conditional_issues_sql($cm, $groupmode);

    // Get all the users that have customcerts issued, should only be one issue per user for a customcert.
    $allparams = $conditionsparams + array('customcertid' => $customcertid);

    return $DB->count_records_sql("SELECT COUNT(u.*) as count
                                   FROM {user} u
                                   INNER JOIN {customcert_issues} ci
                                   ON u.id = ci.userid
                                   WHERE u.deleted = 0
                                   AND ci.customcertid = :customcertid
                                   $conditionssql",
                                   $allparams);
}

/**
 * Returns an array of the conditional variables to use in the get_issues SQL query.
 *
 * @param stdClass $cm the course module
 * @param bool $groupmode are we in group mode ?
 * @return array the conditional variables
 */
function customcert_get_conditional_issues_sql($cm, $groupmode) {
    global $CFG, $DB;

    // Get all users that can manage this customcert to exclude them from the report.
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $conditionssql = '';
    $conditionsparams = array();
    if ($certmanagers = array_keys(get_users_by_capability($context, 'mod/customcert:manage', 'u.id'))) {
        list($sql, $params) = $DB->get_in_or_equal($certmanagers, SQL_PARAMS_NAMED, 'cert');
        $conditionssql .= "AND NOT u.id $sql \n";
        $conditionsparams += $params;
    }

    $restricttogroup = false;
    if ($groupmode) {
        $currentgroup = groups_get_activity_group($cm);
        if ($currentgroup) {
            $restricttogroup = true;
            $groupusers = array_keys(groups_get_members($currentgroup, 'u.*'));
            if (empty($groupusers)) {
                return array();
            }
        }
    }

    $restricttogrouping = false;

    // If groupmembersonly used, remove users who are not in any group.
    if (!empty($CFG->enablegroupings) and $cm->groupmembersonly) {
        if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
            $restricttogrouping = true;
        } else {
            return array();
        }
    }

    if ($restricttogroup || $restricttogrouping) {
        if ($restricttogroup) {
            $allowedusers = $groupusers;
        } else if ($restricttogroup && $restricttogrouping) {
            $allowedusers = array_intersect($groupusers, $groupingusers);
        } else  {
            $allowedusers = $groupingusers;
        }

        list($sql, $params) = $DB->get_in_or_equal($allowedusers, SQL_PARAMS_NAMED, 'grp');
        $conditionssql .= "AND u.id $sql \n";
        $conditionsparams += $params;
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
 * @param int $userid
 */
function customcert_generate_pdf($customcert, $userid) {
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
        // Remove full-stop at the end, if it exists, to avoid "..pdf" being created and being filtered by clean_filename.
        $filename = rtrim($customcert->name, '.');
        $filename = clean_filename($filename . '.pdf');
        // Loop through the pages and display their content.
        foreach ($pages as $page) {
            // Add the page to the PDF.
            $pdf->AddPage($page->orientation, array($page->width, $page->height));
            // Get the elements for the page.
            if ($elements = $DB->get_records('customcert_elements', array('pageid' => $page->id), 'sequence ASC')) {
                // Loop through and display.
                foreach ($elements as $element) {
                    // Get an instance of the element class.
                    if ($e = customcert_get_element_instance($element)) {
                        $e->render($pdf, $userid);
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

    // Send HTTP headers
    $workbook->send($filename);

    // Creating the first worksheet
    $myxls = $workbook->add_worksheet(get_string('report', 'customcert'));

    // Print names of all the fields
    $myxls->write_string(0, 0, get_string('lastname'));
    $myxls->write_string(0, 1, get_string('firstname'));
    $myxls->write_string(0, 2, get_string('idnumber'));
    $myxls->write_string(0, 3, get_string('group'));
    $myxls->write_string(0, 4, get_string('receiveddate', 'customcert'));
    $myxls->write_string(0, 5, get_string('code', 'customcert'));

    // Generate the data for the body of the spreadsheet
    $i = 0;
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
    // Close the workbook
    $workbook->close();
}
