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

use context_module;
use core_user\fields;
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
            ['userid' => $userid, 'customcertid' => $customcertid]
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
        $service = certificate_issue_service::create();

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
     * Get an issue by id, throwing an exception if not found.
     *
     * @param int $id
     * @return stdClass
     */
    public function get_by_id_or_fail(int $id): stdClass {
        global $DB;

        return $DB->get_record('customcert_issues', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Delete an issue by id.
     *
     * @param int $id
     * @return void
     */
    public function delete(int $id): void {
        global $DB;

        $DB->delete_records('customcert_issues', ['id' => $id]);
    }

    /**
     * List all issues for a user/certificate pair.
     *
     * @param int $customcertid
     * @param int $userid
     * @return array
     */
    public function list_by_user_certificate(int $customcertid, int $userid): array {
        global $DB;

        return $DB->get_records('customcert_issues', ['userid' => $userid, 'customcertid' => $customcertid]);
    }

    /**
     * Check whether an issue exists for a user/certificate pair.
     *
     * @param int $customcertid
     * @param int $userid
     * @return bool
     */
    public function exists_for_user(int $customcertid, int $userid): bool {
        global $DB;

        return $DB->record_exists('customcert_issues', ['userid' => $userid, 'customcertid' => $customcertid]);
    }

    /**
     * List all issues for a certificate.
     *
     * @param int $customcertid
     * @return array
     */
    public function list_by_certificate(int $customcertid): array {
        global $DB;

        return $DB->get_records('customcert_issues', ['customcertid' => $customcertid]);
    }

    /**
     * Delete all issues for a certificate.
     *
     * @param int $customcertid
     * @return void
     */
    public function delete_by_certificate(int $customcertid): void {
        global $DB;

        $DB->delete_records('customcert_issues', ['customcertid' => $customcertid]);
    }

    /**
     * Delete all issues for all certificates in a course.
     *
     * @param int $courseid
     * @return void
     */
    public function delete_by_course(int $courseid): void {
        global $DB;

        $sql = "SELECT cert.id
                  FROM {customcert} cert
                 WHERE cert.course = :courseid";
        $DB->delete_records_select('customcert_issues', "customcertid IN ($sql)", ['courseid' => $courseid]);
    }

    /**
     * Returns a list of issued customcerts.
     *
     * @param int $customcertid
     * @param bool $groupmode are we in group mode
     * @param stdClass $cm the course module
     * @param int $limitfrom
     * @param int $limitnum
     * @param string $sort
     * @return array the users
     */
    public function get_issues(
        int $customcertid,
        bool $groupmode,
        stdClass $cm,
        int $limitfrom,
        int $limitnum,
        string $sort = ''
    ): array {
        global $DB;

        [$conditionssql, $conditionsparams] = $this->get_conditional_issues_sql($cm, $groupmode);

        if (empty($conditionsparams)) {
            return [];
        }

        $context = context_module::instance($cm->id);
        $query = fields::for_identity($context)->with_userpic()->get_sql('u', true, '', '', false);

        $allparams = $query->params + $conditionsparams + ['customcertid' => $customcertid];

        $orderby = $sort ?: $DB->sql_fullname();

        $sql = "SELECT $query->selects, ci.id as issueid, ci.code, ci.timecreated
                  FROM {user} u
            INNER JOIN {customcert_issues} ci ON (u.id = ci.userid)
                       $query->joins
                 WHERE u.deleted = 0 AND ci.customcertid = :customcertid
                       $conditionssql
              ORDER BY $orderby";

        return $DB->get_records_sql($sql, $allparams, $limitfrom, $limitnum);
    }

    /**
     * Returns the total number of issues for a given customcert.
     *
     * @param int $customcertid
     * @param stdClass $cm the course module
     * @param bool $groupmode the group mode
     * @return int the number of issues
     */
    public function get_number_of_issues(int $customcertid, stdClass $cm, bool $groupmode): int {
        global $DB;

        [$conditionssql, $conditionsparams] = $this->get_conditional_issues_sql($cm, $groupmode);

        if (empty($conditionsparams)) {
            return 0;
        }

        $allparams = $conditionsparams + ['customcertid' => $customcertid];

        $sql = "SELECT COUNT(u.id) as count
                  FROM {user} u
            INNER JOIN {customcert_issues} ci
                    ON u.id = ci.userid
                 WHERE u.deleted = 0
                   AND ci.customcertid = :customcertid
                       $conditionssql";
        return $DB->count_records_sql($sql, $allparams);
    }

    /**
     * Returns an array of the conditional variables to use in the get_issues SQL query.
     *
     * @param stdClass $cm the course module
     * @param bool $groupmode are we in group mode ?
     * @return array the conditional variables
     */
    public function get_conditional_issues_sql(stdClass $cm, bool $groupmode): array {
        global $DB, $USER;

        $context = context_module::instance($cm->id);
        $conditionssql = '';
        $conditionsparams = [];

        $certmanagers = array_keys(get_users_by_capability($context, 'mod/customcert:manage', 'u.id'));
        $certmanagers = array_merge($certmanagers, array_keys(get_admins()));
        [$sql, $params] = $DB->get_in_or_equal($certmanagers, SQL_PARAMS_NAMED, 'cert');
        $conditionssql .= "AND NOT u.id $sql \n";
        $conditionsparams += $params;

        if ($groupmode) {
            $canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);
            $currentgroup = groups_get_activity_group($cm);

            if (!$currentgroup && !$canaccessallgroups) {
                return ['', []];
            }

            if ($currentgroup) {
                if (!$canaccessallgroups) {
                    if (isguestuser()) {
                        return ['', []];
                    }

                    $usersgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
                    if ($usersgroups) {
                        if (!isset($usersgroups[$currentgroup])) {
                            return ['', []];
                        }
                    } else {
                        return ['', []];
                    }
                }

                $groupusers = array_keys(groups_get_members($currentgroup, 'u.*'));
                if (empty($groupusers)) {
                    return ['', []];
                }

                [$sql, $params] = $DB->get_in_or_equal($groupusers, SQL_PARAMS_NAMED, 'grp');
                $conditionssql .= "AND u.id $sql ";
                $conditionsparams += $params;
            }
        }

        return [$conditionssql, $conditionsparams];
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
