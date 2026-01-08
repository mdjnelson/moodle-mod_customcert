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

use core\di;
use stored_file;

/**
 * Provides a base structure for exportable custom certificate subplugins. *
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class subplugin_exportable {
    /**
     * @var i_template_import_logger Logger instance used for reporting import issues and notices.
     */
    protected i_template_import_logger $logger;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->logger = di::get(i_template_import_logger::class);
    }

    /**
     * Validates the provided data before import.
     *
     * @param array $data The data to validate.
     * @return array|false Returns the corrected data if recoverable
     *                     or false on validation failure.
     */
    public function validate(array $data): array|false {
        return $data;
    }

    /**
     * Converts raw import data to a string format suitable for subplugin storage.
     *
     * Will only be called if validation was successful.
     *
     * @param array $data The data to convert.
     * @return string|null Converted string.
     */
    abstract public function convert_for_import(array $data): ?string;

    /**
     * Exports subplugin data.
     *
     * The exported structure will later be passed to convert_for_import again.
     *
     * @param int $elementid The ID of the customcert element.
     * @param string $customdata Subplugin custom data.
     * @return array Exported data in array format.
     */
    abstract public function export(int $elementid, string $customdata): array;

    /**
     * Retrieves file references used by the subplugin element.
     *
     * @param int $id The ID of the element.
     * @param string $customdata Subplugin custom data.
     * @return stored_file[] Stored files, defaulting to an empty array.
     */
    public function get_used_files(int $id, string $customdata): array {
        return [];
    }
}
