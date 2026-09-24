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
 * Testable restore structure step fixture for tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use restore_customcert_activity_structure_step;
use stdClass;

/**
 * Exposes the real, unmodified process_customcert_element() for direct invocation, bypassing
 * the XML-driven execute() pipeline restore_controller would otherwise require.
 *
 * restore_controller instantiates the real task/step classes itself, so it cannot exercise a
 * plugin that is not actually installed (such as a tests/fixtures-only element). Only the base
 * restore_structure_step plumbing unrelated to element migration -- date offsetting
 * (apply_date_offset()) and old/new id mapping (get_new_parentid()/set_mapping()) -- is
 * overridden here with simple in-memory equivalents, so the step can be driven directly.
 */
final class testable_restore_customcert_element_step extends restore_customcert_activity_structure_step {
    /** @var array<string,mixed> Latest new id mapped per itemname, mirroring the parent's own semantics. */
    private array $latestnewid = [];

    /**
     * In-memory only: avoids requiring a real restore_dbops-backed backup_ids_temp table.
     *
     * @param string $itemname
     * @param int $oldid
     * @param int $newid
     * @param bool $restorefiles
     * @param int|null $filesctxid
     * @param int|null $parentid
     * @return void
     */
    public function set_mapping($itemname, $oldid, $newid, $restorefiles = false, $filesctxid = null, $parentid = null) {
        $this->latestnewid[$itemname] = $newid;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $itemname
     * @return mixed
     */
    public function get_new_parentid($itemname) {
        return $this->latestnewid[$itemname] ?? null;
    }

    /**
     * No-op: date offsetting is unrelated to element data migration, and the real
     * implementation requires a fully wired restore_plan.
     *
     * @param int $value
     * @return int
     */
    public function apply_date_offset($value) {
        return $value;
    }

    /**
     * Seed the mapping a real page-processing step would have already produced by the time
     * process_customcert_element() runs, so $data->pageid resolves correctly.
     *
     * @param int $oldpageid
     * @param int $newpageid
     * @return void
     */
    public function seed_page_mapping(int $oldpageid, int $newpageid): void {
        $this->latestnewid['customcert_page'] = $newpageid;
    }

    /**
     * Public wrapper invoking the real, unmodified protected process_customcert_element().
     *
     * @param stdClass $data
     * @return void
     */
    public function invoke_process_customcert_element(stdClass $data): void {
        $this->process_customcert_element($data);
    }
}
