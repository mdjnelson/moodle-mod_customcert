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

use mod_customcert\helper;
use stdClass;

/**
 * Repository for email-specific certificate and issue data loading.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class issue_email_repository {
    /**
     * Load a certificate with the fields required for emailing.
     *
     * @param int $customcertid
     * @return stdClass|null
     */
    public function get_customcert_for_email(int $customcertid): ?stdClass {
        global $DB;

        $sql = "SELECT c.*, ct.id as templateid, ct.name as templatename, ct.contextid, co.id as courseid,
                       co.fullname as coursefullname, co.shortname as courseshortname, co.lang as courselang
                  FROM {customcert} c
                  JOIN {customcert_templates} ct ON c.templateid = ct.id
                  JOIN {course} co ON c.course = co.id
                 WHERE c.id = :id";

        return $DB->get_record_sql($sql, ['id' => $customcertid]) ?: null;
    }

    /**
     * Load the issued user for emailing.
     *
     * @param int $customcertid
     * @param int $issueid
     * @return stdClass|null
     */
    public function get_user_for_issue(int $customcertid, int $issueid): ?stdClass {
        global $DB;

        $userfields = helper::get_all_user_name_fields('u');
        $sql = "SELECT u.id, u.username, $userfields, u.email, u.mailformat, ci.id as issueid, ci.emailed
                  FROM {customcert_issues} ci
                  JOIN {user} u ON ci.userid = u.id
                 WHERE ci.customcertid = :customcertid
                   AND ci.id = :issueid";

        return $DB->get_record_sql($sql, ['customcertid' => $customcertid, 'issueid' => $issueid]) ?: null;
    }
}
