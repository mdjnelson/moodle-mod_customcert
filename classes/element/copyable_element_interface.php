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

namespace mod_customcert\element;

use stdClass;

/**
 * Interface for elements that require custom logic when copied between pages or templates.
 *
 * Implement this interface when an element needs to perform additional work during a copy
 * operation (e.g. copying associated files to a new context). The repository/service layer
 * calls copy_from() after inserting the new element record.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface copyable_element_interface {
    /**
     * Perform any additional work required when this element is copied from a source record.
     *
     * @param stdClass $source The original element DB record being copied from.
     * @return bool True on success, false if the copy should be aborted and the new record deleted.
     */
    public function copy_from(stdClass $source): bool;
}
