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
use mod_customcert\task\email_certificate_task;
use context_module;
use core_availability\info_module;
use completion_info;
use mod_customcert\service\certificate_time_service;

/**
 * Coordinates certificate issuing and email dispatching.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class certificate_issuer_service {
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
     * Create a certificate_issuer_service with default dependencies.
     *
     * @return self
     */
    public static function create(): self {
        return new self(
            certificate_email_service::create(),
            new certificate_repository(),
            new issue_repository(),
        );
    }

    /**
     * certificate_issuer_service constructor.
     *
     * @param certificate_email_service $emailservice
     * @param certificate_repository $certificates
     * @param issue_repository $issues
     */
    public function __construct(
        certificate_email_service $emailservice,
        certificate_repository $certificates,
        issue_repository $issues
    ) {
        $this->emailservice = $emailservice;
        $this->certificates = $certificates;
        $this->issues = $issues;
    }

    /**
     * List eligible users for emailing for a single certificate.
     *
     * This is a single-certificate public entry point to the same candidate-eligibility rules
     * used by the batch cron path (process_email_issuance_run(), invoked from
     * issue_certificates_task). It is not called by that batch path itself — batch processing
     * loads its certificate list and visibility filtering via
     * certificate_repository::list_for_issuance_run() instead, since that's cheaper to express
     * as one SQL query across many certificates than as a per-certificate PHP check. This method
     * exists for callers (e.g. an external script, or a future "preview eligible recipients"
     * feature) that need the same eligibility rules for one known certificate id without running
     * a full batch pass. Keep it — it is intentional API surface, not dead code.
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
        $issue = $this->find_existing_issue($customcertid, $userid);

        if ($issue) {
            return $issue;
        }

        $issueid = $this->issues->create($customcertid, $userid);

        return (object)['id' => $issueid, 'emailed' => 0];
    }

    /**
     * Find an existing issue for a user/certificate pair, without creating one.
     *
     * @param int $customcertid
     * @param int $userid
     * @return object|null Contains id and emailed flags for the issue
     */
    private function find_existing_issue(int $customcertid, int $userid): ?object {
        $issue = $this->issues->find_by_user_certificate($customcertid, $userid);

        if (!$issue) {
            return null;
        }

        return (object)['id' => (int)$issue->id, 'emailed' => (int)$issue->emailed];
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
                // Only proactively issue a certificate on the student's behalf when emailstudents is
                // enabled. Otherwise (e.g. only emailteachers/emailothers is set), we must not manufacture
                // a certificate for a student who hasn't triggered issuance themselves (e.g. by viewing
                // it) -- we can only notify about certificates that already exist.
                $issue = !empty($customcert->emailstudents)
                    ? $this->issue_if_needed((int)$customcert->id, (int)$filtereduser->id)
                    : $this->find_existing_issue((int)$customcert->id, (int)$filtereduser->id);

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
     * This decides who a *new* certificate should be issued/emailed to, based on capability,
     * availability, suspension, completion and required-time rules. It is unrelated to
     * issue_repository::get_conditional_issues_sql(), which scopes the already-issued-certificates
     * *report* to what the current viewer is permitted to see (excluding managers/admins,
     * restricting by the viewer's group). Despite both filtering a list of users, they answer
     * different questions and must not be merged into one another.
     *
     * @param object $customcert
     * @param object $cm
     * @return array
     */
    private function get_email_candidates_for_customcert(object $customcert, object $cm): array {
        // Get a list of all the issues that are already emailed (skip these users).
        $issuedusers = $this->issues->list_emailed_users((int)$customcert->id);

        // Get the context of the Custom Certificate module.
        $cmcontext = context_module::instance($cm->id);

        // Get users with the mod/customcert:receiveissue capability in the Custom Certificate module context.
        $userswithissue = get_users_by_capability($cmcontext, 'mod/customcert:receiveissue');
        // Get users with mod/customcert:view capability.
        $userswithview = get_users_by_capability($cmcontext, 'mod/customcert:view');
        // Users with both mod/customcert:view and mod/customcert:receiveissue capabilities.
        $userswithissueview = array_intersect_key($userswithissue, $userswithview);

        // Filter remaining users by availability conditions.
        $infomodule = new info_module($cm);
        $filteredusers = $infomodule->filter_user_list($userswithissueview);

        $candidates = [];
        $timeservice = certificate_time_service::create();
        $completion = new completion_info(get_course((int)$customcert->courseid));

        foreach ($filteredusers as $filtereduser) {
            // Do not issue certs to suspended users.
            if ($filtereduser->suspended) {
                continue;
            }

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

            // Skip users who have not yet met completion conditions configured on the certificate itself.
            if (
                $completion->is_enabled($usercm) &&
                !$this->has_met_own_completion($completion, $usercm, (int)$filtereduser->id)
            ) {
                continue;
            }

            // Check required time (if any).
            if (!empty($customcert->requiredtime)) {
                if (
                    $timeservice->get_course_time(
                        (int)$customcert->courseid,
                        (int)$filtereduser->id
                    ) < ($customcert->requiredtime * 60)
                ) {
                    continue;
                }
            }

            $candidates[$filtereduser->id] = $filtereduser;
        }

        return $candidates;
    }

    /**
     * Check whether a user has met the completion conditions configured on the certificate cm itself.
     *
     * This is distinct from availability/restrict access, which is already enforced via uservisible.
     * Activity completion tracking is only ever consulted by other activities' restrict access rules
     * unless we check it explicitly here, so a certificate configured with completion conditions but no
     * restrict access rule would otherwise be issued/emailed regardless of the user's completion state.
     *
     * For automatic tracking, deliberately excludes the completionemailed custom rule: it is what
     * issuing/emailing this certificate is *for*, so requiring it here would make it permanently
     * unreachable through this candidate list -- the student would need to already be emailed to
     * become eligible to be emailed. Other core completion criteria configured on the certificate
     * itself (e.g. completionview) still gate candidacy normally.
     *
     * For manual tracking there is no such circularity -- completionemailed is only ever a
     * custom rule evaluated under automatic tracking, so get_core_completion_state() would
     * return an empty array here (it only covers grade/passgrade/view) and vacuously pass anyone
     * who hasn't manually ticked the activity complete. Use the aggregate completion state
     * instead, exactly as before this method started excluding completionemailed.
     *
     * @param completion_info $completion
     * @param object $cm
     * @param int $userid
     * @return bool
     */
    private function has_met_own_completion(completion_info $completion, object $cm, int $userid): bool {
        if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
            $data = $completion->get_data($cm, false, $userid);

            return in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
        }

        $completionstate = $completion->get_core_completion_state($cm, $userid);

        foreach ($completionstate as $state) {
            if (!in_array((int)$state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
                return false;
            }
        }

        return true;
    }
}
