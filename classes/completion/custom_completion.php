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
 * Activity custom completion class for mod_customcert.
 *
 * @package    mod_customcert
 * @copyright  2026 Vadym Nersesov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion class for mod_customcert.
 *
 * Evaluates the "completionissued" rule: marks the activity complete when a
 * certificate has been emailed to the student.
 *
 * @package    mod_customcert
 * @copyright  2026 Vadym Nersesov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state (COMPLETION_COMPLETE or COMPLETION_INCOMPLETE).
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $customcertid = $this->cm->instance;
        $userid = $this->userid;

        if ($rule === 'completionissued') {
            $customcert = $DB->get_record('customcert', ['id' => $customcertid], 'id, completionissued, emailstudents',
                MUST_EXIST);

            // Rule is active only when email to students is enabled (globally or per-instance).
            if (empty($customcert->emailstudents) && !get_config('customcert', 'emailstudents')) {
                return COMPLETION_INCOMPLETE;
            }

            $emailed = $DB->record_exists('customcert_issues',
                ['customcertid' => $customcertid, 'userid' => $userid, 'emailed' => 1]);

            return $emailed ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionissued'];
    }

    /**
     * Returns an associative array of the descriptions of the custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionissued' => get_string('completionissued', 'customcert'),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionissued',
        ];
    }
}
