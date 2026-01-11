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
 * Handles loading a customcert template.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_customcert\page_helper;
use mod_customcert\service\template_load_service;
use mod_customcert\template;

require_once('../../config.php');

$tid = required_param('tid', PARAM_INT);
$ltid = required_param('ltid', PARAM_INT); // The template to load.
$confirm = optional_param('confirm', 0, PARAM_INT);

$template = template::load((int)$tid);
$loadtemplate = template::load((int)$ltid);

if ($cm = $template->get_cm()) {
    require_login($cm->course, false, $cm);
} else {
    require_login();
}
$template->require_manage();

if ($template->get_context()->contextlevel == CONTEXT_MODULE) {
    $customcert = $DB->get_record('customcert', ['id' => $cm->instance], '*', MUST_EXIST);
    $title = $customcert->name;
} else {
    $title = $SITE->fullname;
}

// Check that they have confirmed they wish to load the template.
if ($confirm && confirm_sesskey()) {
    $service = new template_load_service();
    $service->replace($template->get_id(), $loadtemplate->get_id());

    // Redirect.
    $url = new moodle_url('/mod/customcert/edit.php', ['tid' => $tid]);
    redirect($url);
}

// Create the link options.
$nourl = new moodle_url('/mod/customcert/edit.php', ['tid' => $tid]);
$yesurl = new moodle_url('/mod/customcert/load_template.php', ['tid' => $tid,
                                                                    'ltid' => $ltid,
                                                                    'confirm' => 1,
                                                                    'sesskey' => sesskey()]);

$pageurl = new moodle_url('/mod/customcert/load_template.php', ['tid' => $tid, 'ltid' => $ltid]);
page_helper::page_setup($pageurl, $template->get_context(), $title);
$PAGE->activityheader->set_attrs(['hidecompletion' => true,
            'description' => '']);

$str = get_string('editcustomcert', 'customcert');
$link = new moodle_url('/mod/customcert/edit.php', ['tid' => $template->get_id()]);
$PAGE->navbar->add($str, new \action_link($link, $str));
$PAGE->navbar->add(get_string('loadtemplate', 'customcert'));

// Show a confirmation page.
echo $OUTPUT->header();
echo $OUTPUT->confirm(get_string('loadtemplatemsg', 'customcert'), $yesurl, $nourl);
echo $OUTPUT->footer();
