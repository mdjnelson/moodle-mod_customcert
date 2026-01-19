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
use mod_customcert\local\paging;
use stdClass;

/**
 * Repository for loading and manipulating customcert templates.
 *
 * This provides a single place to access `customcert_templates` and applies
 * consistent ordering and paging conventions.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_repository {
    /**
     * Load a template by id.
     *
     * @param int $id
     * @return stdClass Row from `customcert_templates`.
     * @throws dml_missing_record_exception When the record doesn't exist.
     * @throws dml_exception For database errors.
     */
    public function get_by_id_or_fail(int $id): stdClass {
        global $DB;
        return $DB->get_record('customcert_templates', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * List templates for a given context.
     *
     * @param int $contextid
     * @param ordering|null $order Defaults to name ASC, timemodified DESC, id ASC.
     * @param paging|null $paging Optional paging
     * @return array<int, stdClass>
     *
     * Behaviour note: if paging is provided but its limit equals 0, this method treats it as
     * "no limit" and does not pass limit/offset to the DML call (returns all records).
     *
     * @throws dml_exception For database errors.
     */
    public function list_by_context(int $contextid, ?ordering $order = null, ?paging $paging = null): array {
        global $DB;

        $order = $order ?? new ordering([
            'name' => 'ASC',
            'timemodified' => 'DESC',
            'id' => 'ASC',
        ]);

        if ($paging && $paging->get_limit() > 0) {
            return $DB->get_records(
                'customcert_templates',
                ['contextid' => $contextid],
                $order->to_sql(),
                '*',
                $paging->get_offset(),
                $paging->get_limit()
            ) ?: [];
        }

        return $DB->get_records(
            'customcert_templates',
            ['contextid' => $contextid],
            $order->to_sql()
        ) ?: [];
    }

    /**
     * Create a new template.
     *
     * @param stdClass $data Fields for `customcert_templates` (name, contextid, etc.)
     * @return int The new template id
     * @throws invalid_parameter_exception When required fields are missing/invalid.
     * @throws dml_exception For database errors.
     */
    public function create(stdClass $data): int {
        global $DB;

        // Light input validation to avoid confusing DB errors.
        $name = isset($data->name) ? trim((string)$data->name) : '';
        $contextid = (int)($data->contextid ?? 0);

        if ($contextid <= 0) {
            throw new invalid_parameter_exception('Missing/invalid contextid');
        }
        if ($name === '') {
            throw new invalid_parameter_exception('Missing/invalid template name');
        }

        $now = time();
        $record = (object) [
            'name' => $name,
            'contextid' => $contextid,
            'timecreated' => $data->timecreated ?? $now,
            'timemodified' => $data->timemodified ?? $now,
        ];

        return (int)$DB->insert_record('customcert_templates', $record, true);
    }

    /**
     * Update an existing template.
     *
     * @param int $id
     * @param stdClass $data Allowed fields: name
     * @return void
     * @throws dml_missing_record_exception When the record doesn't exist.
     * @throws dml_exception For database errors.
     */
    public function update(int $id, stdClass $data): void {
        global $DB;
        $record = $this->get_by_id_or_fail($id);

        if (property_exists($data, 'name')) {
            $name = trim((string)$data->name);
            if ($name === '') {
                throw new \invalid_parameter_exception('Missing/invalid template name');
            }
            $record->name = $name;
        }

        $record->timemodified = time();
        $DB->update_record('customcert_templates', $record);
    }

    /**
     * Duplicate a template record (no pages/elements yet).
     *
     * This method intentionally only duplicates the template row. Copying pages
     * and elements will be handled by a higher-level service in later commits.
     *
     * @param int $sourceid
     * @param string|null $newname Optional explicit new name.
     * @return int New template id
     * @throws dml_missing_record_exception When the source record doesn't exist.
     * @throws dml_exception For database errors.
     * @throws \invalid_parameter_exception When an explicit new name is provided but empty after trimming.
     */
    public function duplicate(int $sourceid, ?string $newname = null): int {
        global $DB;

        $source = $this->get_by_id_or_fail($sourceid);

        // If you intend full-row duplication.
        $copy = clone $source;
        unset($copy->id);

        if ($newname !== null) {
            $trimmed = trim($newname);
            if ($trimmed === '') {
                throw new \invalid_parameter_exception('Missing/invalid template name');
            }
            $copy->name = $trimmed;
        } else {
            $sourcename = trim((string)($source->name ?? ''));
            if ($sourcename === '') {
                $copy->name = 'Template (copy)';
            } else {
                $copy->name = $sourcename . ' (copy)';
            }
        }
        $now = time();
        $copy->timecreated  = $now;
        $copy->timemodified = $now;

        return (int) $DB->insert_record('customcert_templates', $copy, true);
    }

    /**
     * Delete a template record.
     *
     * @param int $id
     * @return void
     * @throws dml_exception For database errors.
     */
    public function delete(int $id): void {
        global $DB;
        $DB->delete_records('customcert_templates', ['id' => $id]);
    }

    /**
     * Count templates in a context.
     *
     * @param int $contextid
     * @return int
     * @throws dml_exception For database errors.
     */
    public function count_by_context(int $contextid): int {
        global $DB;
        return (int)$DB->count_records('customcert_templates', ['contextid' => $contextid]);
    }
}
