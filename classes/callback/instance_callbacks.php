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
 * Instance lifecycle callback implementations.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\callback;

use mod_customcert\event\issue_deleted;
use mod_customcert\service\form_service;
use mod_customcert\service\issue_repository;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;
use mod_customcert\template;

/**
 * Handles add/update/delete lifecycle callbacks for the customcert activity instance.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instance_callbacks {
    /**
     * Add customcert instance.
     *
     * @param \stdClass $data
     * @param \mod_customcert_mod_form $mform
     * @return int new customcert instance id
     */
    public static function add_instance(\stdClass $data, $mform): int {
        global $DB;

        // Create a template for this customcert to use.
        $context = \context_module::instance($data->coursemodule);
        $template = template::create($data->name, $context->id);

        // Add the data to the DB.
        $data->templateid = $template->get_id();
        $data->protection = form_service::set_protection($data);
        $data->timecreated = time();
        $data->timemodified = $data->timecreated;
        $data->id = $DB->insert_record('customcert', $data);

        // Add a page to this customcert.
        $service = template_service::create();
        $service->add_page($template, false);

        return $data->id;
    }

    /**
     * Update customcert instance.
     *
     * @param \stdClass $data
     * @param \mod_customcert_mod_form $mform
     * @return bool true
     */
    public static function update_instance(\stdClass $data, $mform): bool {
        global $DB;

        $data->protection = form_service::set_protection($data);
        $data->timemodified = time();
        $data->id = $data->instance;

        return $DB->update_record('customcert', $data);
    }

    /**
     * Given an ID of an instance of this module,
     * this function will permanently delete the instance
     * and any data that depends on it.
     *
     * @param int $id
     * @return bool true if successful
     */
    public static function delete_instance(int $id): bool {
        global $DB;

        // Ensure the customcert exists.
        if (!$customcert = $DB->get_record('customcert', ['id' => $id])) {
            return false;
        }

        // Get the course module as it is used when deleting files.
        if (!$cm = get_coursemodule_from_instance('customcert', $id)) {
            return false;
        }

        $context = \context_module::instance($cm->id);

        // Trigger issue_deleted events for each issue.
        $issuerepo = new issue_repository();
        $issues = $issuerepo->list_by_certificate($id);
        foreach ($issues as $issue) {
            $event = issue_deleted::create([
                'objectid' => $issue->id,
                'context' => $context,
                'relateduserid' => $issue->userid,
            ]);
            $event->trigger();
        }
        // Delete the customcert issues.
        $issuerepo->delete_by_certificate($id);

        // Now, delete the template associated with this certificate.
        if (($templaterecord = (new template_repository())->get_by_id((int)$customcert->templateid)) !== null) {
            $templateservice = template_service::create();
            $templateservice->delete(template::from_record($templaterecord));
        }

        // Delete the customcert instance.
        if (!$DB->delete_records('customcert', ['id' => $id])) {
            return false;
        }

        // Delete any files associated with the customcert.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id);

        return true;
    }
}
