<?php

namespace customcertelement_qrcode;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $width = $data['width'] ?? null;
        $height = $data['height'] ?? null;

        if (intval($width) == 0) {
            $this->logger->warning("invalid width");
            $width = 0;
        }

        if (intval($height) == 0) {
            $this->logger->warning("invalid width");
            $height = 0;
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    public function convert_for_import(array $data): ?string {
        return json_encode($data);
    }

    public function export(int $elementid, string $customdata): array {
        return (array) json_decode($customdata);
    }
}
