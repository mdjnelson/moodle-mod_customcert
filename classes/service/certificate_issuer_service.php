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

namespace mod_customcert\service;

use core\task\manager;
use mod_customcert\certificate;
use mod_customcert\task\email_certificate_task;

/**
 * Coordinates certificate issuing and email dispatching.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_issuer_service {
    /**
     * @var certificate_email_service
     */
    private certificate_email_service $emailservice;

    /**
     * certificate_issuer_service constructor.
     *
     * @param certificate_email_service|null $emailservice
     */
    public function __construct(?certificate_email_service $emailservice = null) {
        $this->emailservice = $emailservice ?? new certificate_email_service();
    }

    /**
     * Process a run of certificate issuance and emailing according to configuration.
     *
     * @return void
     */
    public function process_email_issuance_run(): void {
        global $DB;

        // Get the certificatesperrun, includeinnotvisiblecourses, and certificateexecutionperiod configurations.
        $certificatesperrun = (int)get_config('customcert', 'certificatesperrun');
        $includeinnotvisiblecourses = (bool)get_config('customcert', 'includeinnotvisiblecourses');
        $certificateexecutionperiod = (int)get_config('customcert', 'certificateexecutionperiod');
        $offset = (int)get_config('customcert', 'certificate_offset');
        $emailothersselect = "c.emailothers";
        $emailotherslengthsql = $DB->sql_length('c.emailothers');

        $sql = "SELECT DISTINCT c.id, c.templateid, c.course, c.requiredtime, c.emailstudents, c.emailteachers, $emailothersselect,
                       ct.id AS templateid, ct.name AS templatename, ct.contextid, co.id AS courseid,
                       co.fullname AS coursefullname, co.shortname AS courseshortname
                  FROM {customcert} c
                  JOIN {customcert_templates} ct
                    ON c.templateid = ct.id
                  JOIN {course} co
                    ON c.course = co.id
             LEFT JOIN {course_categories} cat
                    ON co.category = cat.id
             LEFT JOIN {customcert_issues} ci
                    ON c.id = ci.customcertid
                 WHERE (c.emailstudents = :emailstudents
                    OR c.emailteachers = :emailteachers
                    OR $emailotherslengthsql >= 3)";

        $params = ['emailstudents' => 1, 'emailteachers' => 1];

        // Check the includeinnotvisiblecourses configuration.
        if (!$includeinnotvisiblecourses) {
            // Exclude certificates from hidden courses.
            $sql .= " AND co.visible = 1 AND (cat.visible = 1 OR cat.id IS NULL)";
        }

        // Add condition based on certificate execution period.
        if ($certificateexecutionperiod > 0) {
            // Include courses with no end date or end date greater than the specified period.
            $sql .= " AND (co.enddate > :enddate OR (co.enddate = 0 AND (ci.timecreated > :enddate2 OR ci.timecreated IS NULL)))";
            $params['enddate'] = time() - $certificateexecutionperiod;
            $params['enddate2'] = $params['enddate'];
        }

        // Execute the SQL query.
        $customcerts = $DB->get_records_sql($sql, $params, $offset, $certificatesperrun);

        // When we get to the end of the list, reset the offset.
        set_config('certificate_offset', !empty($customcerts) ? $offset + $certificatesperrun : 0, 'customcert');

        if (empty($customcerts)) {
            return;
        }

        foreach ($customcerts as $customcert) {
            // Check if the certificate is hidden, quit early.
            $cm = get_course_and_cm_from_instance($customcert->id, 'customcert', $customcert->course)[1];
            if (!$cm->visible) {
                continue;
            }

            // Do not process an empty certificate.
            $sqlelements = "SELECT ce.*
                              FROM {customcert_elements} ce
                              JOIN {customcert_pages} cp
                                ON cp.id = ce.pageid
                              JOIN {customcert_templates} ct
                                ON ct.id = cp.templateid
                             WHERE ct.contextid = :contextid";
            if (!$DB->record_exists_sql($sqlelements, ['contextid' => $customcert->contextid])) {
                continue;
            }

            // Get the context.
            $context = \context::instance_by_id($customcert->contextid);

            // Get a list of all the issues that are already emailed (skip these users).
            $sqlissued = "SELECT u.id
                            FROM {customcert_issues} ci
                            JOIN {user} u
                              ON ci.userid = u.id
                           WHERE ci.customcertid = :customcertid
                                 AND ci.emailed = 1";
            $issuedusers = $DB->get_records_sql($sqlissued, ['customcertid' => $customcert->id]);

            // Now, get a list of users who can Manage the certificate.
            $userswithmanage = get_users_by_capability($context, 'mod/customcert:manage', 'u.id');

            // Get the context of the Custom Certificate module.
            $cmcontext = \context_module::instance($cm->id);

            // Get users with the mod/customcert:receiveissue capability in the Custom Certificate module context.
            $userswithissue = get_users_by_capability($cmcontext, 'mod/customcert:receiveissue');
            // Get users with mod/customcert:view capability.
            $userswithview = get_users_by_capability($cmcontext, 'mod/customcert:view');
            // Users with both mod/customcert:view and mod/customcert:receiveissue capabilities.
            $userswithissueview = array_intersect_key($userswithissue, $userswithview);

            // Filter remaining users by availability conditions.
            $infomodule = new \core_availability\info_module($cm);
            $filteredusers = $infomodule->filter_user_list($userswithissueview);

            foreach ($filteredusers as $filtereduser) {
                // Skip if the user has already been issued and emailed.
                if (in_array($filtereduser->id, array_keys((array)$issuedusers))) {
                    continue;
                }

                // Require mod/customcert:receiveissue capability.
                if (!in_array($filtereduser->id, array_keys($userswithissue))) {
                    continue;
                }

                // Check whether the CM is visible to this user.
                $usercm = get_fast_modinfo($customcert->courseid, $filtereduser->id)->instances['customcert'][$customcert->id];
                if (!$usercm->uservisible) {
                    continue;
                }

                // Check required time (if any).
                if (!empty($customcert->requiredtime)) {
                    if (
                        certificate::get_course_time(
                            $customcert->courseid,
                            $filtereduser->id
                        ) < ($customcert->requiredtime * 60)
                    ) {
                        continue;
                    }
                }

                // Ensure the cert hasn't already been issued; if not, issue it now.
                $issue = $DB->get_record(
                    'customcert_issues',
                    ['userid' => $filtereduser->id, 'customcertid' => $customcert->id],
                    'id, emailed'
                );

                $issueid = null;
                $emailed = 0;
                if (!empty($issue)) {
                    $issueid = (int)$issue->id;
                    $emailed = (int)$issue->emailed;
                } else {
                    $issueid = certificate::issue_certificate($customcert->id, $filtereduser->id);
                    $emailed = 0;
                }

                // If we have an issue and it has not been emailed yet, send it now.
                if (!empty($issueid) && $emailed === 0) {
                    $this->dispatch_email((int)$customcert->id, $issueid);
                }
            }
        }
    }

    /**
     * Dispatch emailing of a certificate issue using configured mode.
     *
     * @param int $customcertid
     * @param int $issueid
     * @return void
     */
    private function dispatch_email(int $customcertid, int $issueid): void {
        $useadhoc = get_config('customcert', 'useadhoc');
        if ($useadhoc) {
            $task = new email_certificate_task();
            $task->set_custom_data((object)['issueid' => $issueid, 'customcertid' => $customcertid]);
            manager::queue_adhoc_task($task);
            return;
        }

        $this->emailservice->send_issue($customcertid, $issueid);
    }
}
