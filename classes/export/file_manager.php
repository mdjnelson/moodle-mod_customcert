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
use mod_customcert\export\contracts\i_file_manager;
use stored_file;

class file_manager implements i_file_manager {
    /** @var array<string, stored_file> */
    private array $index = [];

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
                // contextid is intentionally NOT used on import; import writes into target context.
                // But keeping it can help debugging:
                'sourcecontextid' => $file->get_contextid(),
            ];
        }
        $manifestpath = $this->get_manifest_path($storepath);
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new coding_exception('Failed to encode files manifest');
        }
        file_put_contents($manifestpath, $json);
    }

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
                throw new \Exception("file has no name: files/$contenthash");
            }
            $filename = $meta['filename'];

            $srcpath = $this->get_imagepath($importpath, $contenthash);
            if (!file_exists($srcpath)) {
                throw new \Exception("file not found: files/$contenthash");
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

    public function find($identifier): stored_file|false {
        $identifier = (string)$identifier;
        return $this->index[$identifier] ?? false;
    }

    public function get_identifier(stored_file $file): string {
        return $file->get_contenthash();
    }

    private function get_filepath(string $tempdir): string {
        return $tempdir . DIRECTORY_SEPARATOR . "files";
    }

    private function get_imagepath(string $tempdir, string $imagename) {
        return $this->get_filepath($tempdir) . DIRECTORY_SEPARATOR . $imagename;
    }

    private function get_manifest_path(string $tempdir): string {
        return $tempdir . DIRECTORY_SEPARATOR . "manifest.json";
    }
}
