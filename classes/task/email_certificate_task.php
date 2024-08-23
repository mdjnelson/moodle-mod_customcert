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
 * A scheduled task for emailing certificates.
 *
 * @package    mod_customcert
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_customcert\task;

use mod_customcert\helper;

/**
 * A scheduled task for emailing certificates.
 *
 * @package    mod_customcert
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_certificate_task extends \core\task\scheduled_task {

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
        global $DB;

        // Get the certificatesperrun, includeinnotvisiblecourses, and certificateexecutionperiod configurations.
        $certificatesperrun = (int)get_config('customcert', 'certificatesperrun');
        $includeinnotvisiblecourses = (bool)get_config('customcert', 'includeinnotvisiblecourses');
        $certificateexecutionperiod = (int)get_config('customcert', 'certificateexecutionperiod');

        // Get the last processed batch and total certificates to process.
        $taskprogress = $DB->get_record('customcert_email_task_prgrs', ['taskname' => 'email_certificate_task']);
        $lastprocessed = $taskprogress->last_processed;

        // Get all the certificates that have requested someone get emailed.
        $emailotherslengthsql = $DB->sql_length('c.emailothers');
        $sql = "SELECT c.*, ct.id as templateid, ct.name as templatename, ct.contextid, co.id as courseid,
                       co.fullname as coursefullname, co.shortname as courseshortname
                  FROM {customcert} c
                  JOIN {customcert_templates} ct
                    ON c.templateid = ct.id
                  JOIN {course} co
                    ON c.course = co.id";

        // Add JOIN with mdl_course_categories to exclude certificates from hidden courses.
        $sql .= " JOIN {course_categories} cat ON co.category = cat.id";

        // Only get certificates where we have to email someone.
        $sql .= " WHERE (c.emailstudents = :emailstudents
                 OR c.emailteachers = :emailteachers
                 OR $emailotherslengthsql >= 3)";

        // Check the includeinnotvisiblecourses configuration.
        if (!$includeinnotvisiblecourses) {
            // Exclude certificates from hidden courses.
            $sql .= " AND co.visible = 1 AND cat.visible = 1";
        }

        // Add condition based on certificate execution period.
        if ($certificateexecutionperiod > 0) {
            // Include courses with no end date or end date greater than the specified period.
            $sql .= " AND (co.enddate = 0 OR co.enddate > :enddate)";
            $params['enddate'] = time() - $certificateexecutionperiod;
        }

        // Execute the SQL query.
        if (!$customcerts = $DB->get_records_sql($sql, ['emailstudents' => 1, 'emailteachers' => 1] + $params)) {
            return;
        }

        // The renderers used for sending emails.
        $page = new \moodle_page();
        $htmlrenderer = $page->get_renderer('mod_customcert', 'email', 'htmlemail');
        $textrenderer = $page->get_renderer('mod_customcert', 'email', 'textemail');

        // Store the total count of certificates in the database.
        $totalcertificatestoprocess = count($customcerts);
        $DB->set_field('customcert_email_task_prgrs', 'total_certificate_to_process', $totalcertificatestoprocess, [
            'taskname' => 'email_certificate_task',
        ]);

        // Check if we need to reset and start from the beginning.
        if ($lastprocessed >= count($customcerts)) {
            $lastprocessed = 0; // Reset to the beginning.
        }

        if ($certificatesperrun <= 0) {
            // Process all certificates in a single run.
            $certificates = $customcerts;
        } else {
            // Process certificates in batches, starting from the last processed batch.
            $certificates = array_slice($customcerts, $lastprocessed, $certificatesperrun);
        }

        foreach ($certificates as $customcert) {
            // Check if the certificate is hidden, quit early.
            $fastmoduleinfo = get_fast_modinfo($customcert->courseid)->instances['customcert'][$customcert->id];
            if (!$fastmoduleinfo->visible) {
                continue;
            }

            // Do not process an empty certificate.
            $sql = "SELECT ce.*
                      FROM {customcert_elements} ce
                      JOIN {customcert_pages} cp
                        ON cp.id = ce.pageid
                      JOIN {customcert_templates} ct
                        ON ct.id = cp.templateid
                     WHERE ct.contextid = :contextid";
            if (!$DB->record_exists_sql($sql, ['contextid' => $customcert->contextid])) {
                continue;
            }

            // Get the context.
            $context = \context::instance_by_id($customcert->contextid);

            // Get the person we are going to send this email on behalf of.
            $userfrom = \core_user::get_noreply_user();

            // Store teachers for later.
            $teachers = get_enrolled_users($context, 'moodle/course:update');

            $courseshortname = format_string($customcert->courseshortname, true, ['context' => $context]);
            $coursefullname = format_string($customcert->coursefullname, true, ['context' => $context]);
            $certificatename = format_string($customcert->name, true, ['context' => $context]);

            // Used to create the email subject.
            $info = new \stdClass();
            $info->coursename = $courseshortname; // Added for BC, so users who have edited the string don't lose this value.
            $info->courseshortname = $courseshortname;
            $info->coursefullname = $coursefullname;
            $info->certificatename = $certificatename;

            // Get a list of all the issues.
            $userfields = helper::get_all_user_name_fields('u');
            $sql = "SELECT u.id, u.username, $userfields, u.email, ci.id as issueid, ci.emailed
                      FROM {customcert_issues} ci
                      JOIN {user} u
                        ON ci.userid = u.id
                     WHERE ci.customcertid = :customcertid";
            $issuedusers = $DB->get_records_sql($sql, ['customcertid' => $customcert->id]);

            // Now, get a list of users who can Manage the certificate.
            $userswithmanage = get_users_by_capability($context, 'mod/customcert:manage', 'u.id');

            // Get the context of the Custom Certificate module.
            $cm = get_coursemodule_from_instance('customcert', $customcert->id, $customcert->course);
            $context = \context_module::instance($cm->id);

            // Now, get a list of users who can view and issue the certificate but have not yet.
            // Get users with the mod/customcert:receiveissue capability in the Custom Certificate module context.
            $userswithissue = get_users_by_capability($context, 'mod/customcert:receiveissue');
            // Get users with mod/customcert:view capability.
            $userswithview = get_users_by_capability($context, 'mod/customcert:view');
            // Users with both mod/customcert:view and mod/customcert:receiveissue capabilities.
            $userswithissueview = array_intersect_key($userswithissue, $userswithview);

            foreach ($userswithissueview as $enroluser) {
                // Check if the user has already been issued.
                if (in_array($enroluser->id, array_keys((array)$issuedusers))) {
                    continue;
                }

                // Don't want to email those with the capability to manage the certificate.
                if (in_array($enroluser->id, array_keys((array)$userswithmanage))) {
                    continue;
                }

                // Now check if the certificate is not visible to the current user.
                $cm = get_fast_modinfo($customcert->courseid, $enroluser->id)->instances['customcert'][$customcert->id];
                if (!$cm->uservisible) {
                    continue;
                }

                // Check that they have passed the required time.
                if (!empty($customcert->requiredtime)) {
                    if (\mod_customcert\certificate::get_course_time($customcert->courseid,
                            $enroluser->id) < ($customcert->requiredtime * 60)) {
                        continue;
                    }
                }

                // Ensure the cert hasn't already been issued, e.g via the UI (view.php) - a race condition.
                $issueid = $DB->get_field('customcert_issues', 'id',
                    ['userid' => $enroluser->id, 'customcertid' => $customcert->id], IGNORE_MULTIPLE);
                if (empty($issueid)) {
                    // Ok, issue them the certificate.
                    $issueid = \mod_customcert\certificate::issue_certificate($customcert->id, $enroluser->id);
                }

                // Add them to the array so we email them.
                $enroluser->issueid = $issueid;
                $enroluser->emailed = 0;
                $issuedusers[] = $enroluser;
            }

            // Remove all the users who have already been emailed.
            foreach ($issuedusers as $key => $issueduser) {
                if ($issueduser->emailed) {
                    unset($issuedusers[$key]);
                }
            }

            // If there are no users to email, we can return early.
            if (!$issuedusers) {
                continue;
            }

            // Create a directory to store the PDF we will be sending.
            $tempdir = make_temp_directory('certificate/attachment');
            if (!$tempdir) {
                return;
            }

            $issueids = [];
            // Now, email the people we need to.
            foreach ($issuedusers as $user) {
                // Set up the user.
                \core\cron::setup_user($user);

                $userfullname = fullname($user);
                $info->userfullname = $userfullname;

                // Now, get the PDF.
                $template = new \stdClass();
                $template->id = $customcert->templateid;
                $template->name = $customcert->templatename;
                $template->contextid = $customcert->contextid;
                $template = new \mod_customcert\template($template);
                $filecontents = $template->generate_pdf(false, $user->id, true);

                // Set the name of the file we are going to send.
                $filename = $courseshortname . '_' . $certificatename;
                $filename = \core_text::entities_to_utf8($filename);
                $filename = strip_tags($filename);
                $filename = rtrim($filename, '.');
                $filename = str_replace('&', '_', $filename) . '.pdf';

                // Create the file we will be sending.
                $tempfile = $tempdir . '/' . md5(microtime() . $user->id) . '.pdf';
                file_put_contents($tempfile, $filecontents);

                if ($customcert->emailstudents) {
                    $renderable = new \mod_customcert\output\email_certificate(true, $userfullname, $courseshortname,
                        $coursefullname, $certificatename, $context->instanceid);

                    $subject = get_string('emailstudentsubject', 'customcert', $info);
                    $message = $textrenderer->render($renderable);
                    $messagehtml = $htmlrenderer->render($renderable);
                    email_to_user($user, $userfrom, html_entity_decode($subject, ENT_COMPAT), $message,
                        $messagehtml, $tempfile, $filename);
                }

                if ($customcert->emailteachers) {
                    $renderable = new \mod_customcert\output\email_certificate(false, $userfullname, $courseshortname,
                        $coursefullname, $certificatename, $context->instanceid);

                    $subject = get_string('emailnonstudentsubject', 'customcert', $info);
                    $message = $textrenderer->render($renderable);
                    $messagehtml = $htmlrenderer->render($renderable);
                    foreach ($teachers as $teacher) {
                        email_to_user($teacher, $userfrom, html_entity_decode($subject, ENT_COMPAT),
                            $message, $messagehtml, $tempfile, $filename);
                    }
                }

                if (!empty($customcert->emailothers)) {
                    $others = explode(',', $customcert->emailothers);
                    foreach ($others as $email) {
                        $email = trim($email);
                        if (validate_email($email)) {
                            $renderable = new \mod_customcert\output\email_certificate(false, $userfullname,
                                $courseshortname, $coursefullname, $certificatename, $context->instanceid);

                            $subject = get_string('emailnonstudentsubject', 'customcert', $info);
                            $message = $textrenderer->render($renderable);
                            $messagehtml = $htmlrenderer->render($renderable);

                            $emailuser = new \stdClass();
                            $emailuser->id = -1;
                            $emailuser->email = $email;
                            email_to_user($emailuser, $userfrom, html_entity_decode($subject, ENT_COMPAT), $message,
                                $messagehtml, $tempfile, $filename);
                        }
                    }
                }

                // Set the field so that it is emailed.
                $issueids[] = $user->issueid;
            }

            if (!empty($issueids)) {
                list($sql, $params) = $DB->get_in_or_equal($issueids, SQL_PARAMS_NAMED, 'id');
                $DB->set_field_select('customcert_issues', 'emailed', 1, 'id ' . $sql, $params);
            }
        }

        // Update the last processed position, if run in batches.
        if ($certificatesperrun > 0) {
            $newlastprocessed = $lastprocessed + count($certificates);
            $DB->set_field('customcert_email_task_prgrs', 'last_processed', $newlastprocessed, [
                'taskname' => 'email_certificate_task',
            ]);
        }
    }
}
