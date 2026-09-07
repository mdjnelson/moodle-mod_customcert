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

/**
 * Hook callbacks reacting to activity completion changes.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use core_completion\hook\after_cm_completion_updated;

/**
 * Hook callbacks reacting to activity completion changes.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_hook_callbacks {
    /**
     * Issue a certificate when a teacher/admin manually overrides a student's completion to complete.
     *
     * @param after_cm_completion_updated $hook
     * @return void
     */
    public static function after_cm_completion_updated(after_cm_completion_updated $hook): void {
        global $DB;

        if ($hook->cm->modname !== 'customcert') {
            return;
        }

        // Only explicit completion overrides should issue a certificate.
        if (empty($hook->data->overrideby)) {
            return;
        }

        if ((int)$hook->data->completionstate !== COMPLETION_COMPLETE) {
            return;
        }

        $customcertid = (int)$hook->cm->instance;
        $userid = (int)$hook->data->userid;

        if ($DB->record_exists('customcert_issues', ['userid' => $userid, 'customcertid' => $customcertid])) {
            return;
        }

        certificate::issue_certificate($customcertid, $userid);
    }
}
