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

namespace mod_customcert;

use advanced_testcase;
use context_system;
use mod_customcert\element\restorable_element_interface;
use mod_customcert\service\element_factory;
use restore_customcert_activity_task;

defined('MOODLE_INTERNAL') || die;

// Ensure the restore base classes and task class are loaded in PHPUnit context.
global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/customcert/backup/moodle2/restore_customcert_activity_task.class.php');

/**
 * Tests that after_restore_from_backup() preserves JSON and updates IDs.
 *
 * We don't spin a full backup/restore here; instead we simulate the mapping the
 * restore API provides and call the hook directly.
 *
 * @package    mod_customcert
 * @category   test
 * @coversNothing
 */
final class restore_hooks_json_test extends advanced_testcase {
    /**
     * Create minimal template/page and return page id.
     */
    private function create_template_and_page(): int {
        global $DB;
        $this->resetAfterTest();

        $template = (object) [
            'name' => 'Restore JSON Template',
            'contextid' => context_system::instance()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $template->id = (int)$DB->insert_record('customcert_templates', $template, true);

        $page = (object) [
            'templateid' => $template->id,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $page->id = (int)$DB->insert_record('customcert_pages', $page, true);

        $customcert = (object) [
            'course' => 0,
            'name' => 'Restore JSON Activity',
            'templateid' => $template->id,
            'intro' => '',
            'introformat' => 0,
            'requiredtime' => 0,
            'emailstudents' => 0,
            'emailteachers' => 0,
            'emailothers' => '',
            'savecert' => 0,
            'delivery' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('customcert', $customcert);

        return $page->id;
    }

    /**
     * Helper to insert an element with given type/name/json.
     *
     * @param int $pageid Page id
     * @param string $type Element type key
     * @param string $name Element name
     * @param array $json JSON payload to encode into data
     * @return int Inserted element id
     */
    private function insert_element(int $pageid, string $type, string $name, array $json): int {
        global $DB;
        $now = time();
        $record = (object) [
            'element' => $type,
            'pageid' => $pageid,
            'name' => $name,
            'data' => json_encode($json),
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 1,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return (int)$DB->insert_record('customcert_elements', $record, true);
    }

    /**
     * Build a minimal restore task double with the given restoreid and courseid.
     *
     * @param string $restoreid Restore id
     * @param int $courseid Course id
     * @return restore_customcert_activity_task A lightweight restore task double
     */
    private function make_restore_task(string $restoreid, int $courseid): restore_customcert_activity_task {
        // Anonymous subclass exposing constructorless instance with overridden getters.
        return new class ($restoreid, $courseid) extends restore_customcert_activity_task {
            /** @var string */
            private string $rid;
            /** @var int */
            private int $cid;
            /** @var array<string,array<int,int>> In-memory mapping store */
            private array $map = [];

            /** Constructor for the anonymous restore task.
             *
             * @param string $rid restore id
             * @param int $cid course id */
            public function __construct(string $rid, int $cid) {
                $this->rid = $rid;
                $this->cid = $cid;
            }

            /**
             * {inheritdoc}
             *
             * @return void
             */
            protected function define_my_settings() {
            }

            /**
             * {inheritdoc}
             *
             * @return void
             */
            protected function define_my_steps() {
            }

            /**
             * {inheritdoc}
             *
             * @return void
             */
            public static function define_decode_contents() {
            }

            /**
             * {inheritdoc}
             *
             * @return void
             */
            public static function define_decode_rules() {
            }

            /**
             * {inheritdoc}
             *
             * @return void
             */
            public static function define_restore_log_rules() {
            }

            /**
             * {inheritdoc}
             *
             * @return void
             */
            public function after_restore() {
            }

            /**
             * {inheritdoc}
             *
             * @return int
             */
            public function get_restoreid() {
                return $this->rid;
            }

            /**
             * {inheritdoc}
             *
             * @return int
             */
            public function get_courseid() {
                return $this->cid;
            }

            /**
             * Set a mapping without touching restore_dbops/temp tables.
             *
             * @param string $itemname Mapping item name (e.g., 'course_module', 'grade_item')
             * @param int $oldid Source id in backup
             * @param int $newid Target id in site
             * @return void
             */
            public function set_mapping(string $itemname, int $oldid, int $newid): void {
                if (!isset($this->map[$itemname])) {
                    $this->map[$itemname] = [];
                }
                $this->map[$itemname][$oldid] = $newid;
            }

            /**
             * Override to avoid restore_dbops. Return mapped id or false if not found.
             *
             * @param string $itemname
             * @param int $oldid
             * @return int|false
             */
            public function get_mappingid($itemname, $oldid) {
                return $this->map[$itemname][$oldid] ?? false;
            }
        };
    }

    public function test_date_restore_updates_json_and_keeps_valid_json(): void {
        global $DB;
        $pageid = $this->create_template_and_page();

        // Simulate a Date element pointing to old course_module id 123 (non-grade case).
        $elementid = $this->insert_element($pageid, 'date', 'Date', [
            'dateitem' => '456', // Will be treated as cm id in non-grade path.
            'dateformat' => '1',
        ]);

        // Instantiate element object and interface.
        $legacy = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $obj = element_factory::get_element_instance($legacy);
        $this->assertInstanceOf(restorable_element_interface::class, $obj);

        // Create a fake restore mapping: map old cm id 456 -> new 999.
        $restoreid = 'rid-' . uniqid();
        $task = $this->make_restore_task($restoreid, 0);
        // Provide mapping without using restore_dbops/temp tables.
        $task->set_mapping('course_module', 456, 999);

        // Call the hook.
        $obj->after_restore_from_backup($task);

        // Assert data remains JSON and the id changed to 999.
        $row = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $this->assertIsString($row->data);
        $decoded = json_decode($row->data, true);
        $this->assertIsArray($decoded);
        $this->assertSame('999', (string)$decoded['dateitem']);
        $this->assertArrayHasKey('dateformat', $decoded);
    }

    public function test_grade_restore_updates_json_with_gradeitem_prefix(): void {
        global $DB;
        $pageid = $this->create_template_and_page();

        // Simulate a Grade element pointing to old grade_item id 321.
        $elementid = $this->insert_element($pageid, 'grade', 'Grade', [
            'gradeitem' => 'gradeitem:321',
            'gradeformat' => '1',
        ]);

        $legacy = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $obj = element_factory::get_element_instance($legacy);
        $this->assertInstanceOf(restorable_element_interface::class, $obj);

        $restoreid = 'rid-' . uniqid();
        $task = $this->make_restore_task($restoreid, 0);
        // Map old grade_item 321 -> 777 without touching temp tables.
        $task->set_mapping('grade_item', 321, 777);

        $obj->after_restore_from_backup($task);

        $row = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $decoded = json_decode($row->data, true);
        $this->assertIsArray($decoded);
        $this->assertSame('gradeitem:777', (string)$decoded['gradeitem']);
        $this->assertArrayHasKey('gradeformat', $decoded);
    }
}
