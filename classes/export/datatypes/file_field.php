<?php

namespace mod_customcert\classes\export\datatypes;

use core\di;
use mod_customcert\export\contracts\i_template_appendix_manager;
use stored_file;

class file_field implements i_field, i_file_field {
    /**
     * @var i_template_appendix_manager Reference to the template appendix manager used for file lookup and identification.
     */
    private i_template_appendix_manager $filemng;

    public function __construct(
       private string $component
    ) {
        $this->filemng = di::get(i_template_appendix_manager::class);
    }

    public function import(array $data) {
        return $this->filemng->get_file_reference($data['file_ref']);
    }

    public function get_file(array $data) {
        $file = $this->filemng->find($data['file_ref']);
        if (!$file) {
            throw new format_exception("File with ref " . $data['imageref'] . " not found");
        }
        return $file;
    }

    public function export($value): array {
        $file = $this->get_file_from_customdata($value);
        $fileid = $this->filemng->get_identifier($file);
        return [
            'file_ref' => $fileid,
        ];
    }

    /**
     * Retrieves the stored file instance associated with this element.
     *
     * @param array $imagedata JSON-encoded data with file metadata.
     * @return stored_file|false The resolved image file or false if not found.
     */
    protected function get_file_from_customdata(array $imagedata): stored_file|false {
        $fs = get_file_storage();
        return $fs->get_file(
            (int) $imagedata["contextid"],
            $this->component,
            $imagedata["filearea"],
            (int) $imagedata["itemid"],
            $imagedata["filepath"],
            $imagedata["filename"]
        );
    }
}
