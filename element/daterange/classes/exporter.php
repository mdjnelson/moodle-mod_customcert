<?php

namespace customcertelement_daterange;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $dateitem = $data['dateitem'];
        $validdateitems = $this->get_valid_dateitems();
        if (!in_array($dateitem, $validdateitems)) {
            $this->logger->warning("invalid dateitem '{$dateitem}'");
            return false;
        }

        $dateranges = $data['dateranges'];
        if (!is_array($dateranges)) {
            $this->logger->warning("Rangedelete should be an array");
            return false;
        }

        // Check that datestring is set dataranges what aren't need to be deleted.
        foreach ($dateranges as $daterange) {
            $startdate = (int) $daterange["startdate"];
            $enddate = (int) $daterange["enddate"];
            $datestring = $daterange["datestring"];
            $recurring = (bool) $daterange["recurring"];

            if (empty($datestring)) {
                $this->logger->warning("datestring should not be empty");
                return false;
            }

            if ($startdate >= $enddate) {
                $this->logger->warning("Start date should be smaller than end date");
                return false;
            }

            if (
                $recurring
                && ($enddate - $startdate) > element::MAX_RECURRING_PERIOD
            ) {
                $this->logger->warning("Range period should be shorter than a year if reoccuring");
                return false;
            }
        }

        return $data;
    }

    private function is_one_range_set($repeats, $rangedeletes): bool {
        for ($i = 0; $i < $repeats; $i++) {
            if (empty($rangedeletes[$i])) {
                return true;
            }
        }

        return false;
    }

    private function get_valid_dateitems() {
        return [
            element::DATE_ISSUE,
            element::DATE_CURRENT_DATE,
            element::DATE_COMPLETION,
            element::DATE_COURSE_START,
            element::DATE_COURSE_END,
            element::DATE_COURSE_GRADE,
        ];
    }

    public function convert_for_import(array $data): ?string {
        return json_encode($data);
    }

    public function export(int $elementid, string $customdata): array {
        return (array) json_decode($customdata);
    }
}
