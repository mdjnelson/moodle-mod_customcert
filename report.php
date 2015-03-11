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
 * Handles viewing a report that shows who has received a customcert.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/customcert/locallib.php');

$id   = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', CUSTOMCERT_PER_PAGE, PARAM_INT);
$pageurl = $url = new moodle_url('/mod/customcert/report.php', array('id' => $id, 'page' => $page, 'perpage' => $perpage));

// Ensure the perpage variable does not exceed the max allowed if
// the user has not specified they wish to view all customcerts.
if (CUSTOMCERT_PER_PAGE !== 0) {
    if (($perpage > CUSTOMCERT_MAX_PER_PAGE) || ($perpage === 0)) {
        $perpage = CUSTOMCERT_PER_PAGE;
    }
}

$cm = get_coursemodule_from_id('customcert', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$customcert = $DB->get_record('customcert', array('id' => $cm->instance), '*', MUST_EXIST);

// Requires a course login.
require_course_login($course->id, false, $cm);

// Check capabilities.
$context = context_module::instance($cm->id);
require_capability('mod/customcert:manage', $context);

// Get the users who have been issued.
$users = customcert_get_issues($customcert->id, groups_get_activity_groupmode($cm), $cm, $page, $perpage);

if ($download) {
    customcert_generate_report_file($customcert, $users, $download);
    exit;
}

// Create the table for the users.
$table = new html_table();
$table->attributes['class'] = 'generaltable centre';
$table->attributes['style'] = 'width: 95%;';
$table->head  = array(get_string('awardedto', 'customcert'), get_string('receiveddate', 'customcert'), get_string('code', 'customcert'));
$table->align = array('left', 'left', 'center');
foreach ($users as $user) {
    $name = $OUTPUT->user_picture($user) . fullname($user);
    $date = userdate($user->timecreated);
    $code = $user->code;
    $table->data[] = array($name, $date, $code);
}

// Create table to store buttons.
$tablebutton = new html_table();
$tablebutton->attributes['class'] = 'centre';
$btndownloadods = $OUTPUT->single_button(new moodle_url('report.php', array('id' => $cm->id, 'download' => 'ods')), get_string("downloadods"));
$btndownloadxls = $OUTPUT->single_button(new moodle_url('report.php', array('id' => $cm->id, 'download' => 'xls')), get_string("downloadexcel"));
$tablebutton->data[] = array($btndownloadods, $btndownloadxls);

$PAGE->set_url($pageurl);
$PAGE->navbar->add(get_string('report', 'customcert'));
$PAGE->set_title(format_string($customcert->name) . ": " . get_string('report', 'customcert'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'customcert'));
groups_print_activity_menu($cm, $url);
// If perpage is not set to 0 (displaying all issues), we may need a paging bar.
if ($perpage !== 0) {
    echo $OUTPUT->paging_bar(count((array) $users), $page, $perpage, $url);
}
echo '<br />';
echo html_writer::table($table);
echo html_writer::tag('div', html_writer::table($tablebutton), array('style' => 'margin:auto; width:50%'));
echo $OUTPUT->footer($course);
