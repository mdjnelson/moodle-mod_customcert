<?php

namespace customcertelement_coursename;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $coursenamedisplay = $data['coursenamedisplay'];

        if (
            $coursenamedisplay == element::COURSE_SHORT_NAME
            || $coursenamedisplay == element::COURSE_FULL_NAME
        ) {
            return $data;
        }

        $this->logger->warning("Course name display format not found");
        return [
            'coursenamedisplay' => element::COURSE_SHORT_NAME,
        ];
    }

    public function convert_for_import(array $data): ?string {
        return $data['coursenamedisplay'];
    }

    public function export(int $elementid, string $customdata): array {
        $arrtostore = [];
        $arrtostore['coursenamedisplay'] = $customdata;
        return $arrtostore;
    }
}
