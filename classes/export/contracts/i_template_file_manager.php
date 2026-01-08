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
 * Handles the import and export of custom certificate templates.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface i_template_file_manager {
    /**
     * Exports as a zip file contains infos about a custom certificate template.
     *
     * Call this export function to download the zip.
     *
     * @param int $templateid The ID of the certificate template to export.
     */
    public function export(int $templateid): void;

    /**
     * Imports certificate template files into a context from a temporary directory.
     *
     * @param int $contextid The context ID where the template files will be imported.
     * @param string $tempdir The path to the temporary directory containing the template file.
     *                        Must be a zip named import.zip
     */
    public function import(int $contextid, string $tempdir): void;
}
