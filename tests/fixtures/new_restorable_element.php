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
use mod_customcert\element\restorable_element_interface;
use mod_customcert\service\element_renderer;
use pdf;
use restore_customcert_activity_task;
use stdClass;

/**
 * Test fixture: element that implements restorable_element_interface directly.
 *
 * Used to verify the adapter delegates to after_restore_from_backup() without
 * emitting a deprecation notice when the inner element uses the new interface.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class new_restorable_element extends element implements restorable_element_interface {
    /**
     * Flag to track if after_restore_from_backup was called.
     *
     * @var bool
     */
    public bool $called = false;

    /**
     * New-style restore hook.
     *
     * @param restore_customcert_activity_task $restore
     * @return void
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        $this->called = true;
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
