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

use stored_file;

/**
 * Manages appendix files for custom certificate templates during import and export.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface i_template_appendix_manager {
    /**
     * Exports appendix files linked to a custom certificate template to a target directory.
     *
     * @param int $templateid The ID of the certificate template.
     * @param string $storepath Extracted zip path of template file.
     */
    public function export(int $templateid, string $storepath): void;

    /**
     * Imports appendix files for a template from a specified directory into the given context.
     *
     * @param int $contextid The context ID into which the appendix files will be imported.
     * @param string $importpath Main path of temp folder, which will be converted to a zip.
     */
    public function import(int $contextid, string $importpath): void;

    /**
     * Finds and returns a stored file based on a given identifier.
     *
     * @param mixed $identifier The unique identifier for the file.
     * @return stored_file|false The found file or false if not found.
     */
    public function find($identifier): stored_file|false;

    /**
     * Returns a unique identifier for a given stored file.
     *
     * @param stored_file $file The file to retrieve the identifier for.
     * @return string The identifier representing the file.
     */
    public function get_identifier(stored_file $file): string;
}
