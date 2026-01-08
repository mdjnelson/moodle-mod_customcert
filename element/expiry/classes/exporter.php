<?php

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
