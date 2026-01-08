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
