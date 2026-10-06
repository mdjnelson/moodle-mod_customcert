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

namespace mod_customcert;

use context_module;
use mod_customcert\service\certificate_time_service;
use stdClass;

/**
 * Access checks for certificates downloaded through the mobile app.
 *
 * @package   mod_customcert
 * @copyright 2026 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile_access {
    /**
     * Enforces access to the certificate for the current user.
     *
     * @param stdClass $cm The course module.
     * @param stdClass $certificate The customcert record.
     * @param int $userid The user whose certificate is requested.
     * @param bool $issued Whether the requested user already has an issue.
     * @return bool False if the request should end without generating a PDF.
     */
    public static function require_access(stdClass $cm, stdClass $certificate, int $userid, bool $issued): bool {
        global $USER;

        require_login($cm->course, false, $cm, true, true);

        $context = context_module::instance($cm->id);
        require_capability('mod/customcert:view', $context);

        if ($userid != $USER->id) {
            require_capability('mod/customcert:viewreport', $context);
            return $issued;
        }

        if ($certificate->requiredtime) {
            $timeservice = certificate_time_service::create();
            if ($timeservice->get_course_time((int)$certificate->course, (int)$USER->id) < ($certificate->requiredtime * 60)) {
                return false;
            }
        }

        if (!$issued) {
            require_capability('mod/customcert:receiveissue', $context);
        }

        return true;
    }
}
