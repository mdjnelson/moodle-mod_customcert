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
use Exception;
use mod_customcert\export\contracts\i_template_appendix_manager;
use stored_file;

/**
 * Manages the export and import of appendix files for custom certificate templates.
 *
 * This class collects all files associated with a template, creates a manifest,
 * exports them to disk, and handles importing them back into Moodle’s file storage,
 * resolving and avoiding duplicates.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_appendix_manager implements i_template_appendix_manager {
    /** @var array<string, stored_file> In-memory index of imported files, mapped by content hash. */
    private array $index = [];

    /**
     * Exports appendix files linked to a certificate template and writes a manifest file.
     *
     * Copies all used files to a subdirectory and writes a JSON manifest describing them.
     *
     * @param int $templateid The ID of the template.
     * @param string $storepath The target path for storing files and the manifest.
     * @throws coding_exception If manifest JSON encoding fails.
     */
    public function export(int $templateid, string $storepath): void {
        $files = $this->get_files_from_template($templateid);
        $files = array_filter($files, fn ($file) => $file instanceof stored_file);

        // Save files.
        $targetdir = $this->get_filepath($storepath);
        check_dir_exists($targetdir);
        foreach ($files as $file) {
            $id = $this->get_identifier($file);
            $filepath = "$targetdir/$id";
            $file->copy_content_to($filepath);
        }

        // Create manifest.
        $manifest = [
            'version' => 1,
            'files' => [],
        ];

        foreach ($files as $file) {
            $id = $this->get_identifier($file);
            $manifest['files'][$id] = [
                'mimetype'  => $file->get_mimetype(),
                'filename'  => $file->get_filename(),
                'filepath'  => $file->get_filepath(),
                'itemid'    => $file->get_itemid(),
                'filearea'  => $file->get_filearea(),
                'component' => $file->get_component(),
            ];
        }
        $manifestpath = $this->get_manifest_path($storepath);
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new coding_exception('Failed to encode files manifest');
        }
        file_put_contents($manifestpath, $json);
    }

    /**
     * Gathers all files associated with the elements of a certificate template.
     *
     * Traverses pages and elements to collect their related files.
     *
     * @param int $templateid The ID of the template.
     * @return array An array of stored_file objects.
     */
    private function get_files_from_template(int $templateid): array {
        $pageids = page::get_pageids_from_template($templateid);
        $elementids = [];
        foreach ($pageids as $pageid) {
            $elementids[] = element::get_elementids_from_page($pageid);
        }
        $elementids = array_merge(...$elementids);
        $element = new element();

        $files = array_map(fn ($elementid) => $element->get_files($elementid), $elementids);
        return array_merge(...$files);
    }

    /**
     * Imports appendix files into the Moodle file storage based on the manifest.
     *
     * Verifies file existence, deduplicates where possible, and stores new files
     * under the appropriate context and path.
     *
     * @param int $contextid The context ID to import files into.
     * @param string $importpath Path to the extracted ZIP directory containing files and manifest.
     * @throws Exception If required files or metadata are missing or malformed.
     */
    public function import(int $contextid, string $importpath): void {
        $manifestpath = $this->get_manifest_path($importpath);

        if (!file_exists($manifestpath)) {
            // Allow "no files" exports.
            $this->index = [];
            return;
        }

        $manifest = json_decode((string)file_get_contents($manifestpath), true);
        if (!is_array($manifest) || empty($manifest['files']) || !is_array($manifest['files'])) {
            // Also allow empty.
            $this->index = [];
            return;
        }

        $fs = get_file_storage();

        foreach ($manifest['files'] as $contenthash => $meta) {
            if (empty($meta['filename'] ?? null)) {
                throw new Exception("file has no name: files/$contenthash");
            }
            $filename = $meta['filename'];

            $srcpath = $this->get_imagepath($importpath, $contenthash);
            if (!file_exists($srcpath)) {
                throw new Exception("file not found: files/$contenthash");
            }

            // Using mod_customcert/image/itemid=0 is compatible with your element runtime code.
            $filerecord = [
                'contextid' => $contextid,
                'component' => 'mod_customcert',
                'filearea'  => 'image',
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => $filename,
            ];

            // Avoid duplicates: if the exact pathname exists, reuse.
            $existing = $fs->get_file(
                $filerecord['contextid'],
                $filerecord['component'],
                $filerecord['filearea'],
                $filerecord['itemid'],
                $filerecord['filepath'],
                $filerecord['filename']
            );

            if ($existing) {
                $this->index[$contenthash] = $existing;
                continue;
            }

            $stored = $fs->create_file_from_pathname($filerecord, $srcpath);
            $this->index[$contenthash] = $stored;
        }
    }

    /**
     * Retrieves a previously imported file based on its identifier.
     *
     * @param string $identifier The content hash of the file.
     * @return stored_file|false The matched file or false if not found.
     */
    public function find(string $identifier): stored_file|false {
        return $this->index[$identifier] ?? false;
    }

    /**
     * Finds and returns a stored file based on a given identifier
     * and returns its reference data.
     *
     * @param string $identifier The unique identifier for the file.
     * @param string $filename Name put before each value, in the reference array.
     * @return array Data array to reference the found file, empty if not found.
     */
    public function get_file_reference(string $identifier, string $filename = ''): array {
        if (!$file = $this->find($identifier)) {
            return [];
        }

        return [
            "{$filename}contextid" => $file->get_contextid(),
            "{$filename}filearea" => $file->get_filearea(),
            "{$filename}itemid" => $file->get_itemid(),
            "{$filename}filepath" => $file->get_filepath(),
            "{$filename}filename" => $file->get_filename(),
        ];
    }

    /**
     * Returns a unique identifier (content hash) for a given file.
     *
     * @param stored_file $file The file to identify.
     * @return string The file's content hash.
     */
    public function get_identifier(stored_file $file): string {
        return $file->get_contenthash();
    }

    /**
     * Constructs the directory path where appendix files should be stored.
     *
     * @param string $tempdir The base temporary directory.
     * @return string Path to the 'files' subdirectory.
     */
    private function get_filepath(string $tempdir): string {
        return $tempdir . DIRECTORY_SEPARATOR . "files";
    }

    /**
     * Constructs the full path to a specific image file by name.
     *
     * @param string $tempdir The base temporary directory.
     * @param string $imagename The name (hash) of the image.
     * @return string Full file path.
     */
    private function get_imagepath(string $tempdir, string $imagename) {
        return $this->get_filepath($tempdir) . DIRECTORY_SEPARATOR . $imagename;
    }

    /**
     * Returns the full path to the JSON manifest file within a given directory.
     *
     * @param string $tempdir The base temporary directory.
     * @return string Manifest file path.
     */
    private function get_manifest_path(string $tempdir): string {
        return $tempdir . DIRECTORY_SEPARATOR . "files.json";
    }
}
