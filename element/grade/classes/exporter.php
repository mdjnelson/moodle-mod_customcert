<?php

namespace customcertelement_grade;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $gradeformat = $data["gradeformat"];
        $gradeformats = element::get_grade_format_options();

        if (!array_key_exists($gradeformat, $gradeformats)) {
            $this->logger->warning("The grade format '{$gradeformat}' does not exist.");
            $gradeformat = array_keys($gradeformats)[0];
        }

        $this->logger->info("There is a grade reference, that will not be imported");
        return [
            "gradeformat" => $gradeformat,
        ];
    }

    public function convert_for_import(array $data): ?string {
        $arrtostore = [
            'gradeitem' => '0',
            'gradeformat' => $data["gradeformat"],
        ];
        return json_encode($arrtostore);
    }

    public function export(int $elementid, string $customdata): array {
        $decodeddata = json_decode($customdata);

        $arrtostore = [];
        $arrtostore['gradeformat'] = $decodeddata->gradeformat;
        return $arrtostore;
    }
}
