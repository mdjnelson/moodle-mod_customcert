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

namespace customcertelement_bgimage;

use core\di;
use Exception;
use mod_customcert\export\contracts\i_template_appendix_manager;
use mod_customcert\export\contracts\subplugin_exportable;
use stored_file;

class exporter extends subplugin_exportable {
    private i_template_appendix_manager $filemng;
    protected $dbtable = 'customcert_elements';

    public function __construct() {
        $this->filemng = di::get(i_template_appendix_manager::class);
        parent::__construct();
    }

    public function validate(array $data): array|false {
        $file = $this->filemng->find($data['imageref']);
        if (!$file) {
            $this->logger->warning("Image with ref " . $data['imageref'] . " not found");
            return false;
        }

        return $data;
    }

    public function convert_for_import(array $data): ?string {
        $file = $this->filemng->find($data['imageref']);

        $arrtostore = [
            'width' => $data['width'],
            'height' => $data['height'],
            'alphachannel' => $data['alphachannel'],
            'contextid' => $file->get_contextid(),
            'filearea' => $file->get_filearea(),
            'itemid' => $file->get_itemid(),
            'filepath' => $file->get_filepath(),
            'filename' => $file->get_filename(),
        ];
        return json_encode($arrtostore);
    }

    public function export(int $elementid, string $customdata): array {
        $data = json_decode($customdata);

        $file = $this->get_image($elementid, $customdata);
        $fileid = $this->filemng->get_identifier($file);

        $arrtostore = [
            'width' => $data->width,
            'height' => $data->height,
            'alphachannel' => $data->alphachannel ?? null,
            'imageref' => $fileid,
        ];
        return $arrtostore;
    }

    private function get_image(int $id, string $customdata): stored_file {
        $imagedata = json_decode($customdata);

        $fs = get_file_storage();

        if ($file = $fs->get_file(
            (int) $imagedata->contextid,
            'mod_customcert',
            $imagedata->filearea,
            (int) $imagedata->itemid,
            $imagedata->filepath,
            $imagedata->filename
        )) {
            return $file;
        }

        throw new Exception("File not found");
    }

    public function get_used_files(int $id, string $customdata): array {
        $coursefile = $this->get_image($id, $customdata);

        if (!$coursefile) {
            return [];
        }

        return [$coursefile];
    }
}
