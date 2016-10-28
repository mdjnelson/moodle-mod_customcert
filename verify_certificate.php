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
 * Handles verifying the code for a certificate.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// The code for the certificate we are verifying.
$code = optional_param('code', '', PARAM_ALPHANUM);

// Need to be logged in.
require_login();

// Ok, now check the user has the ability to verify certificates.
require_capability('mod/customcert:verifycertificates', context_system::instance());

// Set up the page.
$pageurl = new moodle_url('/mod/customcert/manage_templates.php');
\mod_customcert\page_helper::page_setup($pageurl, context_system::instance(), get_string('verifycertificate', 'customcert'));

// The form we are using to verify these codes.
$form = new \mod_customcert\verify_certificate_form();

if ($form->get_data()) {
    // Ok, now check if the code is valid.
    $sql = "SELECT *
              FROM {customcert} c
              JOIN {customcert_issues} ci
                ON c.id = ci.customcertid
              JOIN {user} u
                ON ci.userid = u.id
             WHERE ci.code = :code
               AND u.deleted = 0";
    if ($DB->get_record('customcert_issues', array('code' => $code))) {
        // Ok, let's show that this user is verified.
    } else {
        // Can't find it, let's say it's not verified.
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('verifycertificate', 'customcert'));
echo $form->display();
echo $OUTPUT->footer();
