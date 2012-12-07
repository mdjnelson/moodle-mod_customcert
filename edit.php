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
 * Edit the customcert settings.
 *
 * @package    mod_customcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');
require_once($CFG->dirroot . '/mod/customcert/edit_form.php');

$cmid = required_param('cmid', PARAM_INT);
$moveup = optional_param('moveup', 0, PARAM_INT);
$movedown = optional_param('movedown', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$cm = get_coursemodule_from_id('customcert', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$customcert = $DB->get_record('customcert', array('id'=>$cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/customcert:manage', $context);

// Check if they are moving a custom certificate page.
if ((!empty($moveup)) || (!empty($movedown))) {
    // Check if we are moving a page up.
    if (!empty($moveup)) {
        if ($movecertpage = $DB->get_record('customcert_pages', array('id' => $moveup))) {
            $swapcertpage = $DB->get_record('customcert_pages', array('pagenumber' => $movecertpage->pagenumber - 1));
        }
    } else { // Must be moving a page down.
        if ($movecertpage = $DB->get_record('customcert_pages', array('id' => $movedown))) {
            $swapcertpage = $DB->get_record('customcert_pages', array('pagenumber' => $movecertpage->pagenumber + 1));
        }
    }
    // Check that there is a page to move, and a page to swap it with.
    if ($swapcertpage && $movecertpage) {
        $DB->set_field('customcert_pages', 'pagenumber', $swapcertpage->pagenumber, array('id' => $movecertpage->id));
        $DB->set_field('customcert_pages', 'pagenumber', $movecertpage->pagenumber, array('id' => $swapcertpage->id));
    }
} else if ((!empty($delete)) && (!empty($confirm))) {
    customcert_delete_page($delete);
}

$mform = new mod_customcert_edit_form('', array('customcertid' => $customcert->id,
                                                'cmid' => $cm->id,
                                                'course' => $course));

if ($data = $mform->get_data()) {
    // Handle file uploads.
    customcert_upload_imagefiles($data->customcertimage);

    // Save any page data.
    customcert_save_page_data($data);

    // Check if they requested to delete a page.
    foreach ($data as $key => $value) {
        if (strpos($key, 'deletecertpage_') !== false) {
            $pageid = str_replace('deletecertpage_', '', $key);
            // Create the link options.
            $nourl = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cm->id));
            $yesurl = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cm->id,
                                                                       'delete' => $pageid,
                                                                       'confirm' => 1,
                                                                       'sesskey' => sesskey()));
            // Show a confirmation page.
            $strheading = get_string('deletecertpage', 'customcert');
            $PAGE->navbar->add($strheading);
            $PAGE->set_title($strheading);
            $PAGE->set_heading($COURSE->fullname);
            $PAGE->set_url('/mod/customcert/edit.php', array('cmid' => $cmid));
            echo $OUTPUT->header();
            echo $OUTPUT->heading($strheading);
            $message = get_string('deletecertpageconfirm', 'customcert');
            echo $OUTPUT->confirm($message, $yesurl, $nourl);
            echo $OUTPUT->footer();
            exit();
        }
    }

    // If they chose to add another page, enter it into database.
    if (!empty($data->addcertpage)) {
        customcert_add_page($data);
    }

    // Redirect to the editing page to show form with recent updates.
    redirect($CFG->wwwroot . '/mod/customcert/edit.php?cmid=' . $cm->id);
}

$PAGE->set_title(get_string('editcustomcert', 'customcert', format_string($customcert->name)));
$PAGE->set_heading($course->fullname);
$PAGE->set_url('/mod/customcert/edit.php', array('cmid' => $cmid));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editcustomcert', 'mod_customcert'));
$mform->display();
echo $OUTPUT->footer();
