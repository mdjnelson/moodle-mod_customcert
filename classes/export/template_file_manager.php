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

namespace mod_customcert\export;

use coding_exception;
use mod_customcert\export\contracts\import_exception;
use mod_customcert\export\contracts\i_template_file_manager;
use mod_customcert\export\contracts\i_template_appendix_manager;
use zip_packer;

/**
 * Manages the export and import of custom certificate template files.
 *
 * This class handles the full process of creating a `.zip` file from a template,
 * including JSON data and appendix files, and supports importing the same structure
 * back into the system. It integrates with file and appendix managers to provide
 * complete backup and restore functionality.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_file_manager implements i_template_file_manager {
    /**
     * Constructor.
     *
     * @param i_template_appendix_manager $filemng The manager for appendix file operations.
     */
    public function __construct(
        private readonly i_template_appendix_manager $filemng
    ) {
    }

    /**
     * Exports a template and its files into a ZIP archive and initiates download.
     *
     * Encodes the template data as JSON, saves it to a temporary directory,
     * includes appendix files, and archives everything into a downloadable ZIP file.
     *
     * @param int $templateid The ID of the template to export.
     * @throws coding_exception If JSON encoding fails.
     */
    public function export(int $templateid): void {
        $jsondata = (new template())->export($templateid);

        $json = json_encode($jsondata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new coding_exception("Json error during export");
        }

        $tempdir = make_temp_directory('customcert_export/' . uniqid(more_entropy: true));
        file_put_contents($tempdir . '/template.json', $json);

        $this->filemng->export($templateid, $tempdir);

        $zipper = new zip_packer();
        $zipfile = "$tempdir/certificate-template-$templateid.zip";

        $files = [];
        foreach (glob("$tempdir/*") as $path) {
            $files[basename($path)] = $path;
        }

        $zipper->archive_to_pathname(
            $files,
            $zipfile
        );

        @ob_clean();
        send_temp_file($zipfile, "certificate-template-$templateid.zip");
    }

    /**
     * Imports a template and its files from a ZIP archive located in a temporary directory.
     *
     * Extracts the archive, validates its contents, loads the JSON structure,
     * and invokes the import logic for both template data and appendix files.
     *
     * @param int $contextid The context ID into which the template will be imported.
     * @param string $tempdir The directory containing the uploaded ZIP archive.
     * @throws import_exception If extraction fails or required files are missing.
     */
    public function import(int $contextid, string $tempdir): void {
        if (!$packer = get_file_packer()) {
            throw new import_exception('errorpackermissing', 'error', '', 'ZIP');
        }

        $unpackdir = "$tempdir/unzipped";
        check_dir_exists($unpackdir);

        if (!$packer->extract_to_pathname("$tempdir/import.zip", $unpackdir)) {
            throw new import_exception('errorunzippingfile', 'error');
        }

        $jsonpath = $unpackdir . '/template.json';
        if (!file_exists($jsonpath)) {
            throw new import_exception('filenotfound', 'error', '', 'template.json');
        }

        $this->filemng->import($contextid, $unpackdir);

        $json = file_get_contents($jsonpath);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new import_exception('Invalid template.json (not valid JSON)');
        }
        (new template())->import($contextid, $data);
    }
}
