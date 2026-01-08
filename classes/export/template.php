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

namespace mod_customcert\export;

use core\clock;
use core\di;
use mod_customcert\export\contracts\import_exception;
use moodle_database;

/**
 * Handles the import and export of custom certificate templates.
 *
 * This class manages top-level certificate template data including its pages
 * and their associated elements. It supports transactional imports and structured
 * exports using nested component classes.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template {
    /**
     * @var clock Clock service for generating consistent timestamps.
     */
    private clock $clock;
    /**
     * @var table_exporter Table exporter for retrieving template-level data.
     */
    private table_exporter $exporter;
    /**
     * @var string Name of the database table for certificate templates.
     */
    protected static string $dbtable = 'customcert_templates';
    /**
     * @var array List of fields to be exported from the template table.
     */
    protected array $fields = ['name'];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->exporter = new table_exporter(self::$dbtable);
        $this->clock = di::get(clock::class);
    }

    /**
     * Imports a full certificate template, including pages and elements.
     *
     * Validates the structure, inserts template metadata into the database,
     * and initiates recursive import of page data. Uses a delegated DB transaction
     * to ensure consistency.
     *
     * @param int $contextid The context in which the template is being imported.
     * @param array $templatedata The structured data for the template.
     * @throws import_exception If required template fields are missing.
     */
    public function import(int $contextid, array $templatedata): void {
        if (($templatedata['name'] ?? null) == null) {
            throw new import_exception('Certificate missing the attribute name');
        }

        $db = di::get(moodle_database::class);
        $db->transactions_forbidden();
        $transaction = $db->start_delegated_transaction();

        $tid = $db->insert_record(static::$dbtable, [
            'name' => $templatedata['name'],
            'contextid' => $contextid,
            'timecreated' => $this->clock->time(),
            'timemodified' => $this->clock->time(),
        ]);

        $page = new page();
        foreach ($templatedata['pages'] as $pagedata) {
            $page->import($tid, $pagedata);
        }
        $transaction->allow_commit();
    }

    /**
     * Exports a full certificate template including all pages and elements.
     *
     * Retrieves the template metadata and assembles the full structure recursively.
     *
     * @param int $templateid The ID of the template to export.
     * @return array The structured export of the template.
     */
    public function export(int $templateid): array {
        $data = $this->exporter->export($templateid, $this->fields);

        $pageids = page::get_pageids_from_template($templateid);
        $page = new page();
        $data['pages'] = array_map(function ($pageid) use ($page) {
            return $page->export($pageid);
        }, $pageids);
        return $data;
    }
}
