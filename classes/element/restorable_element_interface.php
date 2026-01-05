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
 * Restore lifecycle hook for element implementations.
 *
 * Elements that store references to other records (e.g., course modules or files)
 * can implement this interface to adjust those references after a backup is
 * restored into a new site or course.
 *
 * This is the v2 replacement for the legacy method on the base element class.
 * See {@see mod_customcert\element\restorable_element_interface::after_restore_from_backup()}.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

use restore_customcert_activity_task;

/**
 * Contract for elements that need to adjust data during restore.
 *
 * Provides a hook for backup/restore processes to adjust stored references.
 */
interface restorable_element_interface {
    /**
     * Handle restore-time adjustments for this element.
     *
     * @param restore_customcert_activity_task $restore
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void;
}
