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

namespace customcertelement_expiry;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $dateitem = $data["dateitem"];
        $validdateitems = $this->get_dateitems();
        if (!in_array($dateitem, $validdateitems)) {
            $this->logger->warning("invalid expiry date item");
            $dateitem = $validdateitems[0];
        }

        $dateformat = $data["dateformat"];
        $validdateformats = array_keys(element::get_date_formats());
        if (!in_array($dateformat, $validdateformats)) {
            $this->logger->warning("invalid expiry date format");
            $dateformat = $validdateformats[0];
        }

        $startfrom = $data["startfrom"];
        $validstartfroms = ['award', 'coursecomplete'];
        if (!in_array($startfrom, $validstartfroms)) {
            $this->logger->warning("invalid expiry start date format");
            $startfrom = $validstartfroms[0];
        }

        return [
            'dateitem' => $dateitem,
            'dateformat' => $dateformat,
            'startfrom' => $startfrom,
        ];
    }

    private function get_dateitems(): array {
        return [
            '-8',
            '-9',
            '-10',
            '-11',
            '-12',
        ];
    }

    public function convert_for_import(array $data): ?string {
        return json_encode($data);
    }

    public function export(int $elementid, string $customdata): array {
        $data = json_decode($customdata);

        $arrtostore = [
            'dateitem' => $data->dateitem,
            'dateformat' => $data->dateformat,
            'startfrom' => $data->startfrom,
        ];
        return $arrtostore;
    }
}
