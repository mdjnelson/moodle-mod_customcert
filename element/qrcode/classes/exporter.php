<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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
