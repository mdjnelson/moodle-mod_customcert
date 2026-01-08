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
use moodle_database;

/**
 * Handles the import and export of certificate pages and their elements.
 *
 * It coordinates the page-level export/import process
 * and delegates element handling to the `element` class.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page {
    /**
     * @var clock Clock instance used to retrieve current timestamps.
     */
    private clock $clock;
    /**
     * @var table_exporter Table exporter responsible for retrieving page data from the database.
     */
    private table_exporter $exporter;
    /**
     * @var string Name of the database table containing certificate pages.
     */
    protected static string $dbtable = 'customcert_pages';
    /**
     * @var array List of database fields to be used in page import/export operations.
     */
    protected array $fields = [
        'width',
        'height',
        'leftmargin',
        'rightmargin',
        'sequence',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->exporter = new table_exporter(self::$dbtable);
        $this->clock = di::get(clock::class);
    }

    /**
     * Retrieves all page IDs associated with a specific certificate template.
     *
     * @param int $templateid The ID of the certificate template.
     * @return array List of page IDs.
     */
    public static function get_pageids_from_template(int $templateid): array {
        $db = di::get(moodle_database::class);
        $pageids = $db->get_fieldset(static::$dbtable, 'id', ['templateid' => $templateid]);
        return $pageids;
    }

    /**
     * Imports a certificate page and its elements from a provided data array.
     *
     * Inserts the page into the database, then imports each associated element.
     *
     * @param int $templateid The ID of the template to which the page belongs.
     * @param array $pagedata The page data to import, including elements.
     */
    public function import(int $templateid, array $pagedata): void {
        $db = di::get(moodle_database::class);
        $pageid = $db->insert_record(static::$dbtable, [
            'templateid' => $templateid,
            'width' => (int) $pagedata['width'],
            'height' => (int) $pagedata['height'],
            'leftmargin' => (int) $pagedata['leftmargin'],
            'rightmargin' => (int) $pagedata['rightmargin'],
            'sequence' => (int) $pagedata['sequence'],
            'timecreated' => $this->clock->time(),
            'timemodified' => $this->clock->time(),
        ]);

        $element = new element();
        foreach ($pagedata['elements'] as $elementdata) {
            $element->import($pageid, $elementdata);
        }
    }

    /**
     * Exports a certificate page, including all associated elements.
     *
     * Fetches layout data and recursively exports each element on the page.
     *
     * @param int $pageid The ID of the page to export.
     * @return array The exported page data.
     */
    public function export(int $pageid): array {
        $data = $this->exporter->export($pageid, $this->fields);

        $elementids = element::get_elementids_from_page($pageid);
        $element = new element();
        $data['elements'] = array_map(function ($elementid) use ($element) {
            return $element->export($elementid);
        }, $elementids);
        return $data;
    }
}
