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

use stdClass;

/**
 * Repository for loading certificates needed by the issuer service.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class certificate_repository {
    /**
     * List certificates to process in a run respecting configuration flags.
     *
     * @param int $limit Number of certificates to return (0 means all)
     * @param int $offset Offset into the certificate list
     * @param bool $includeinnotvisiblecourses Whether hidden courses/categories are allowed
     * @param int $certificateexecutionperiod Execution period in seconds (0 to disable)
     * @return array<int, stdClass>
     */
    public function list_for_issuance_run(
        int $limit,
        int $offset,
        bool $includeinnotvisiblecourses,
        int $certificateexecutionperiod
    ): array {
        global $DB;

        $emailotherslengthsql = $DB->sql_length('c.emailothers');

        $sql = "SELECT DISTINCT c.id, c.templateid, c.course, c.requiredtime, c.emailstudents, c.emailteachers, c.emailothers,
                       ct.contextid, co.id AS courseid, co.fullname AS coursefullname, co.shortname AS courseshortname
                  FROM {customcert} c
                  JOIN {customcert_templates} ct ON c.templateid = ct.id
                  JOIN {course} co ON c.course = co.id
             LEFT JOIN {course_categories} cat ON co.category = cat.id
             LEFT JOIN {customcert_issues} ci ON c.id = ci.customcertid
                 WHERE (c.emailstudents = :emailstudents OR c.emailteachers = :emailteachers OR $emailotherslengthsql >= 3)";

        $params = ['emailstudents' => 1, 'emailteachers' => 1];

        if (!$includeinnotvisiblecourses) {
            $sql .= " AND co.visible = 1 AND (cat.visible = 1 OR cat.id IS NULL)";
        }

        if ($certificateexecutionperiod > 0) {
            $cutoff = time() - $certificateexecutionperiod;
            $sql .= " AND (co.enddate > :enddate OR (co.enddate = 0 AND (ci.timecreated > :enddate2 OR ci.timecreated IS NULL)))";
            $params['enddate'] = $cutoff;
            $params['enddate2'] = $cutoff;
        }

        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Load a certificate for processing with visibility metadata.
     *
     * @param int $customcertid
     * @return stdClass|null
     */
    public function get_for_processing(int $customcertid): ?stdClass {
        global $DB;

        $sql = "SELECT c.id, c.templateid, c.course, c.requiredtime, c.emailstudents, c.emailteachers, c.emailothers,
                       ct.contextid, co.id AS courseid, co.visible AS coursevisible, cat.visible AS categoryvisible
                  FROM {customcert} c
                  JOIN {customcert_templates} ct ON c.templateid = ct.id
                  JOIN {course} co ON c.course = co.id
             LEFT JOIN {course_categories} cat ON co.category = cat.id
                 WHERE c.id = :id";

        return $DB->get_record_sql($sql, ['id' => $customcertid]) ?: null;
    }

    /**
     * Get a certificate record by id.
     *
     * @param int $id
     * @return stdClass
     */
    public function get_by_id(int $id): stdClass {
        global $DB;
        return $DB->get_record('customcert', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Get a certificate record by template id.
     *
     * @param int $templateid
     * @return stdClass|null
     */
    public function get_by_template_id(int $templateid): ?stdClass {
        global $DB;
        return $DB->get_record('customcert', ['templateid' => $templateid]) ?: null;
    }

    /**
     * Determine if the certificate has at least one element.
     *
     * @param int $contextid
     * @return bool
     */
    public function has_elements(int $contextid): bool {
        global $DB;

        $sql = "SELECT 1
                  FROM {customcert_elements} ce
                  JOIN {customcert_pages} cp ON cp.id = ce.pageid
                  JOIN {customcert_templates} ct ON ct.id = cp.templateid
                 WHERE ct.contextid = :contextid";

        return $DB->record_exists_sql($sql, ['contextid' => $contextid]);
    }
}
