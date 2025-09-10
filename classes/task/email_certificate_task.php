<?php
// This file is part of Moodle - http://moodle.org/
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
 * An adhoc task for emailing certificates.
 *
 * @package    mod_customcert
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_customcert\task;

use mod_customcert\helper;

/**
 * An adhoc task for emailing certificates per issue.
 *
 * @package    mod_customcert
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_certificate_task extends \core\task\adhoc_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskemailcertificate', 'customcert');
    }

    /**
     * Execute.
     */
    public function execute() {
        global $CFG, $DB;
        
        // Force error_log output to a dedicated debug file for all executions.

        require_once(__DIR__ . '/../../lib.php');

        $customdata = $this->get_custom_data();
        if (empty($customdata) || empty($customdata->issueid) || empty($customdata->customcertid)) {
            return;
        }

        $issueid = (int)$customdata->issueid;
        $customcertid = (int)$customdata->customcertid;
        
        // Get certificate and course information
        $sql = "SELECT c.*, ct.id as templateid, ct.name as templatename, ct.contextid, co.id as courseid,
                       co.fullname as coursefullname, co.shortname as courseshortname, co.lang as courselang
                  FROM {customcert} c
                  JOIN {customcert_templates} ct ON c.templateid = ct.id
                  JOIN {course} co ON c.course = co.id
                 WHERE c.id = :id";

        $customcert = $DB->get_record_sql($sql, ['id' => $customcertid]);
        if (!$customcert) {
            return;
        }

        // Get user and issue information
        $userfields = helper::get_all_user_name_fields('u');
        $sql = "SELECT u.id, u.username, $userfields, u.email, u.mailformat, ci.id as issueid, ci.emailed, u.lang as lang
                  FROM {customcert_issues} ci
                  JOIN {user} u ON ci.userid = u.id
                 WHERE ci.customcertid = :customcertid AND ci.id = :issueid";
        $user = $DB->get_record_sql($sql, ['customcertid' => $customcertid, 'issueid' => $issueid]);
        
        if (!$user) {
            return;
        }

        // Store original language to restore later
        $originallang = current_language();
        
        // --- LANGUAGE SELECTION LOGIC (same hierarchy as certificate view) ---
        $lang = $this->resolve_certificate_language($customcert, $user);

        // Force the resolved language
        $activelangs = get_string_manager()->get_list_of_translations();
        if (!empty($lang) && array_key_exists($lang, $activelangs)) {
            force_current_language($lang);
            get_string_manager()->reset_caches();
            
            // Test string fetch after forcing language
            $teststring = get_string('emailstudentsubject', 'customcert');
        }

        // Get the context
        $context = \context::instance_by_id($customcert->contextid);

        // Setup the user for the cron
        \core\cron::setup_user($user);

        // Create a directory to store the PDF
        $tempdir = make_temp_directory('certificate/attachment');
        if (!$tempdir) {
            // Restore original language before returning
            force_current_language($originallang);
            return;
        }

        // Generate PDF with the forced language
        $template = new \stdClass();
        $template->id = $customcert->templateid;
        $template->name = $customcert->templatename;
        $template->contextid = $customcert->contextid;
        $template = new \mod_customcert\template($template);
        $filecontents = $template->generate_pdf(false, $user->id, true);

        // Prepare file information
        $courseshortname = format_string($customcert->courseshortname, true, ['context' => $context]);
        $coursefullname = format_string($customcert->coursefullname, true, ['context' => $context]);
        $certificatename = format_string($customcert->name, true, ['context' => $context]);
        $userfullname = fullname($user);

        // Set the name of the file we are going to send
        $filename = $courseshortname . '_' . $certificatename;
        $filename = \core_text::entities_to_utf8($filename);
        $filename = strip_tags($filename);
        $filename = rtrim($filename, '.');
        $filename = str_replace('&', '_', $filename) . '.pdf';

        // Create the file we will be sending
        $tempfile = $tempdir . '/' . md5(microtime() . $user->id) . '.pdf';
        file_put_contents($tempfile, $filecontents);

        // Prepare email information object
        $info = new \stdClass();
        $info->coursename = $courseshortname; // Added for BC
        $info->courseshortname = $courseshortname;
        $info->coursefullname = $coursefullname;
        $info->certificatename = $certificatename;
        $info->userfullname = $userfullname;

        // Get email renderers
        $page = new \moodle_page();
        $htmlrenderer = $page->get_renderer('mod_customcert', 'email', 'htmlemail');
        $textrenderer = $page->get_renderer('mod_customcert', 'email', 'textemail');
        
        // Get the person we are going to send this email on behalf of
        $userfrom = \core_user::get_noreply_user();

        // Send email to students
        if ($customcert->emailstudents) {
            $this->send_email_to_student($user, $userfrom, $info, $context, $htmlrenderer, 
                $textrenderer, $tempfile, $filename, $userfullname, $courseshortname, 
                $coursefullname, $certificatename);
        }

        // Send email to teachers
        if ($customcert->emailteachers) {
            $this->send_email_to_teachers($context, $userfrom, $info, $htmlrenderer, 
                $textrenderer, $tempfile, $filename, $userfullname, $courseshortname, 
                $coursefullname, $certificatename);
        }

        // Send email to others
        if (!empty($customcert->emailothers)) {
            $this->send_email_to_others($customcert->emailothers, $userfrom, $info, 
                $context, $htmlrenderer, $textrenderer, $tempfile, $filename, 
                $userfullname, $courseshortname, $coursefullname, $certificatename);
        }

        // Mark as emailed
        $DB->set_field('customcert_issues', 'emailed', 1, ['id' => $issueid]);
        

        // Restore original language
        force_current_language($originallang);
        get_string_manager()->reset_caches();
    }

    /**
     * Resolve the certificate language using the same hierarchy as certificate view
     * 
     * @param object $customcert The certificate record
     * @param object $user The user record
     * @return string The resolved language code
     */
    private function resolve_certificate_language($customcert, $user) {
        global $CFG;
        
        $lang = null;

        // 1. Certificate-specific language (if set)
        if (!empty($customcert->force_language)) {
            $lang = $customcert->force_language;
            return $lang;
        }

        // 2. Course language (if set)
        if (!empty($customcert->courselang)) {
            $lang = $customcert->courselang;
            return $lang;
        }

        // 3. User profile language (if set)
        if (!empty($user->lang)) {
            $lang = $user->lang;
            return $lang;
        }

        // 4. Site default language
        $lang = $CFG->lang;
        
        return $lang;
    }

    /**
     * Send email to student
     */
    private function send_email_to_student($user, $userfrom, $info, $context, $htmlrenderer, 
            $textrenderer, $tempfile, $filename, $userfullname, $courseshortname, 
            $coursefullname, $certificatename) {
        
        $renderable = new \mod_customcert\output\email_certificate(true, $userfullname, 
            $courseshortname, $coursefullname, $certificatename, $context->instanceid);

        $subject = get_string('emailstudentsubject', 'customcert', $info);
        $message = $textrenderer->render($renderable);
        $messagehtml = $htmlrenderer->render($renderable);
        
        // Apply multilang filter to all text content
        $subject = format_text($subject, FORMAT_HTML, ['filter' => true, 'context' => $context]);
        $message = format_text($message, FORMAT_HTML, ['filter' => true, 'context' => $context]);
        $messagehtml = format_text($messagehtml, FORMAT_HTML, ['filter' => true, 'context' => $context]);
        
        
        email_to_user($user, $userfrom, html_entity_decode($subject, ENT_COMPAT), 
            $message, $messagehtml, $tempfile, $filename);
    }

    /**
     * Send email to teachers
     */
    private function send_email_to_teachers($context, $userfrom, $info, $htmlrenderer, 
            $textrenderer, $tempfile, $filename, $userfullname, $courseshortname, 
            $coursefullname, $certificatename) {
        
        $teachers = get_enrolled_users($context, 'moodle/course:update');

        $renderable = new \mod_customcert\output\email_certificate(false, $userfullname, 
            $courseshortname, $coursefullname, $certificatename, $context->instanceid);

        $subject = get_string('emailnonstudentsubject', 'customcert', $info);
        $message = $textrenderer->render($renderable);
        $messagehtml = $htmlrenderer->render($renderable);
        
        // Apply multilang filter
        $subject = format_text($subject, FORMAT_HTML, ['filter' => true, 'context' => $context]);
        $message = format_text($message, FORMAT_HTML, ['filter' => true, 'context' => $context]);
        $messagehtml = format_text($messagehtml, FORMAT_HTML, ['filter' => true, 'context' => $context]);
        
        foreach ($teachers as $teacher) {
            email_to_user($teacher, $userfrom, html_entity_decode($subject, ENT_COMPAT),
                $message, $messagehtml, $tempfile, $filename);
        }
    }

    /**
     * Send email to other recipients
     */
    private function send_email_to_others($emailothers, $userfrom, $info, $context, 
            $htmlrenderer, $textrenderer, $tempfile, $filename, $userfullname, 
            $courseshortname, $coursefullname, $certificatename) {
        
        $others = explode(',', $emailothers);
        
        foreach ($others as $email) {
            $email = trim($email);
            if (validate_email($email)) {
                $renderable = new \mod_customcert\output\email_certificate(false, $userfullname,
                    $courseshortname, $coursefullname, $certificatename, $context->instanceid);

                $subject = get_string('emailnonstudentsubject', 'customcert', $info);
                $message = $textrenderer->render($renderable);
                $messagehtml = $htmlrenderer->render($renderable);
                
                // Apply multilang filter
                $subject = format_text($subject, FORMAT_HTML, ['filter' => true, 'context' => $context]);
                $message = format_text($message, FORMAT_HTML, ['filter' => true, 'context' => $context]);
                $messagehtml = format_text($messagehtml, FORMAT_HTML, ['filter' => true, 'context' => $context]);

                $emailuser = new \stdClass();
                $emailuser->id = -1;
                $emailuser->email = $email;
                
                email_to_user($emailuser, $userfrom, html_entity_decode($subject, ENT_COMPAT), 
                    $message, $messagehtml, $tempfile, $filename);
            }
        }
    }
}