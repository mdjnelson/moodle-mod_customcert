<?php

require_once(__DIR__ . '/../../../config.php');

use core\di;
use core\notification;
use mod_customcert\export\contracts\i_backup_logger;
use mod_customcert\export\contracts\i_backup_manager;
use mod_customcert\export\contracts\import_form;

require_login();

$contextid = required_param('context_id', PARAM_INT);

$context = context::instance_by_id($contextid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/customcert/pages/import.php'));
$PAGE->set_title(get_string('import'));
$PAGE->set_heading(get_string('import'));

$mform = new import_form();

if ($mform->is_cancelled()) {

} else if ($fromform = $mform->get_data()) {
    $zipstring = $mform->get_file_content('backup');
    $tempdir = make_temp_directory('customcert_import/' . uniqid(more_entropy: true));
    $zippath = "$tempdir/import.zip";
    file_put_contents($zippath, $zipstring);

    $backupmng = di::get(i_backup_manager::class);
    $backupmng->import($fromform->context_id, $tempdir);

    di::get(i_backup_logger::class)->print_notification();
    notification::success("Successfully imported template");
}

echo $OUTPUT->header();

// Formular anzeigen.
$mform->display();

echo $OUTPUT->footer();
