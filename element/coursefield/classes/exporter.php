<?php

namespace customcertelement_coursefield;

use core_course\customfield\course_handler;
use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $customfieldname = $data['customfieldname'];

        if (in_array($customfieldname,  ['fullname', 'shortname', 'idnumber'])) {
            return $data;
        }

        // Get the course custom fields.
        $handler = course_handler::create();
        $customfields = $handler->get_fields();
        $arrcustomfields = array_map(fn ($field) => $field->get('id'), $customfields);

        if (in_array($customfieldname, $arrcustomfields)) {
            return $data;
        }

        // Course field not found, do not create element.
        $this->logger->warning("Course field $customfieldname not found");
        return false;
    }

    public function convert_for_import(array $data): ?string {
        return $data['customfieldname'];
    }

    public function export(int $elementid, string $customdata): array {
        $arrtosave = [];
        $arrtosave['customfieldname'] = $customdata;
        return $arrtosave;
    }
}
