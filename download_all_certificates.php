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
 * Handles downloading all certificates on the site.
 *
 * @package    mod_customcert
 * @copyright  2024 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\task\manager;
use mod_customcert\task\generate_site_certificates_download_task;

require_once('../../config.php');

require_login();

$confirm = optional_param('confirm', 0, PARAM_BOOL);

$context = context_system::instance();
require_capability('mod/customcert:viewallcertificates', $context);

$PAGE->set_url('/mod/customcert/download_all_certificates.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('downloadallsitecertificates', 'customcert'));
$PAGE->set_heading($SITE->fullname);

$returnurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingcustomcert']);

if ($confirm && confirm_sesskey()) {
    $task = new generate_site_certificates_download_task();
    $task->set_userid($USER->id);
    // Avoid queueing duplicate tasks if the user submits the request more than once before it runs.
    manager::queue_adhoc_task($task, true);

    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('downloadallsitecertificatesqueued', 'customcert'), 'success');
    echo $OUTPUT->continue_button($returnurl);
    echo $OUTPUT->footer();
    exit;
}

$confirmurl = new moodle_url('/mod/customcert/download_all_certificates.php', [
    'confirm' => 1,
    'sesskey' => sesskey(),
]);

echo $OUTPUT->header();
echo $OUTPUT->confirm(get_string('downloadallsitecertificatesconfirm', 'customcert'), $confirmurl, $returnurl);
echo $OUTPUT->footer();
