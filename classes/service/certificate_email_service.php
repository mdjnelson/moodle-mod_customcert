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
 * Service responsible for emailing certificate issues.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_customcert\service;

use core\cron;
use core_shutdown_manager;
use mod_customcert\helper;
use mod_customcert\output\email_certificate;
use mod_customcert\template;
use stdClass;

/**
 * Encapsulates certificate email dispatch.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_email_service {
    /**
     * @var template_service
     */
    private template_service $templateservice;

    /**
     * @var issue_repository
     */
    private issue_repository $issues;

    /**
     * certificate_email_service constructor.
     *
     * @param template_service|null $templateservice
     * @param issue_repository|null $issues
     */
    public function __construct(?template_service $templateservice = null, ?issue_repository $issues = null) {
        $this->templateservice = $templateservice ?? new template_service();
        $this->issues = $issues ?? new issue_repository();
    }

    /**
     * Send an issued certificate via email to configured recipients.
     *
     * @param int $customcertid
     * @param int $issueid
     * @return void
     */
    public function send_issue(int $customcertid, int $issueid): void {
        global $DB;

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

        $userfields = helper::get_all_user_name_fields('u');
        $sql = "SELECT u.id, u.username, $userfields, u.email, u.mailformat, ci.id as issueid, ci.emailed
                  FROM {customcert_issues} ci
                  JOIN {user} u ON ci.userid = u.id
                 WHERE ci.customcertid = :customcertid
                   AND ci.id = :issueid";
        $user = $DB->get_record_sql($sql, ['customcertid' => $customcertid, 'issueid' => $issueid]);
        if (!$user) {
            return;
        }

        $tempdir = make_temp_directory('certificate/attachment');
        if (!$tempdir) {
            return;
        }

        // Setup the user for the cron.
        cron::setup_user($user);

        $context = \context::instance_by_id($customcert->contextid);
        $userfrom = \core_user::get_noreply_user();

        $courseshortname = format_string($customcert->courseshortname, true, ['context' => $context]);
        $coursefullname = format_string($customcert->coursefullname, true, ['context' => $context]);
        $certificatename = format_string($customcert->name, true, ['context' => $context]);

        $info = new stdClass();
        $info->coursename = $courseshortname;
        $info->courseshortname = $courseshortname;
        $info->coursefullname = $coursefullname;
        $info->certificatename = $certificatename;

        $page = new \moodle_page();
        $htmlrenderer = $page->get_renderer('mod_customcert', 'email', 'htmlemail');
        $textrenderer = $page->get_renderer('mod_customcert', 'email', 'textemail');

        $originallang = current_language();
        $userfullname = fullname($user);
        $info->userfullname = $userfullname;

        $template = template::load((int)$customcert->templateid);
        $filecontents = $this->templateservice->generate_pdf($template, false, (int)$user->id, true);

        $filename = $courseshortname . '_' . $certificatename;
        $filename = \core_text::entities_to_utf8($filename);
        $filename = strip_tags($filename);
        $filename = rtrim($filename, '.');
        $filename = str_replace('&', '_', $filename) . '.pdf';

        $tempfile = $tempdir . '/' . md5(microtime() . $user->id) . '.pdf';
        file_put_contents($tempfile, $filecontents);

        if ($customcert->emailstudents) {
            $recipientlang = mod_customcert_get_language_to_use($customcert, $user, $customcert->courselang ?? null);
            $switched = mod_customcert_apply_runtime_language($recipientlang);
            if ($switched) {
                // This is a failsafe -- if an exception triggers during the template rendering, this should still execute.
                // Preventing a user from getting trapped with the wrong language.
                core_shutdown_manager::register_function('force_current_language', [$originallang]);
            }

            $renderable = new email_certificate(
                true,
                $userfullname,
                $courseshortname,
                $coursefullname,
                $certificatename,
                $context->instanceid
            );

            $subject = get_string('emailstudentsubject', 'customcert', $info);
            $message = $textrenderer->render($renderable);
            $messagehtml = $htmlrenderer->render($renderable);
            email_to_user(
                $user,
                $userfrom,
                html_entity_decode($subject, ENT_COMPAT),
                $message,
                $messagehtml,
                $tempfile,
                $filename
            );

            if ($recipientlang !== $originallang) {
                mod_customcert_apply_runtime_language($originallang);
            }
        }

        if ($customcert->emailteachers) {
            $teachers = get_enrolled_users($context, 'moodle/course:update');

            $renderable = new email_certificate(
                false,
                $userfullname,
                $courseshortname,
                $coursefullname,
                $certificatename,
                $context->instanceid
            );

            foreach ($teachers as $teacher) {
                $recipientlang = mod_customcert_get_language_to_use($customcert, $teacher, $customcert->courselang ?? null);
                $switched = mod_customcert_apply_runtime_language($recipientlang);
                if ($switched) {
                    // This is a failsafe -- if an exception triggers during the template rendering, this should still execute.
                    // Preventing a user from getting trapped with the wrong language.
                    core_shutdown_manager::register_function('force_current_language', [$originallang]);
                }

                $subject = get_string('emailnonstudentsubject', 'customcert', $info);
                $message = $textrenderer->render($renderable);
                $messagehtml = $htmlrenderer->render($renderable);
                email_to_user(
                    $teacher,
                    $userfrom,
                    html_entity_decode($subject, ENT_COMPAT),
                    $message,
                    $messagehtml,
                    $tempfile,
                    $filename
                );

                if ($recipientlang !== $originallang) {
                    mod_customcert_apply_runtime_language($originallang);
                }
            }
        }

        if (!empty($customcert->emailothers)) {
            $others = explode(',', $customcert->emailothers);
            foreach ($others as $email) {
                $email = trim($email);
                if (validate_email($email)) {
                    $renderable = new email_certificate(
                        false,
                        $userfullname,
                        $courseshortname,
                        $coursefullname,
                        $certificatename,
                        $context->instanceid
                    );

                    $subject = get_string('emailnonstudentsubject', 'customcert', $info);
                    $message = $textrenderer->render($renderable);
                    $messagehtml = $htmlrenderer->render($renderable);

                    $emailuser = new stdClass();
                    $emailuser->id = -1;
                    $emailuser->email = $email;
                    email_to_user(
                        $emailuser,
                        $userfrom,
                        html_entity_decode($subject, ENT_COMPAT),
                        $message,
                        $messagehtml,
                        $tempfile,
                        $filename
                    );
                }
            }
        }

        $this->issues->mark_emailed($issueid);
    }
}
