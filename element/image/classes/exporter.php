<?php

namespace customcertelement_image;

use customcertelement_bgimage\exporter as bgimage_exporter;

class exporter extends bgimage_exporter {
    public function validate(array $data): array|false {
        $valid = parent::validate($data);
        if (!$valid) {
            return false;
        }

        $width = intval($data['width'] ?? null);
        $height = intval($data['height'] ?? null);

        return [
            'width' => $width,
            'height' => $height,
            'alphachannel' => $data['alphachannel'],
            'imageref' => $data['imageref'],
        ];
    }
}
