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
 * Define all the restore steps that will be used by the restore_customcert_activity_task.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_customcert\service\element_factory;
use mod_customcert\element\restorable_element_interface;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

global $CFG;
require_once($CFG->dirroot . '/mod/customcert/backup/moodle2/restore_customcert_stepslib.php');

/**
 * The class definition for assigning tasks that provide the settings and steps to perform a restore of the activity.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_customcert_activity_task extends restore_activity_task {
    /**
     * @var element_factory|null Element factory for constructing element instances.
     */
    private ?element_factory $factory = null;

    /**
     * Build or return the shared element factory for this restore task.
     *
     * @return element_factory
     */
    protected function get_element_factory(): element_factory {
        if ($this->factory === null) {
            $this->factory = element_factory::build_with_defaults();
        }

        return $this->factory;
    }

    /**
     * Define  particular settings this activity can have.
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define particular steps this activity can have.
     */
    protected function define_my_steps() {
        // The customcert only has one structure step.
        $this->add_step(new restore_customcert_activity_structure_step('customcert_structure', 'customcert.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('customcert', ['intro'], 'customcert');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder.
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('CUSTOMCERTVIEWBYID', '/mod/customcert/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('CUSTOMCERTINDEX', '/mod/customcert/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied by the {@see restore_logs_processor} when restoring
     * customcert logs. It must return one array of {@see restore_log_rule} objects.
     *
     * @return array the restore log rules
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('customcert', 'add', 'view.php?id={course_module}', '{customcert}');
        $rules[] = new restore_log_rule('customcert', 'update', 'view.php?id={course_module}', '{customcert}');
        $rules[] = new restore_log_rule('customcert', 'view', 'view.php?id={course_module}', '{customcert}');
        $rules[] = new restore_log_rule('customcert', 'received', 'view.php?id={course_module}', '{customcert}');
        $rules[] = new restore_log_rule('customcert', 'view report', 'view.php?id={course_module}', '{customcert}');

        return $rules;
    }

    /**
     * This function is called after all the activities in the backup have been restored. This allows us to get
     * the new course module ids, as they may have been restored after the customcert module, meaning no id
     * was available at the time.
     */
    public function after_restore() {
        global $DB;

        // Get the customcert elements.
        $sql = "SELECT e.*
                  FROM {customcert_elements} e
            INNER JOIN {customcert_pages} p
                    ON e.pageid = p.id
            INNER JOIN {customcert} c
                    ON p.templateid = c.templateid
                 WHERE c.id = :customcertid";
        if ($elements = $DB->get_records_sql($sql, ['customcertid' => $this->get_activityid()])) {
            // Go through the elements for the certificate.
            $factory = $this->get_element_factory();
            foreach ($elements as $element) {
                // Get an instance of the element class.
                if ($instance = $factory->create_from_legacy_record($element)) {
                    // Prefer the new typed restore hook when implemented; otherwise fall back to legacy method.
                    if ($instance instanceof restorable_element_interface) {
                        $instance->after_restore_from_backup($this);
                    } else if (method_exists($instance, 'after_restore')) {
                        $instance->after_restore($this);
                    }
                }
            }
        }
    }
}
