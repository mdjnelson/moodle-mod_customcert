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
use moodle_exception;
use stdClass;

/**
 * Group visibility checks for downloading another user's certificate.
 *
 * Shared by the web and mobile download routes. Callers remain responsible for the
 * mod/customcert:viewreport capability and for checking that an issue exists.
 *
 * @package   mod_customcert
 * @copyright 2026 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class download_access {
    /**
     * Returns whether the current user may download the certificate of the given recipient.
     *
     * The decision is made from stored group membership only, never from the group selected in the
     * session or supplied in the request, and denies by default.
     *
     * Users without moodle/site:accessallgroups may only download for recipients who share a group
     * with them, within the activity's grouping. This applies to both separate and visible groups, as
     * the report only lists members of a group the viewer belongs to in either mode. Recipients and
     * requesters who belong to no such group are denied.
     *
     * The report's exclusion of managers and administrators is a listing convenience rather than a
     * visibility boundary, so it is intentionally not applied here.
     *
     * @param stdClass $cm The course module.
     * @param int $userid The user whose certificate is requested.
     * @return bool
     */
    public static function can_download_for_user(stdClass $cm, int $userid): bool {
        global $USER;

        if ($userid == $USER->id) {
            return true;
        }

        if (!groups_get_activity_groupmode($cm)) {
            return true;
        }

        if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
            return true;
        }

        $requestergroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid, 'g.id');
        if (empty($requestergroups)) {
            return false;
        }

        $recipientgroups = groups_get_all_groups($cm->course, $userid, $cm->groupingid, 'g.id');

        return !empty(array_intersect_key($requestergroups, $recipientgroups));
    }

    /**
     * Requires that the current user may download the certificate of the given recipient.
     *
     * @param stdClass $cm The course module.
     * @param int $userid The user whose certificate is requested.
     * @throws moodle_exception If access is denied.
     */
    public static function require_download_for_user(stdClass $cm, int $userid): void {
        if (!self::can_download_for_user($cm, $userid)) {
            throw new moodle_exception('nopermissions', 'error', '', get_string('download'));
        }
    }
}
