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

namespace mod_customcert\export\datatypes;

use core\di;
use mod_customcert\export\contracts\i_template_appendix_manager;
use stored_file;

class file_field implements i_field, i_file_field {
    /**
     * @var i_template_appendix_manager Reference to the template appendix manager used for file lookup and identification.
     */
    private i_template_appendix_manager $filemng;

    /**
     * @var string $component Component of the file storage
     */
    private string $component;

    /**
     * Constructor.
     *
     * @param string $component Component of the file storage
     */
    public function __construct(
       string $component
    ) {
        $this->component = $component;
        $this->filemng = di::get(i_template_appendix_manager::class);
    }

    /**
     * Returns the file reference name
     *
     * @param array $data Associative array containing a 'file_ref' key
     * @return array The validated input value. (Local file reference)
     */
    public function import(array $data) {
        if (!$fileref = ($data['file_ref'] ?? null)) {
            throw new format_exception("File reference not set");
        }
        return $this->filemng->get_file_reference($fileref);
    }

    /**
     * Exports the file field as a structured array containing its reference ID.
     *
     * Uses internal file data to resolve and serialize a file identifier.
     *
     * @param mixed $value Field value containing file location data.
     * @return array Array containing the 'file_ref' identifier.
     */
    public function export($value): array {
        if (!$file = $this->get_file($value)) {
            return [];
        }

        return [
            'file_ref' => $this->filemng->get_identifier($file),
        ];
    }

    /**
     * Fallback value for file reference.
     *
     * @return array No fallback needed.
     */
    public function get_fallback() {
        return [];
    }

    /**
     * Retrieves the stored file instance associated with this element.
     *
     * @param array $data JSON-encoded data with file metadata.
     * @return stored_file|false The resolved image file or false if not found.
     */
    public function get_file(array $data): stored_file|false {
        $fs = get_file_storage();
        return $fs->get_file(
            (int) $data["contextid"],
            $this->component,
            $data["filearea"],
            (int) $data["itemid"],
            $data["filepath"],
            $data["filename"]
        );
    }
}
