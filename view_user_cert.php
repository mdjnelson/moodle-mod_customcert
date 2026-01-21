<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
// Include required Moodle configuration and custom certificate library.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');

// Allows guest access to course with ID 1.
require_login(1, false);

// Set up the page context before processing any parameters.
// This ensures that Moodle properly initializes the page and handles any errors gracefully.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/mod/customcert/view_user_cert.php');
$PAGE->set_title('View certificate');
$PAGE->set_heading('View certificate');

/**
 * Displays an error message in a formatted Moodle page and exits.
 *
 * This function helps standardize error handling by rendering the page
 * properly and showing the error message in an alert box.
 *
 * Code fragment to define the version of the customcert module
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @param string $message The error message to display.
 */
function display_error_page($message) {
    global $OUTPUT;

    echo $OUTPUT->header(); // Display the page header.
    echo $OUTPUT->box($message, 'alert alert-danger'); // Display the error message in a styled box.
    echo $OUTPUT->footer(); // Display the page footer.
    exit; // Stop further execution.
}

// Retrieve certificate code and verification token from URL parameters.
// 'optional_param' is used instead of 'required_param' to avoid Moodle throwing an automatic error page.
$certcode = optional_param('cert_code', '', PARAM_ALPHANUMEXT);
$token = optional_param('token', '', PARAM_ALPHANUMEXT);

// Ensure both required parameters are provided.
if (empty($certcode) || empty($token)) {
    display_error_page('Certificate code or verification token is missing. Please check the URL and try again.');
}

// Validate the provided token by regenerating it using the expected algorithm.
$expectedtoken = calculate_signature($certcode);
if ($token !== $expectedtoken) {
    display_error_page('The verification token is invalid for this certificate. Please check the URL and try again.');
}

// Retrieve the certificate issue entry
// using the provided certificate code.
// This helps fetch the associated user ID to verify ownership.
$issue = $DB->get_record('customcert_issues', ['code' => $certcode], '*');

if (!$issue) {
    display_error_page('Certificate with this code not found. '
        . 'Please check the code and try again.');
}

// Fetch the certificate associated with the retrieved issue.
$certificate = $DB->get_record('customcert', ['id' => $issue->customcertid]);
if (!$certificate) {
    display_error_page('The certificate does not exist. Please contact the site administrator for assistance.');
}

// Retrieve the corresponding template for the fetched certificate.
// The template defines the layout and content of the generated certificate.
$template = $DB->get_record('customcert_templates', ['id' => $certificate->templateid]);
if (!$template) {
    display_error_page('The certificate template could not be found. Please contact the site administrator for assistance.');
}

try {
    // Convert the template record into a template object.
    // This object provides methods to generate and render the certificate.
    $template = new \mod_customcert\template($template);

    // Generate and output the certificate PDF.
    // 'false' indicates that the PDF is displayed inline,
    // instead of being force-downloaded.
    // The second parameter ensures the certificate is generated for the correct user.
    $template->generate_pdf(false, $issue->userid);
} catch (Exception $e) {
    // Catch any errors that may occur while generating the certificate PDF.
    display_error_page('Error generating certificate PDF. '
        . 'Try again later or contact support.');
}

// Prevent further execution after rendering the certificate.
exit;
