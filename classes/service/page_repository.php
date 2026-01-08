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

declare(strict_types=1);

namespace mod_customcert\service;

use dml_exception;
use dml_missing_record_exception;
use invalid_parameter_exception;
use mod_customcert\local\ordering;
use stdClass;

/**
 * Repository for loading and manipulating customcert pages.
 *
 * Provides a single place to access `customcert_pages` and applies
 * consistent ordering conventions.
 *
 * Default page ordering: sequence ASC, id ASC.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page_repository {
    /**
     * Load a page by id or throw if missing.
     *
     * @param int $id
     * @return stdClass
     * @throws dml_missing_record_exception When the record doesn't exist.
     * @throws dml_exception For database errors.
     */
    public function get_by_id_or_fail(int $id): stdClass {
        global $DB;
        return $DB->get_record('customcert_pages', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * List all pages for a template id.
     *
     * @param int $templateid
     * @param ordering|null $order Defaults to sequence ASC, id ASC.
     * @return array<int, stdClass>
     * @throws dml_exception For database errors.
     */
    public function list_by_template(int $templateid, ?ordering $order = null): array {
        global $DB;

        $order = $order ?? new ordering([
            'sequence' => 'ASC',
            'id' => 'ASC',
        ]);

        return $DB->get_records('customcert_pages', ['templateid' => $templateid], $order->to_sql()) ?: [];
    }

    /**
     * Create a page.
     *
     * Required fields: templateid (>0), width, height, leftmargin, rightmargin.
     * Optional: sequence (if omitted/null, appended at the end).
     *
     * @param stdClass $data
     * @return int New page id
     * @throws invalid_parameter_exception When required fields are missing/invalid.
     * @throws dml_exception For database errors.
     */
    public function create(stdClass $data): int {
        global $DB;

        $templateid = (int)($data->templateid ?? 0);
        if ($templateid <= 0) {
            throw new invalid_parameter_exception('Missing/invalid templateid');
        }

        // Basic integer validation; allow zeroes for margins/sizes if desired by caller.
        $width = (int)($data->width ?? 0);
        $height = (int)($data->height ?? 0);
        $leftmargin = (int)($data->leftmargin ?? 0);
        $rightmargin = (int)($data->rightmargin ?? 0);

        $now = time();
        $record = (object) [
            'templateid' => $templateid,
            'width' => $width,
            'height' => $height,
            'leftmargin' => $leftmargin,
            'rightmargin' => $rightmargin,
            'sequence' => isset($data->sequence) ? (int)$data->sequence : null,
            'timecreated' => $data->timecreated ?? $now,
            'timemodified' => $data->timemodified ?? $now,
        ];

        // If sequence not provided, append at end.
        if ($record->sequence === null) {
            $maxseq = (int)$DB->get_field_sql(
                'SELECT MAX(sequence) FROM {customcert_pages} WHERE templateid = ?',
                [$templateid]
            );
            $record->sequence = $maxseq > 0 ? $maxseq + 1 : 1;
        }

        return (int)$DB->insert_record('customcert_pages', $record, true);
    }

    /**
     * Bulk create pages for a template.
     *
     * Sequences from provided data are respected; null sequences will be appended.
     * A resequence() can be called afterwards to compact if needed.
     *
     * @param int $templateid
     * @param stdClass[] $pages
     * @return void
     * @throws invalid_parameter_exception When templateid is invalid.
     * @throws dml_exception For database errors.
     */
    public function bulk_create(int $templateid, array $pages): void {
        if ($templateid <= 0) {
            throw new invalid_parameter_exception('Missing/invalid templateid');
        }
        foreach ($pages as $p) {
            $p = (object)$p;
            $p->templateid = $templateid;
            $this->create($p);
        }
    }

    /**
     * Resequence all pages for a template to compact 1..N while preserving order.
     *
     * Order is defined by sequence ASC, id ASC. After resequence, sequence values
     * will be 1..N in that order.
     *
     * @param int $templateid
     * @return void
     * @throws dml_exception For database errors.
     */
    public function resequence(int $templateid): void {
        global $DB;
        $pages = $this->list_by_template($templateid);
        $seq = 1;
        $now = time();
        foreach ($pages as $page) {
            if ((int)$page->sequence !== $seq) {
                $page->sequence = $seq;
                $page->timemodified = $now;
                $DB->update_record('customcert_pages', $page);
            }
            $seq++;
        }
    }
}
