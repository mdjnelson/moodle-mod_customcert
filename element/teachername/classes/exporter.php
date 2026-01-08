<?php

namespace customcertelement_teachername;

use core\di;
use mod_customcert\export\contracts\subplugin_exportable;
use moodle_database;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        if (empty($data)) {
            return [];
        }

        $userid = $data['userid'];
        $db = di::get(moodle_database::class);
        $teacher = $db->get_record('user', ['id' => $userid]);

        if (!$teacher) {
            $this->logger->info("Teacher name: Teacher with $userid does not exist");
            return [];
        }

        $issame = fullname($teacher) == $data['fullname'];
        if (!$issame) {
            $this->logger->info("Teacher name: Teacher with $userid is not the same as in backup.");
            return [];
        }

        return $data;
    }

    public function convert_for_import(array $data): ?string {
        if (empty($data)) {
            return null;
        }

        return $data['userid'];
    }

    public function export(int $elementid, string $customdata): array {
        $db = di::get(moodle_database::class);
        $teacher = $db->get_record('user', ['id' => intval($customdata)]);

        if (!$teacher) {
            return [];
        }

        return [
            'userid' => $customdata,
            'fullname' => $teacher->fullname,
        ];
    }
}
