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
     * List email candidates for one certificate using the same eligibility rules as batch issuance.
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
     * @return object|null Contains id, emailed, and studentemailed (int|null; see
     *   needs_email_processing()) flags for the issue
     */
    public function issue_if_needed(int $customcertid, int $userid): ?object {
        $issue = $this->find_existing_issue($customcertid, $userid);

        if ($issue) {
            return $issue;
        }

        // A newly created issue is always explicitly 0 (known, retryable), never NULL -- see
        // certificate_issue_service::issue_certificate().
        $issueid = $this->issues->create($customcertid, $userid);

        return (object)['id' => $issueid, 'emailed' => 0, 'studentemailed' => 0];
    }

    /**
     * Find an existing issue for a user/certificate pair, without creating one.
     *
     * @param int $customcertid
     * @param int $userid
     * @return object|null Contains id, emailed, and studentemailed (int|null; see
     *   needs_email_processing()) flags for the issue
     */
    private function find_existing_issue(int $customcertid, int $userid): ?object {
        $issue = $this->issues->find_by_user_certificate($customcertid, $userid);

        if (!$issue) {
            return null;
        }

        return (object)[
            'id' => (int)$issue->id,
            'emailed' => (int)$issue->emailed,
            // Preserve NULL rather than casting to int, which would collapse it into 0.
            'studentemailed' => $issue->studentemailed === null ? null : (int)$issue->studentemailed,
        ];
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
            [, $cm] = get_course_and_cm_from_instance($customcert->id, 'customcert', $customcert->course);
            if (!$cm->visible) {
                continue;
            }

            // Do not process an empty certificate.
            if (!$this->certificates->has_elements((int)$customcert->contextid)) {
                continue;
            }

            $candidates = $this->get_email_candidates_for_customcert($customcert, $cm);

            // Auto-issue only for "Email students", or when this certificate's own "Issue
            // certificates automatically" setting is enabled (#672, #904).
            $autoissue = !empty($customcert->emailstudents) || !empty($customcert->issueautomatically);

            foreach ($candidates as $filtereduser) {
                $issue = $autoissue
                    ? $this->issue_if_needed((int)$customcert->id, (int)$filtereduser->id)
                    : $this->find_existing_issue((int)$customcert->id, (int)$filtereduser->id);

                if (!empty($issue) && $this->needs_email_processing($issue, $customcert)) {
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
     * Determine whether an issue still needs email processing.
     *
     * NULL is legacy/unknown and is not retryable; 0 is explicitly retryable. Must compare with
     * === rather than (int) casting, which would collapse NULL into 0.
     *
     * @param object $issue Contains emailed (int) and studentemailed (int|null) flags, e.g. from
     *   issue_if_needed().
     * @param object $customcert
     * @return bool
     */
    private function needs_email_processing(object $issue, object $customcert): bool {
        if ((int)$issue->emailed === 0) {
            return true;
        }

        return !empty($customcert->emailstudents) && $issue->studentemailed === 0;
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
     * Find issuance candidates using capability, availability, suspension, completion and required time.
     * Report visibility rules in issue_repository do not determine issuance eligibility.
     *
     * @param object $customcert
     * @param object $cm
     * @return array
     */
    private function get_email_candidates_for_customcert(object $customcert, object $cm): array {
        // Users who need no further email processing (skip these users): once emailstudents is
        // enabled, only studentemailed = 0 remains a retry candidate.
        $issuedusers = $this->issues->list_emailed_users((int)$customcert->id, !empty($customcert->emailstudents));

        $cmcontext = context_module::instance($cm->id);

        // Outside the site home, only actively enrolled users are eligible, so a capability granted
        // at system level does not issue certificates to users who are not in the course.
        if ((int)$customcert->courseid === SITEID) {
            $userswithissue = get_users_by_capability($cmcontext, 'mod/customcert:receiveissue');
        } else {
            $userswithissue = get_enrolled_users($cmcontext, 'mod/customcert:receiveissue', 0, 'u.*', null, 0, 0, true);
        }
        $userswithview = get_users_by_capability($cmcontext, 'mod/customcert:view');
        $userswithissueview = array_intersect_key($userswithissue, $userswithview);

        // Filter remaining users by availability conditions.
        $infomodule = new info_module($cm);
        $filteredusers = $infomodule->filter_user_list($userswithissueview);

        $candidates = [];
        $timeservice = certificate_time_service::create();

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

            // Check the CM is visible and genuinely available, as uservisible alone is true for users
            // who can ignore availability restrictions (e.g. admins and managers).
            $usercm = get_fast_modinfo($customcert->courseid, $filtereduser->id)->instances['customcert'][$customcert->id];
            if (!$usercm->uservisible || !$usercm->available) {
                continue;
            }

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
}
