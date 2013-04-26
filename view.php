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
 * Handles viewing a customcert.
 *
 * @package    mod_customcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->dirroot . '/mod/customcert/includes/tcpdf_colors.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('customcert', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$customcert = $DB->get_record('customcert', array('id'=> $cm->instance), '*', MUST_EXIST);

// Ensure the user is allowed to view this page.
require_login($course, true, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/customcert:view', $context);

// Initialise $PAGE.
$PAGE->set_url('/mod/customcert/view.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_title(format_string($customcert->name));
$PAGE->set_heading(format_string($course->fullname));

// Check that no action was passed, if so that means we are not outputting to PDF.
if (empty($action)) {
    // Get the current groups mode.
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/customcert/view.php?id=' . $cm->id);
    $currentgroup = groups_get_activity_group($cm);
    $groupmode = groups_get_activity_groupmode($cm);

    // Generate the link to the report if there are issues to display.
    $reportlink = '';
    if (has_capability('mod/customcert:manage', $context)) {
        // Get the total number of issues.
        $numissues = customcert_get_number_of_issues($customcert->id, $cm, $groupmode);
        // If the number of issues is greater than 0 display a link to the report.
        if ($numissues > 0) {
            $url = html_writer::tag('a', get_string('viewcustomcertissues', 'customcert', $numissues),
                array('href' => $CFG->wwwroot . '/mod/customcert/report.php?id=' . $cm->id));
            $reportlink = html_writer::tag('div', $url, array('class' => 'reportlink'));
        }
    }

    // Generate the intro content if it exists.
    $intro = '';
    if (!empty($customcert->intro)) {
        $intro = $OUTPUT->box(format_module_intro('customcert', $customcert, $cm->id), 'generalbox', 'intro');
    }

    // If the current user has been issued a customcert generate HTML to display the details.
    $issuelist = '';
    if ($issues = $DB->get_records('customcert_issues', array('userid' => $USER->id, 'customcertid' => $customcert->id))) {
        $header = $OUTPUT->heading(get_string('summaryofissue', 'customcert'));

        $table = new html_table();
        $table->class = 'generaltable';
        $table->head = array(get_string('issued', 'customcert'));
        $table->align = array('left');
        $table->attributes = array('style' => 'width:20%; margin:auto');

        foreach ($issues as $issue) {
            $row = array();
            $row[] = userdate($issue->timecreated);
            $table->data[$issue->id] = $row;
        }

        $issuelist = $header . html_writer::table($table) . "<br />";
    }

    // Create the button to download the customcert.
    $linkname = get_string('getcustomcert', 'customcert');
    $link = new moodle_url('/mod/customcert/view.php', array('id' => $cm->id, 'action' => 'download'));
    $downloadbutton = new single_button($link, $linkname);
    $downloadbutton->add_action(new popup_action('click', $link, 'customcertpopup', array('height' => 600, 'width' => 800)));
    $downloadbutton = html_writer::tag('div', $OUTPUT->render($downloadbutton), array('style' => 'text-align:center'));

    // Output all the page data.
    echo $OUTPUT->header();
    echo $reportlink;
    echo $intro;
    echo $issuelist;
    echo $downloadbutton;
    echo $OUTPUT->footer($course);
    exit;
} else { // Output to pdf
    // Create new customcert issue record if one does not already exist.
    if (!$DB->record_exists('customcert_issues', array('userid' => $USER->id, 'customcertid' => $customcert->id))) {
        $customcertissue = new stdClass();
        $customcertissue->customcertid = $customcert->id;
        $customcertissue->userid = $USER->id;
        $customcertissue->code = customcert_generate_code();
        $customcertissue->timecreated =  time();
        // Insert the record into the database.
        $DB->insert_record('customcert_issues', $customcertissue);
    }
    // Now we want to generate the PDF.
    customcert_generate_pdf($customcert, $USER->id);
}
