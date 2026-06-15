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

namespace mod_customcert;

use advanced_testcase;
use context_system;
use customcertelement_text\element as text_element;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_repository;
use mod_customcert\service\persistence_helper;
use stdClass;

/**
 * Tests that element data is always stored as JSON for v2 (persistable) elements.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class persistence_json_test extends advanced_testcase {
    /**
     * Create a minimal template + page and return their IDs.
     *
     * @return array{templateid:int,pageid:int}
     */
    private function create_template_and_page(): array {
        global $DB;

        $this->resetAfterTest();

        $template = (object) [
            'name' => 'Persist JSON Template',
            'contextid' => context_system::instance()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $template->id = (int)$DB->insert_record('customcert_templates', $template, true);

        $page = (object) [
            'templateid' => $template->id,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $page->id = (int)$DB->insert_record('customcert_pages', $page, true);

        return ['templateid' => $template->id, 'pageid' => $page->id];
    }

    /**
     * A v2 persistable element writes JSON to the data column via persistence_helper.
     */
    public function test_persistable_element_writes_json(): void {
        global $DB;

        ['pageid' => $pageid] = $this->create_template_and_page();

        // Build a minimal record for a Text element.
        $record = (object) [
            'id' => null,
            'pageid' => $pageid,
            'name' => 'Text',
            'element' => 'text',
            'data' => null,
            'posx' => 10,
            'posy' => 20,
            'refpoint' => 1,
            'alignment' => 'L',
            'font' => null,
            'fontsize' => null,
            'colour' => null,
            'width' => null,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        // Insert the element record directly.
        $record->id = (int)$DB->insert_record('customcert_elements', $record, true);

        // Use persistence_helper to produce JSON from a form submission.
        $el = new text_element($record);
        $form = new stdClass();
        $form->text = 'Hello JSON';
        $json = persistence_helper::to_json_data($el, $form);

        // Write it back.
        $DB->set_field('customcert_elements', 'data', $json, ['id' => $record->id]);

        $row = $DB->get_record('customcert_elements', ['id' => $record->id], '*', MUST_EXIST);
        $this->assertIsString($row->data);
        $decoded = json_decode($row->data, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Hello JSON', $decoded['text'] ?? null);
    }
}
