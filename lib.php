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
 * @package    mod
 * @subpackage customcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/grade/querylib.php');

/**
 * Add customcert instance.
 *
 * @param stdClass $customcert
 * @param stdClass $mform
 * @return int new customcert instance id
 */
function customcert_add_instance($data, $mform) {
    global $DB;

    $time = time();
    $data->timecreated = $time;
    $data->timemodified = $data->timecreated;

    return $DB->insert_record('customcert', $data);

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

    // Update the time modified.
    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record('customcert', $data);
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
    if (!$customcert = $DB->get_record('customcert', array('id' => $id))) {
        return false;
    }

    // Get the course module used when deleting files associated to the customcert.
    if (!$cm = get_coursemodule_from_instance('customcert', $id)) {
        return false;
    }

    // Delete the customcert instance.
    if (!$DB->delete_records('customcert', array('id' => $id))) {
        return false;
    }

    // Delete the customcert issues.
    if (!$DB->delete_records('customcert_issues', array('customcertid' => $id))) {
        return false;
    }

    // Get all the customcert pages.
    if ($pages = $DB->get_records('customcert_pages', array('customcertid' => $id))) {
        // Loop through pages.
        foreach ($pages as $p) {
            // Delete the text fields.
            if (!$DB->delete_records('customcert_text_fields', array('customcertpageid' => $p->id))) {
                return false;
            }
        }
        // Delete the pages
        if (!$DB->delete_records('customcert_pages', array('customcertid' => $id))) {
            return false;
        }
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
                new moodle_url('/mod/customcert/edit.php', array('cmid'=>$PAGE->cm->id)),
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
 * Handles saving page data.
 *
 * @param stdClass $data the customcert data
 */
function customcert_save_page_data($data) {
    global $DB;

    // Set the time to a variable.
    $time = time();

    // Get the total number of pages that exist for this customcert.
    $totalpages = $DB->count_records('customcert_pages', array('customcertid' => $data->id));

    // Check if this file has any pages yet, if not we are inserting.
    if ($totalpages == 0) {
        // Create the page to insert.
        $page = new stdClass();
        $page->customcertid = $data->id;
        $page->orientation = $data->orientation_1;
        $page->width = $data->width_1;
        $page->height = $data->height_1;
        $page->backgroundimage = $data->backgroundimage_1;
        $page->pagenumber = 1;
        $page->timecreated = $time;
        $page->timemodified = $time;
        // Insert the page.
        $DB->insert_record('customcert_pages', $page);
    } else {
        // Go through the data and check for any page data.
        foreach ($data as $key => $value) {
            if (strpos($key, 'orientation_') !== false) {
                // Get the page id.
                $pageid = str_replace('orientation_', '', $key);
                // Get the rest of the elements now.
                $orientation = "orientation_$pageid";
                $width = "width_$pageid";
                $height = "height_$pageid";
                $backgroundimage = "backgroundimage_$pageid";
                // Create the page to update.
                $page = new stdClass();
                $page->id = $pageid;
                $page->customcertid = $data->id;
                $page->orientation = $data->$orientation;
                $page->width = $data->$width;
                $page->height = $data->$height;
                $page->backgroundimage = $data->$backgroundimage;
                $page->timemodified = $time;
                // Update the page.
                $DB->update_record('customcert_pages', $page);
            }
        }
    }
}

/**
 * Handles adding another page to the customcert.
 *
 * @param stdClass $data the customcert data
 */
function customcert_add_page($data) {
    global $DB;

    // Set the number of the page we are creating.
    $pagenum = 1;
    // Check if there already pages that exist, if so, overwrite value.
    $sql = "SELECT MAX(pagenumber) as maxpagenumber
            FROM {customcert_pages}
            WHERE customcertid = :id";
    // Get the current max page number and add 1 to page number for new page.
    if ($maxnum = $DB->get_record_sql($sql, array('id' => $data->id))) {
        $pagenum = $maxnum->maxpagenumber + 1;
    }

    // New page creation.
    $time = time();
    $page = new stdClass();
    $page->customcertid = $data->id;
    $page->orientation = 'P';
    $page->pagenumber = $pagenum;
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
    global $DB;

    // Get the page.
    $page = $DB->get_record('customcert_pages', array('id' => $pageid), '*', MUST_EXIST);

    // Delete this page.
    $DB->delete_records('customcert_pages', array('id' => $page->id));

    // Delete any text fields belonging to this page.
    $DB->delete_records('customcert_text_fields', array('customcertpageid' => $page->id));

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
