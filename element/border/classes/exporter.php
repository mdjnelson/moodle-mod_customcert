<?php

namespace customcertelement_border;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $width = $data['width'];

        if (intval($width) == 0) {
            $this->logger->warning("invalid border width");
            $width = 0;
        }

        return [
            'width' => (int) $width,
        ];
    }

    public function convert_for_import(array $data): ?string {
        return $data['width'];
    }

    public function export(int $elementid, string $customdata): array {
        $data = [];
        $data['width'] = $customdata;
        return $data;
    }
}
