<?php

namespace customcertelement_date;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/constants.php');

use mod_customcert\element_helper;
use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $dateitem = $data["dateitem"];
        $dateformat = $data["dateformat"];

        if (!$this->is_valid_dateitem($dateitem)) {
            $this->logger->warning("invalid dateitem $dateitem");
            $dateitem = null;
        }

        if (!$this->is_valid_dateformat($dateformat)) {
            $this->logger->warning("invalid dateformat $dateformat");
            $dateformat = null;
        }

        return [
            'dateitem' => $dateitem,
            'dateformat' => $dateformat,
        ];
    }

    public function convert_for_import(array $data): ?string {
        return json_encode($data);
    }

    private function is_valid_dateitem(string $dateitem): bool {
        $valid = [
            CUSTOMCERT_DATE_ISSUE,
            CUSTOMCERT_DATE_CURRENT_DATE,
            CUSTOMCERT_DATE_COMPLETION,
            CUSTOMCERT_DATE_ENROLMENT_START,
            CUSTOMCERT_DATE_ENROLMENT_END,
            CUSTOMCERT_DATE_COURSE_START,
            CUSTOMCERT_DATE_COURSE_END,
            CUSTOMCERT_DATE_COURSE_GRADE,
        ];
        // TODO check if item exists

        return in_array($dateitem, $valid);
    }

    private function is_valid_dateformat(string $dateformat): bool {
        return array_key_exists($dateformat, element_helper::get_date_formats());
    }

    public function export(int $elementid, string $customdata): array {
        return (array) json_decode($customdata);
    }
}
