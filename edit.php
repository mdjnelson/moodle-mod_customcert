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
 * Edit the customcert settings.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/customcert/locallib.php');
require_once($CFG->dirroot . '/mod/customcert/edit_form.php');
require_once($CFG->dirroot . '/mod/customcert/load_template_form.php');
require_once($CFG->dirroot . '/mod/customcert/save_template_form.php');
require_once($CFG->dirroot . '/mod/customcert/element/element.class.php');

$cmid = required_param('cmid', PARAM_INT);
$moveup = optional_param('moveup', 0, PARAM_INT);
$movedown = optional_param('movedown', 0, PARAM_INT);
$emoveup = optional_param('emoveup', 0, PARAM_INT);
$emovedown = optional_param('emovedown', 0, PARAM_INT);
$deleteelement = optional_param('deleteelement', 0, PARAM_INT);
$deletepage = optional_param('deletepage', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$cm = get_coursemodule_from_id('customcert', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$customcert = $DB->get_record('customcert', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/customcert:manage', $context);

// The form for loading a customcert templates.
$templates = customcert_get_templates();
$loadtemplateform = new mod_customcert_load_template_form('', array('cmid' => $cm->id, 'templates' => $templates));
// The form for saving the current information as a template.
$savetemplateform = new mod_customcert_save_template_form('', array('cmid' => $cm->id));

// Check if they chose to load a customcert template and redirect.
if ($data = $loadtemplateform->get_data()) {
    $url = new moodle_url('/mod/customcert/load_template.php', array('cmid' => $cmid, 'tid' => $data->template));
    redirect($url);
}

// Check if they chose to save the current information and redirect.
if ($data = $savetemplateform->get_data()) {
    $url = new moodle_url('/mod/customcert/save_template.php', array('cmid' => $cmid, 'name' => $data->name));
    redirect($url);
}

// Flag to determine if we are deleting anything.
$deleting = false;

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
} else if ((!empty($emoveup)) || (!empty($emovedown))) { // Check if we are moving a custom certificate element.
    // Check if we are moving an element up.
    if (!empty($emoveup)) {
        if ($movecertelement = $DB->get_record('customcert_elements', array('id' => $emoveup))) {
            $swapcertelement = $DB->get_record('customcert_elements', array('sequence' => $movecertelement->sequence - 1));
        }
    } else { // Must be moving a element down.
        if ($movecertelement = $DB->get_record('customcert_elements', array('id' => $emovedown))) {
            $swapcertelement = $DB->get_record('customcert_elements', array('sequence' => $movecertelement->sequence + 1));
        }
    }
    // Check that there is an element to move, and an element to swap it with.
    if ($swapcertelement && $movecertelement) {
        $DB->set_field('customcert_elements', 'sequence', $swapcertelement->sequence, array('id' => $movecertelement->id));
        $DB->set_field('customcert_elements', 'sequence', $movecertelement->sequence, array('id' => $swapcertelement->id));
    }
} else if (!empty($deletepage)) { // Check if we are deleting a page.
    if (!empty($confirm)) { // Check they have confirmed the deletion.
        customcert_delete_page($deletepage);
    } else {
        // Set deletion flag to true.
        $deleting = true;
        // Create the message.
        $message = get_string('deletepageconfirm', 'customcert');
        // Create the link options.
        $nourl = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cm->id));
        $yesurl = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cm->id,
            'deletepage' => $deletepage,
            'confirm' => 1,
            'sesskey' => sesskey()));
    }
} else if (!empty($deleteelement)) { // Check if we are deleting an element.
    if (!empty($confirm)) { // Check they have confirmed the deletion.
        // Ensure element exists and delete it.
        $element = $DB->get_record('customcert_elements', array('id' => $deleteelement), '*', MUST_EXIST);
        // Get an instance of the element class.
        if ($e = customcert_get_element_instance($element)) {
            $e->delete_element();
        }
    } else {
        // Set deletion flag to true.
        $deleting = true;
        // Create the message.
        $message = get_string('deleteelementconfirm', 'customcert');
        // Create the link options.
        $nourl = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cm->id));
        $yesurl = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cm->id,
            'deleteelement' => $deleteelement,
            'confirm' => 1,
            'sesskey' => sesskey()));
    }
}

// Check if we are deleting either a page or an element.
if ($deleting) {
    // Show a confirmation page.
    $strheading = get_string('deleteconfirm', 'customcert');
    $PAGE->navbar->add($strheading);
    $PAGE->set_title($strheading);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_url('/mod/customcert/edit.php', array('cmid' => $cmid));
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strheading);
    echo $OUTPUT->confirm($message, $yesurl, $nourl);
    echo $OUTPUT->footer();
    exit();
}

$mform = new mod_customcert_edit_form('', array('customcertid' => $customcert->id,
                                                'cmid' => $cm->id,
                                                'course' => $course));

if ($data = $mform->get_data()) {
    // Handle file uploads.
    customcert_upload_imagefiles($data->customcertimage, context_course::instance($course->id)->id);

    // Save any page data.
    customcert_save_page_data($data);

    // Check if we are adding a page.
    if (!empty($data->addcertpage)) {
        customcert_add_page($data);
    }

    // Loop through the data.
    foreach ($data as $key => $value) {
        // Check if they wanted to download the grid PDF.
        if (strpos($key, 'downloadgrid_') !== false) {
            // Get the page id.
            $pageid = str_replace('downloadgrid_', '', $key);
            customcert_generate_grid_pdf($pageid);
        } else if (strpos($key, 'addelement_') !== false) { // Check if they chose to add an element to a page.
            // Get the page id.
            $pageid = str_replace('addelement_', '', $key);
            // Get the element.
            $element = "element_" . $pageid;
            $element = $data->$element;
            // Create the URL to redirect to to add this element.
            $params = array();
            $params['cmid'] = $cmid;
            $params['action'] = 'add';
            $params['element'] = $element;
            $params['pageid'] = $pageid;
            $url = new moodle_url('/mod/customcert/edit_element.php', $params);
            redirect($url);
        }
    }

    // Check if we want to preview this custom certificate.
    if (!empty($data->previewbtn)) {
        customcert_generate_pdf($customcert, true);
    }

    // Redirect to the editing page to show form with recent updates.
    $url = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cmid));
    redirect($url);
}

$PAGE->set_title(get_string('editcustomcert', 'customcert', format_string($customcert->name)));
$PAGE->set_heading($course->fullname);
$PAGE->set_url('/mod/customcert/edit.php', array('cmid' => $cmid));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editcustomcert', 'customcert'));
$mform->display();
if (!empty($templates)) {
    $loadtemplateform->display();
}
$savetemplateform->display();
echo $OUTPUT->footer();
