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
namespace mod_customcert\tests\fixtures;

use mod_customcert\element;
use mod_customcert\element\renderable_element_interface;
use MoodleQuickForm;
use mod_customcert\element\restorable_element_interface;
use mod_customcert\service\element_renderer;
use pdf;
use restore_customcert_activity_task;
use stdClass;

/**
 * Test fixture: third-party native v2 element that implements restorable_element_interface
 * directly, used to exercise the production restore task's dispatch of after_restore_from_backup().
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class new_restorable_element extends element implements renderable_element_interface, restorable_element_interface {
    /**
     * Add element-specific fields to the edit form (no-op in fixture).
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
    }

    /**
     * Flag to track if after_restore_from_backup was called.
     *
     * @var bool
     */
    public bool $called = false;

    /**
     * New-style restore hook.
     *
     * Mirrors the real bundled elements (e.g. customcertelement_date): remaps the stored
     * 'refid' course_module id via the restore task's mapping API and persists the updated
     * JSON payload back to the element record.
     *
     * @param restore_customcert_activity_task $restore
     * @return void
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        global $DB;

        $this->called = true;

        $data = $this->get_payload();
        if (empty($data) || empty($data['refid'])) {
            return;
        }

        $newid = $restore->get_mappingid('course_module', (int)$data['refid']);
        if ($newid) {
            $data['refid'] = $newid;
            $DB->set_field('customcert_elements', 'data', json_encode($data), ['id' => $this->get_id()]);
        }
    }

    /**
     * Render to PDF (not used in this test).
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @param element_renderer|null $renderer
     * @return void
     */
    public function render(
        pdf $pdf,
        bool $preview,
        stdClass $user,
        ?element_renderer $renderer = null
    ): void {
    }

    /**
     * Render HTML (not used in this test).
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }
}
