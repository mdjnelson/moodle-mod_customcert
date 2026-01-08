<?php

namespace customcertelement_text;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function convert_for_import(array $data): ?string {
        return null;
    }

    public function export(int $elementid, string $customdata): array {
        return [];
    }
}
