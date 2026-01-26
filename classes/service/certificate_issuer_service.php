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
     * @var certificate_repository
     */
    private certificate_repository $certificates;

    /**
     * @var issue_repository
     */
    private issue_repository $issues;

    /**
     * certificate_issuer_service constructor.
     *
     * @param certificate_email_service|null $emailservice
     * @param certificate_repository|null $certificates
     * @param issue_repository|null $issues
     */
    public function __construct(
        ?certificate_email_service $emailservice = null,
        ?certificate_repository $certificates = null,
        ?issue_repository $issues = null
    ) {
        $this->emailservice = $emailservice ?? new certificate_email_service();
        $this->certificates = $certificates ?? new certificate_repository();
        $this->issues = $issues ?? new issue_repository();
    }

    /**
     * List eligible users for emailing for a single certificate.
     *
     * @param int $customcertid
     * @return array keyed by userid
     */
    public function list_email_candidates(int $customcertid): array {
        $customcert = $this->certificates->get_for_processing($customcertid);
        if (!$customcert) {
            return [];
        }

        $includeinnotvisiblecourses = (bool)get_config('customcert', 'includeinnotvisiblecourses');
        if (!$includeinnotvisiblecourses && $this->is_hidden_course($customcert)) {
            return [];
        }

        [, $cm] = get_course_and_cm_from_instance($customcert->id, 'customcert', $customcert->course);
        if (!$cm->visible) {
            return [];
        }

        if (!$this->certificates->has_elements((int)$customcert->contextid)) {
            return [];
        }

        return $this->get_email_candidates_for_customcert($customcert, $cm);
    }

    /**
     * Issue a certificate for a user if needed.
     *
     * @param int $customcertid
     * @param int $userid
     * @return object|null Contains id and emailed flags for the issue
     */
    public function issue_if_needed(int $customcertid, int $userid): ?object {
        $issue = $this->issues->find_by_user_certificate($customcertid, $userid);

        if ($issue) {
            return (object)['id' => (int)$issue->id, 'emailed' => (int)$issue->emailed];
        }

        $issueid = $this->issues->create($customcertid, $userid);

        return (object)['id' => $issueid, 'emailed' => 0];
    }

    /**
     * Queue or send the email for an issue depending on configuration.
     *
     * @param int $customcertid
     * @param int $issueid
     * @return void
     */
    public function queue_or_send_email(int $customcertid, int $issueid): void {
        $this->dispatch_email($customcertid, $issueid);
    }

    /**
     * Process a run of certificate issuance and emailing according to configuration.
     *
     * @return void
     */
    public function process_email_issuance_run(): void {
        // Get the certificatesperrun, includeinnotvisiblecourses, and certificateexecutionperiod configurations.
        $certificatesperrun = (int)get_config('customcert', 'certificatesperrun');
        $includeinnotvisiblecourses = (bool)get_config('customcert', 'includeinnotvisiblecourses');
        $certificateexecutionperiod = (int)get_config('customcert', 'certificateexecutionperiod');
        $offset = (int)get_config('customcert', 'certificate_offset');
        $customcerts = $this->certificates->list_for_issuance_run(
            $certificatesperrun,
            $offset,
            $includeinnotvisiblecourses,
            $certificateexecutionperiod
        );

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
            if (!$this->certificates->has_elements((int)$customcert->contextid)) {
                continue;
            }

            $candidates = $this->get_email_candidates_for_customcert($customcert, $cm);

            foreach ($candidates as $filtereduser) {
                $issue = $this->issue_if_needed((int)$customcert->id, (int)$filtereduser->id);

                if (!empty($issue) && (int)$issue->emailed === 0) {
                    $this->queue_or_send_email((int)$customcert->id, (int)$issue->id);
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

    /**
     * Determine if the course/category should be skipped when hidden.
     *
     * @param object $customcert
     * @return bool
     */
    private function is_hidden_course(object $customcert): bool {
        $coursevisible = isset($customcert->coursevisible) ? (int)$customcert->coursevisible : 1;
        $categoryvisible = property_exists($customcert, 'categoryvisible') ? $customcert->categoryvisible : null;

        return $coursevisible === 0 || ($categoryvisible !== null && (int)$categoryvisible === 0);
    }

    /**
     * Build the list of eligible users for a certificate within a CM context.
     *
     * @param object $customcert
     * @param object $cm
     * @return array
     */
    private function get_email_candidates_for_customcert(object $customcert, object $cm): array {
        // Get a list of all the issues that are already emailed (skip these users).
        $issuedusers = $this->issues->list_emailed_users((int)$customcert->id);

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

        $candidates = [];

        foreach ($filteredusers as $filtereduser) {
            // Skip if the user has already been issued and emailed.
            if (array_key_exists($filtereduser->id, $issuedusers)) {
                continue;
            }

            // Require mod/customcert:receiveissue capability.
            if (!array_key_exists($filtereduser->id, $userswithissue)) {
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

            $candidates[$filtereduser->id] = $filtereduser;
        }

        return $candidates;
    }
}
