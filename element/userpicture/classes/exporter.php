<?php

namespace customcertelement_userpicture;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {


    public function convert_for_import(array $data): ?string {
        $arrtostore = [
            'width' => (int) $data["width"],
            'height' => (int) $data["height"],
        ];
        return json_encode($arrtostore);
    }

    public function export(int $elementid, string $customdata): array {
        return (array) json_decode($customdata);
    }
}
