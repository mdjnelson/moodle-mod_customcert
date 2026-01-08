<?php

namespace customcertelement_userfield;

use availability_profile\condition;
use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $userfield = $data['userfield'];

        if (!$this->validate_userfield($userfield)) {
            $this->logger->warning("User field $userfield does not exists");
            return false;
        }

        return $data;
    }

    public function convert_for_import(array $data): ?string {
        return $data['userfield'];
    }

    private function validate_userfield(string $userfield): bool {
        $basefields = [
            'firstname',
            'lastname',
            'email',
            'city',
            'country',
            'url',
            'idnumber',
            'institution',
            'department',
            'phone1',
            'phone2',
            'address',
        ];

        if (in_array($userfield, $basefields)) {
            return true;
        }

        $arrcustomfields = condition::get_custom_profile_fields();
        $customfields = array_map(fn ($field) => $field->id, $arrcustomfields);
        if (in_array($userfield, $customfields)) {
            return true;
        }

        return false;
    }

    public function export(int $elementid, string $customdata): array {
        $data = [];
        $data['userfield'] = $customdata;
        return $data;
    }
}
