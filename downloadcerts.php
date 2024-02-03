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
 * Handles zip and download of certificates.
 *
 * Derived from the local_bulkcustomcert by Gonzalo Romero.
 *
 * @package    mod_customcert
 * @author     Gonzalo Romero
 * @author     Giorgio Consorti <g.consorti@lynxlab.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', null, PARAM_INT);
$customcertid = optional_param('customcertid', null, PARAM_INT);

if (!has_capability('mod/customcert:viewallcertificates', context_system::instance()) && !$courseid && !$customcert) {
    die();
}

/** @var \moodle_database $DB */
global $DB;

// Increase the server timeout to handle the creation and sending of large zip files.
core_php_time_limit::raise();

if ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid]);
    $certs = $DB->get_records('customcert', ['course' => $courseid]);
} else if ($customcertid) {
    $cert = $DB->get_record('customcert', ['id' => $customcertid], '*', MUST_EXIST);
    $certs[$cert->id] = $cert;
    $course = $DB->get_record('course', ['id' => $certs[$cert->id]->course], '*', MUST_EXIST);
    $courseid = $course->id;
    unset($cert);
}

// Build a list of files to zip.
$filesforzipping = [];

foreach ($certs as $certid => $cert_fields) {
    $issues = $DB->get_records('customcert_issues', ['customcertid' => $certid]);
    list($userssql, $params) = $DB->get_in_or_equal(array_map(fn($i) => $i->userid, $issues), SQL_PARAMS_NAMED);
    $usersObjs = $DB->get_records_select('user', "id {$userssql}", $params);
    $template = $DB->get_record('customcert_templates', ['id' => $cert_fields->templateid], '*', MUST_EXIST);
    $template = new \mod_customcert\template($template);
    $lf = new \mod_customcert\localfile($template);
    foreach ($issues as $issue) {
        if (false === $file = $lf->getPDF($issue->userid)) {
            // must generate the pdf
            $pdf = $template->generate_pdf(false, $issue->userid, true);
            if (!empty($pdf)) {
                if ($cert_fields->keeplocalcopy) {
                    $file = $lf->getPDF($issue->userid);
                } else {
                    $file = [
                        'content' => $pdf,
                    ];
                }
            }
        }
        if ($file) {
            $filename = \mod_customcert\localfile::buildFileName($usersObjs[$issue->userid]->username, $template->get_id(), $course->shortname);
            $filesforzipping['/' . $course->shortname . '/' . $cert_fields->name . '/' . $filename] = $file;
        }
    }
}

if (count($filesforzipping) == 0) {
    // This should never happen. The option only show up if there is available certs.
    $url = new moodle_url('/course/view.php?id=' . $courseid);
    redirect($url);
} else if ($zipfile = pack_files($filesforzipping)) {
    send_temp_file($zipfile, get_string('modulenameplural', 'customcert') . '-' . $course->shortname . '.zip');
}
die();


function pack_files($filesforzipping)
{
    global $CFG;
    // Create path for new zip file.
    $tempzip = tempnam($CFG->tempdir . '/', 'customcert_');
    // Zip files.
    $zipper = new zip_packer();
    if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
        return $tempzip;
    }
    return false;
}
