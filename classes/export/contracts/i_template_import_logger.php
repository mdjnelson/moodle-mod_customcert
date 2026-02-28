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

namespace mod_customcert\export\contracts;

/**
 * Defines logging capabilities for custom certificate template import feature.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface i_template_import_logger {
    /**
     * Logs a warning message to the import process logger.
     *
     * @param string $message The warning message to log.
     */
    public function warning(string $message): void;

    /**
     * Logs an informational message during the import process.
     *
     * @param string $message The info message to log.
     */
    public function info(string $message): void;

    /**
     * Outputs or displays the collected log notifications.
     *
     * This method should be called after import operations to show relevant messages.
     */
    public function print_notification(): void;
}
