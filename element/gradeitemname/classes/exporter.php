<?php

namespace customcertelement_gradeitemname;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function convert_for_import(array $data): ?string {
        $this->logger->info("There is a grade reference, that will not be imported");
        return '';
    }

    public function export(int $elementid, string $customdata): array {
        return [];
    }
}
