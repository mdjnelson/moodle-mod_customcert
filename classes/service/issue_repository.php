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

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\service\certificate_issue_service;
use stdClass;

/**
 * Repository for certificate issues used by issuer/email services.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class issue_repository {
    /**
     * Find an issue for a user/certificate pair.
     *
     * @param int $customcertid
     * @param int $userid
     * @return stdClass|null
     */
    public function find_by_user_certificate(int $customcertid, int $userid): ?stdClass {
        global $DB;

        return $DB->get_record(
            'customcert_issues',
            ['userid' => $userid, 'customcertid' => $customcertid],
            'id, emailed'
        ) ?: null;
    }

    /**
     * Create a new issue using the existing certificate helper for side effects.
     *
     * @param int $customcertid
     * @param int $userid
     * @return int Issue id
     */
    public function create(int $customcertid, int $userid): int {
        $service = new certificate_issue_service();

        return $service->issue_certificate($customcertid, $userid);
    }

    /**
     * Mark an issue as emailed.
     *
     * @param int $issueid
     * @return void
     */
    public function mark_emailed(int $issueid): void {
        global $DB;

        $DB->set_field('customcert_issues', 'emailed', 1, ['id' => $issueid]);
    }

    /**
     * List user ids that already have emailed issues for a certificate.
     *
     * @param int $customcertid
     * @return array<int, stdClass> keyed by userid
     */
    public function list_emailed_users(int $customcertid): array {
        global $DB;

        $sql = "SELECT u.id
                  FROM {customcert_issues} ci
                  JOIN {user} u ON ci.userid = u.id
                 WHERE ci.customcertid = :customcertid
                       AND ci.emailed = 1";

        return $DB->get_records_sql($sql, ['customcertid' => $customcertid]);
    }
}
