<?php
// Include required Moodle configuration and custom certificate library.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');

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
$cert_code = optional_param('cert_code', '', PARAM_ALPHANUMEXT);
$token = optional_param('token', '', PARAM_ALPHANUMEXT);

// Ensure both required parameters are provided.
if (empty($cert_code) || empty($token)) {
    display_error_page('Certificate code or verification token is missing. Please check the URL and try again.');
}

// Validate the provided token by regenerating it using the expected algorithm.
$expected_token = calculate_signature($cert_code);
if ($token !== $expected_token) {
    display_error_page('The verification token is invalid for this certificate. Please check the URL and try again.');
}

// Retrieve the certificate issue entry using the provided certificate code.
// This helps fetch the associated user ID to verify ownership.
$issue = $DB->get_record('customcert_issues', ['code' => $cert_code], '*');

if (!$issue) {
    display_error_page('The certificate with the provided code could not be found. Please verify the certificate code and try again.');
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
    // 'false' indicates that the PDF is displayed inline instead of being force-downloaded.
    // The second parameter ensures the certificate is generated for the correct user.
    $template->generate_pdf(false, $issue->userid);
} catch (Exception $e) {
    // Catch any errors that may occur while generating the certificate PDF.
    display_error_page('There was an error generating the certificate PDF. Please try again later or contact support if the problem persists.');
}

// Prevent further execution after rendering the certificate.
exit;
