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
use Exception;
use mod_customcert\export\template_appendix_manager_interface;
use stored_file;
use mod_customcert\export\element;

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
class template_appendix_manager implements template_appendix_manager_interface {
    /** @var element|null Element handler used for file lookups. */
    private ?element $element = null;

    /**
     * Sets the element handler used for file lookups during export.
     *
     * @param element $element Element handler.
     */
    public function set_element(element $element): void {
        $this->element = $element;
    }

    /** @var array<string, stored_file> In-memory index of imported files, mapped by content hash. */
    private array $index = [];

    /** @var array<string, stored_file> Tracks only newly created files for rollback purposes. */
    private array $created = [];

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
        // Flatten page-level arrays; guard against templates with no pages or no elements.
        $elementids = $elementids ? array_merge(...$elementids) : [];
        if (empty($elementids)) {
            return [];
        }
        if ($this->element === null) {
            throw new coding_exception('Element handler not set. Call set_element() before exporting.');
        }
        $element = $this->element;
        $files = array_map(fn ($elementid) => $element->get_files($elementid), $elementids);
        return $files ? array_merge(...$files) : [];
    }

    /**
     * Imports appendix files into the Moodle file storage based on the manifest.
     *
     * Verifies file existence, deduplicates where possible, and stores new files
     * under the appropriate context and path. Only newly created files are tracked
     * for rollback; pre-existing files that are reused are never deleted on failure.
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
            $this->created = [];
            return;
        }

        $raw = (string)file_get_contents($manifestpath);
        $manifest = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($manifest)) {
            throw new \mod_customcert\export\import_exception(
                'files.json is malformed or not a valid JSON object/array'
            );
        }
        // The "files" key must be present and be an array (empty is allowed).
        if (!array_key_exists('files', $manifest)) {
            throw new import_exception('files.json is missing the required "files" key');
        }
        if (!is_array($manifest['files'])) {
            throw new \mod_customcert\export\import_exception(
                'files.json is missing a valid "files" array'
            );
        }
        if (empty($manifest['files'])) {
            // Valid empty manifest — no files to import.
            $this->index = [];
            $this->created = [];
            return;
        }

        $fs = get_file_storage();

        foreach ($manifest['files'] as $contenthash => $meta) {
            if (!is_array($meta)) {
                throw new import_exception("invalid file metadata: files/$contenthash");
            }
            if (!isset($meta['filename']) || !is_string($meta['filename']) || $meta['filename'] === '') {
                throw new import_exception("file has no name: files/$contenthash");
            }
            if (!isset($meta['component']) || !is_string($meta['component'])) {
                throw new import_exception("invalid or missing file component: files/$contenthash");
            }
            if (isset($meta['filearea']) && !is_string($meta['filearea'])) {
                throw new import_exception("invalid file area type: files/$contenthash");
            }
            if (isset($meta['filepath']) && !is_string($meta['filepath'])) {
                throw new import_exception("invalid file path type: files/$contenthash");
            }
            // Validate the filename from the manifest strictly: reject any name that
            // clean_param would mutate (e.g. path traversal sequences) rather than
            // silently accepting a normalised version. This ensures import integrity.
            $rawfilename = $meta['filename'];
            $filename = clean_param($rawfilename, PARAM_FILE);
            if ($filename === '' || $filename !== $rawfilename) {
                throw new import_exception("file has unsafe or empty name: files/$contenthash");
            }

            // Validate the content hash key before using it in a file path.
            if (!preg_match('/^[a-f0-9]{40}$/', $contenthash)) {
                throw new import_exception("invalid content hash key in manifest: $contenthash");
            }
            // Allowlist component, filearea, filepath, and itemid to prevent hostile imports
            // from creating arbitrary files in unrelated fileareas or components.
            $component = $meta['component'];
            if ($component !== 'mod_customcert') {
                throw new import_exception("Invalid file component in files.json: files/$contenthash");
            }
            $allowedareas = ['image'];
            if (!in_array($meta['filearea'] ?? '', $allowedareas, true)) {
                throw new import_exception("Invalid file area in files.json: files/$contenthash");
            }
            if (($meta['filepath'] ?? '/') !== '/') {
                throw new import_exception("Invalid file path in files.json: files/$contenthash");
            }
            $itemid = $meta['itemid'] ?? 0;
            if (!is_numeric($itemid) || (int) $itemid < 0) {
                throw new import_exception("Invalid file itemid in files.json: files/$contenthash");
            }
            $srcpath = $this->get_imagepath($importpath, $contenthash);
            if (!file_exists($srcpath)) {
                throw new import_exception("file not found: files/$contenthash");
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

            // Avoid duplicates: reuse only if the existing file has the same content hash.
            // Matching by pathname alone is not sufficient — a file with the same name but
            // different content would silently produce the wrong result on round-trip.
            $existing = $fs->get_file(
                $filerecord['contextid'],
                $filerecord['component'],
                $filerecord['filearea'],
                $filerecord['itemid'],
                $filerecord['filepath'],
                $filerecord['filename']
            );

            if ($existing && $existing->get_contenthash() === $contenthash) {
                $this->index[$contenthash] = $existing;
                continue;
            }

            // A file with the same name but different content exists. Do not delete it —
            // it may be referenced by another template or element and we do not own it.
            // Instead, import under a collision-free name derived from the content hash.
            if ($existing) {
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $base = pathinfo($filename, PATHINFO_FILENAME);
                $filerecord['filename'] = $ext !== '' ? "$base-$contenthash.$ext" : "$base-$contenthash";
            }

            $stored = $fs->create_file_from_pathname($filerecord, $srcpath);
            // Verify the stored file's content hash matches the manifest key.
            // This catches truncated or corrupted archive members before they
            // are silently used as the wrong file.
            if ($stored->get_contenthash() !== $contenthash) {
                $stored->delete();
                throw new import_exception(
                    "content hash mismatch for files/$contenthash: " .
                    "expected $contenthash, got " . $stored->get_contenthash()
                );
            }
            $this->index[$contenthash] = $stored;
            $this->created[$contenthash] = $stored;
        }
    }

    /**
     * Deletes only the files that were newly created during the current import operation.
     *
     * Called on rollback to clean up any files stored before a later failure.
     * Pre-existing files that were reused are never deleted.
     */
    public function delete_imported_files(): void {
        foreach ($this->created as $file) {
            $file->delete();
        }
        $this->index = [];
        $this->created = [];
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
     * @return array Data array to reference the found file, empty if not found.
     */
    public function get_file_reference(string $identifier): array {
        if (!$file = $this->find($identifier)) {
            return [];
        }

        return [
            "contextid" => $file->get_contextid(),
            "filearea" => $file->get_filearea(),
            "itemid" => $file->get_itemid(),
            "filepath" => $file->get_filepath(),
            "filename" => $file->get_filename(),
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
    private function get_imagepath(string $tempdir, string $imagename): string {
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
