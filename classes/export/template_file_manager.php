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

namespace mod_customcert\export;

use coding_exception;
use mod_customcert\export\import_exception;
use mod_customcert\export\template_file_manager_interface;
use mod_customcert\export\template_appendix_manager_interface;
use mod_customcert\export\template;
use Throwable;

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
class template_file_manager implements template_file_manager_interface {
    /** @var int Maximum number of files allowed in an imported archive. */
    const MAX_ARCHIVE_FILES = 500;

    /** @var int Maximum size in bytes for a single file in an imported archive (50 MB). */
    const MAX_FILE_BYTES = 50 * 1024 * 1024;

    /** @var int Maximum total size in bytes for all files in an imported archive (200 MB). */
    const MAX_TOTAL_BYTES = 200 * 1024 * 1024;

    /** @var template_appendix_manager_interface The manager for appendix file operations. */
    private readonly template_appendix_manager_interface $filemng;

    /** @var template Template handler for import/export operations. */
    private readonly template $template;

    /**
     * Constructor.
     *
     * @param template_appendix_manager_interface $filemng The manager for appendix file operations.
     * @param template $template Template handler for import/export operations.
     */
    public function __construct(
        template_appendix_manager_interface $filemng,
        template $template,
    ) {
        $this->filemng = $filemng;
        $this->template = $template;
    }

    /**
     * Exports a template and its files into a ZIP archive and initiates download.
     *
     * Encodes the template data as JSON, saves it to a temporary directory,
     * includes appendix files, and archives everything into a downloadable ZIP file.
     *
     * @param int $templateid The ID of the template to export.
     * @return string The exported file path
     * @throws coding_exception If JSON encoding fails.
     */
    public function export(int $templateid): string {
        $jsondata = $this->template->export($templateid);

        $json = json_encode($jsondata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new coding_exception("Json error during export");
        }

        $tempdir = make_temp_directory('customcert_export/' . uniqid(more_entropy: true));
        file_put_contents($tempdir . '/template.json', $json);

        $this->filemng->export($templateid, $tempdir);

        if (!$packer = get_file_packer()) {
            throw new import_exception('ZIP packer is not available');
        }
        $zipfile = "$tempdir/certificate-template-$templateid.zip";

        $files = [];
        foreach (glob("$tempdir/*") as $path) {
            $files[basename($path)] = $path;
        }

        $packer->archive_to_pathname(
            $files,
            $zipfile
        );
        return $zipfile;
    }

    /**
     * Imports a template and its files from a ZIP archive located in a temporary directory.
     *
     * Extracts the archive, validates its contents, loads the JSON structure,
     * and invokes the import logic for both template data and appendix files.
     *
     * The import is atomic: if template data import fails after files have already been
     * stored, all stored files are deleted before the exception is re-thrown.
     *
     * Note: all imported files are normalised to component=mod_customcert, filearea=image,
     * itemid=0, filepath=/ regardless of the metadata recorded in the export manifest.
     * This matches the runtime storage model used by customcert image elements and is
     * intentional. Round-trips for other file-backed element types are not supported.
     *
     * @param int $contextid The context ID into which the template will be imported.
     * @param string $tempdir The directory containing the uploaded ZIP archive.
     * @throws import_exception If extraction fails, required files are missing, or ZIP
     *                          content fails validation.
     */
    public function import(int $contextid, string $tempdir): void {
        if (!$packer = get_file_packer()) {
            throw new import_exception('ZIP packer is not available');
        }

        $zippath = "$tempdir/import.zip";
        $this->validate_zip($zippath);

        $unpackdir = "$tempdir/unzipped";
        check_dir_exists($unpackdir);

        if (!$packer->extract_to_pathname($zippath, $unpackdir)) {
            throw new import_exception('Failed to extract the ZIP archive');
        }

        $jsonpath = $unpackdir . '/template.json';
        if (!file_exists($jsonpath)) {
            throw new import_exception('template.json not found in archive');
        }

        $json = file_get_contents($jsonpath);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new import_exception('Invalid template.json (not valid JSON)');
        }

        // Import files first, then template data inside a DB transaction.
        // If the DB import fails, roll back stored files to keep the system clean.
        $this->filemng->import($contextid, $unpackdir);
        try {
            $this->template->import($contextid, $data);
        } catch (Throwable $e) {
            $this->filemng->delete_imported_files();
            throw $e;
        }
    }

    /**
     * Validates the ZIP archive before extraction.
     *
     * Checks that the archive can be listed, contains an acceptable number of entries,
     * that no single entry exceeds the maximum allowed size, and that all entry names
     * consist only of safe characters.
     *
     * @param string $zippath Full path to the ZIP file.
     * @throws import_exception If any validation check fails.
     */
    private function validate_zip(string $zippath): void {
        if (!$packer = get_file_packer()) {
            throw new import_exception('ZIP packer is not available');
        }

        $files = $packer->list_files($zippath);
        if ($files === false) {
            throw new import_exception('Failed to read the ZIP archive');
        }

        // Limit archive entry count to guard against zip bombs.
        $maxfiles = self::MAX_ARCHIVE_FILES;
        if (count($files) > $maxfiles) {
            throw new import_exception('Too many files in archive (max ' . $maxfiles . ')');
        }

        // 50 MB per-entry size limit; 200 MB cumulative uncompressed size limit.
        $maxbytes = self::MAX_FILE_BYTES;
        $maxtotalbytes = self::MAX_TOTAL_BYTES;
        $totalbytes = 0;
        foreach ($files as $file) {
            // Reject absolute paths and any path segment that is or contains '..'.
            if (str_starts_with($file->pathname, '/')) {
                throw new import_exception('Archive contains an absolute path: ' . $file->pathname);
            }
            foreach (explode('/', $file->pathname) as $segment) {
                if ($segment === '..' || $segment === '.') {
                    throw new import_exception('Archive contains a path traversal entry: ' . $file->pathname);
                }
            }
            // Only allow safe filenames: alphanumeric, dash, underscore, dot, slash.
            if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $file->pathname)) {
                throw new import_exception('Archive contains an unsafe filename: ' . $file->pathname);
            }
            if ($file->size > $maxbytes) {
                throw new import_exception('A file in the archive exceeds the size limit: ' . $file->pathname);
            }
            $totalbytes += $file->size;
            if ($totalbytes > $maxtotalbytes) {
                throw new import_exception('Archive total uncompressed size exceeds the allowed limit');
            }
        }
    }
}
