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
 * Imports a custom certificate template from an uploaded ZIP file.
 *
 * This script presents a form to upload a template backup, handles the upload,
 * processes the import using the configured file manager, and displays status
 * notifications. Requires a valid context ID.
 *
 * @package    mod_customcert
 * @copyright  2025, oncampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use core\di;
use core\notification;
use mod_customcert\export\contracts\i_template_import_logger;
use mod_customcert\export\contracts\i_template_file_manager;
use mod_customcert\export\contracts\import_form;

require_login();

$contextid = required_param('context_id', PARAM_INT);

$context = context::instance_by_id($contextid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/customcert/pages/import.php'));
$PAGE->set_title(get_string('import'));
$PAGE->set_heading(get_string('import'));

$mform = new import_form();

if ($fromform = $mform->get_data()) {
    $zipstring = $mform->get_file_content('backup');
    $tempdir = make_temp_directory('customcert_import/' . uniqid(more_entropy: true));
    $zippath = "$tempdir/import.zip";
    file_put_contents($zippath, $zipstring);

    $backupmng = di::get(i_template_file_manager::class);
    $backupmng->import($fromform->context_id, $tempdir);

    di::get(i_template_import_logger::class)->print_notification();
    notification::success("Successfully imported template");
}

echo $OUTPUT->header();

// Formular anzeigen.
$mform->display();

echo $OUTPUT->footer();
