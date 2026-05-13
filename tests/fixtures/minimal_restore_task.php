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
 * Minimal restore task fixture for tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use restore_customcert_activity_task;

/**
 * A lightweight restore task double with overridden getters and an in-memory mapping store.
 *
 * Avoids touching restore_dbops/temp tables during unit tests.
 */
final class minimal_restore_task extends restore_customcert_activity_task {
    /** @var string */
    private string $rid;

    /** @var int */
    private int $cid;

    /** @var int */
    private int $aid;

    /** @var array<string,array<int,int>> In-memory mapping store. */
    private array $map = [];

    /**
     * Constructor.
     *
     * @param string $rid Restore id.
     * @param int $cid Course id.
     * @param int $aid Activity id.
     */
    public function __construct(string $rid, int $cid, int $aid = 0) {
        $this->rid = $rid;
        $this->cid = $cid;
        $this->aid = $aid;
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function define_my_steps() {
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public static function define_decode_contents() {
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public static function define_decode_rules() {
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public static function define_restore_log_rules() {
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function get_activityid() {
        return $this->aid;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function get_restoreid() {
        return $this->rid;
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function get_courseid() {
        return $this->cid;
    }

    /**
     * Set a mapping without touching restore_dbops/temp tables.
     *
     * @param string $itemname Mapping item name (e.g., 'course_module', 'grade_item').
     * @param int $oldid Source id in backup.
     * @param int $newid Target id in site.
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
     * @param bool $ifnotfound
     * @return int|false
     */
    public function get_mappingid($itemname, $oldid, $ifnotfound = false) {
        return $this->map[$itemname][$oldid] ?? false;
    }
}
