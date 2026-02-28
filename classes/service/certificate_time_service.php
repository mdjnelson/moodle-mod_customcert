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

declare(strict_types=1);

namespace mod_customcert\service;

use core\log\sql_internal_table_reader;
use core\log\store;
use stdClass;

/**
 * Calculates course time for certificate issuance rules.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class certificate_time_service {
    /**
     * @var callable
     */
    private $logmanagerfactory;

    /**
     * @var \moodle_database
     */
    private \moodle_database $db;

    /**
     * @var stdClass
     */
    private stdClass $cfg;

    /**
     * Create a new instance with default dependencies.
     *
     * @return self
     */
    public static function create(): self {
        global $CFG, $DB;
        return new self(static fn() => get_log_manager(), $DB, $CFG);
    }

    /**
     * certificate_time_service constructor.
     *
     * @param callable $logmanagerfactory
     * @param \moodle_database $db
     * @param stdClass $cfg
     */
    public function __construct(
        callable $logmanagerfactory,
        \moodle_database $db,
        stdClass $cfg
    ) {
        $this->logmanagerfactory = $logmanagerfactory;
        $this->db = $db;
        $this->cfg = $cfg;
    }

    /**
     * Get the time the user has spent in the course.
     *
     * @param int $courseid
     * @param int $userid
     * @return int the total time spent in seconds
     */
    public function get_course_time(int $courseid, int $userid = 0): int {
        global $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $logmanager = ($this->logmanagerfactory)();
        $readers = $logmanager->get_readers();
        $enabledreaders = get_config('tool_log', 'enabled_stores');
        if (empty($enabledreaders)) {
            return 0;
        }
        $enabledreaders = explode(',', $enabledreaders);

        // Go through all the readers until we find one that we can use.
        foreach ($enabledreaders as $enabledreader) {
            $reader = $readers[$enabledreader];
            if ($reader instanceof store) {
                $logtable = 'log';
                $coursefield = 'course';
                $timefield = 'time';
                break;
            } else if ($reader instanceof sql_internal_table_reader) {
                $logtable = $reader->get_internal_log_table_name();
                $coursefield = 'courseid';
                $timefield = 'timecreated';
                break;
            }
        }

        // If we didn't find a reader then return 0.
        if (!isset($logtable)) {
            return 0;
        }

        $sql = "SELECT id, $timefield
                  FROM {{$logtable}}
                 WHERE userid = :userid
                   AND $coursefield = :courseid
              ORDER BY $timefield ASC";
        $params = ['userid' => $userid, 'courseid' => $courseid];
        $totaltime = 0;
        if ($logs = $this->db->get_recordset_sql($sql, $params)) {
            foreach ($logs as $log) {
                if (!isset($login)) {
                    // For the first time $login is not set so the first log is also the first login.
                    $login = $log->$timefield;
                    $lasthit = $log->$timefield;
                    $totaltime = 0;
                }
                $delay = $log->$timefield - $lasthit;
                if ($delay > $this->cfg->sessiontimeout) {
                    // The difference between the last log and the current log is more than
                    // the timeout Register session value so that we have found a session!
                    $login = $log->$timefield;
                } else {
                    $totaltime += $delay;
                }
                // Now the actual log became the previous log for the next cycle.
                $lasthit = $log->$timefield;
            }

            return $totaltime;
        }

        return 0;
    }
}
