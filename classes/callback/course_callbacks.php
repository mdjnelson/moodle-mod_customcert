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

/**
 * Course reset and user report callback implementations.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\callback;

use mod_customcert\service\issue_repository;

/**
 * Handles course reset and user activity report callbacks for customcert.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_callbacks {
    /**
     * This function is used by the reset_course_userdata function in moodlelib.
     * This function will remove all posts from the specified customcert
     * and clean up any related data.
     *
     * @param \stdClass $data the data submitted from the reset course.
     * @return array status array
     */
    public static function reset_userdata(\stdClass $data): array {
        $componentstr = get_string('modulenameplural', 'customcert');
        $status = [];

        if (!empty($data->reset_customcert)) {
            (new issue_repository())->delete_by_course((int)$data->courseid);
            $status[] = ['component' => $componentstr, 'item' => get_string('deleteissuedcertificates', 'customcert'),
                'error' => false];
        }

        return $status;
    }

    /**
     * Implementation of the function for printing the form elements that control
     * whether the course reset functionality affects the customcert.
     *
     * @param \mod_customcert_mod_form $mform form passed by reference
     */
    public static function reset_course_form_definition(&$mform): void {
        $mform->addElement('header', 'customcertheader', get_string('modulenameplural', 'customcert'));
        $mform->addElement('advcheckbox', 'reset_customcert', get_string('deleteissuedcertificates', 'customcert'));
    }

    /**
     * Course reset form defaults.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function reset_course_form_defaults(\stdClass $course): array {
        return ['reset_customcert' => 1];
    }

    /**
     * Returns information about received customcert.
     * Used for user activity reports.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @param \stdClass $mod
     * @param \stdClass $customcert
     * @return \stdClass the user outline object
     */
    public static function user_outline(\stdClass $course, \stdClass $user, \stdClass $mod, \stdClass $customcert): \stdClass {
        $result = new \stdClass();
        if ($issue = (new issue_repository())->find_by_user_certificate((int)$customcert->id, (int)$user->id)) {
            $result->info = get_string('receiveddate', 'customcert');
            $result->time = $issue->timecreated;
        } else {
            $result->info = get_string('notissued', 'customcert');
        }

        return $result;
    }

    /**
     * Returns information about received customcert.
     * Used for user activity reports.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @param \stdClass $mod
     * @param \stdClass $customcert
     */
    public static function user_complete(\stdClass $course, \stdClass $user, \stdClass $mod, \stdClass $customcert): void {
        global $OUTPUT;
        if ($issue = (new issue_repository())->find_by_user_certificate((int)$customcert->id, (int)$user->id)) {
            echo $OUTPUT->box_start();
            echo get_string('receiveddate', 'customcert') . ": ";
            echo userdate($issue->timecreated);
            echo $OUTPUT->box_end();
        } else {
            print_string('notissued', 'customcert');
        }
    }
}
