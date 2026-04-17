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
 * An adhoc task to backfill activity completion for existing automatically issued certificates.
 *
 * @package    mod_customcert
 * @copyright  2026 Vadym Nersesov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_customcert\task;

/**
 * Backfills activity completion for users who already received a certificate via the scheduled task.
 *
 * For every customcert instance where emailstudents is enabled, this task:
 *  - Sets completionissued = 1 (retroactive opt-in, since email was already active).
 *  - Marks activity completion for users whose certificate was already emailed.
 *
 * This task is queued automatically during the plugin upgrade to 2025122801.
 *
 * @package    mod_customcert
 * @copyright  2026 Vadym Nersesov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_backfill_task extends \core\task\adhoc_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcompletionbackfill', 'customcert');
    }

    /**
     * Execute the backfill.
     */
    public function execute() {
        global $DB;

        mtrace('Starting activity completion backfill for mod_customcert...');

        // Find all customcert instances where automatic email to students was enabled.
        // These are the activities whose existing issued certs should receive completion.
        $customcerts = $DB->get_records('customcert', ['emailstudents' => 1]);

        $processed = 0;
        $skipped = 0;

        foreach ($customcerts as $customcert) {
            // Retroactively opt this instance into completionissued if not already set.
            if (empty($customcert->completionissued)) {
                $DB->set_field('customcert', 'completionissued', 1, ['id' => $customcert->id]);
                $customcert->completionissued = 1;
            }

            // Resolve course and course-module objects.
            try {
                [$course, $cm] = get_course_and_cm_from_instance($customcert->id, 'customcert', $customcert->course);
            } catch (\Exception $e) {
                debugging(
                    'completion_backfill_task: could not resolve cm for customcert id=' . $customcert->id .
                    ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
                $skipped++;
                continue;
            }

            // Only set completion when the activity has completion tracking enabled.
            $completioninfo = new \completion_info($course);
            if (!$completioninfo->is_enabled($cm)) {
                $skipped++;
                continue;
            }

            // Find all users whose certificate was already emailed by the scheduled task.
            $issues = $DB->get_records(
                'customcert_issues',
                ['customcertid' => $customcert->id, 'emailed' => 1],
                '',
                'userid'
            );

            foreach ($issues as $issue) {
                try {
                    $completioninfo->update_state($cm, COMPLETION_COMPLETE, $issue->userid);
                    $processed++;
                } catch (\Exception $e) {
                    debugging(
                        'completion_backfill_task: could not update completion for userid=' . $issue->userid .
                        ' in customcert id=' . $customcert->id . ': ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                }
            }
        }

        mtrace("Activity completion backfill complete. Processed: $processed, Skipped (no completion): $skipped.");
    }
}
