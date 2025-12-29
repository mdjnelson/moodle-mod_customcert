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
use mod_customcert\element as legacy_base_element;
use mod_customcert\service\element_renderer;
use stdClass;

/**
 * Tests that element data is always stored as JSON, both for new (persistable) and legacy elements.
 *
 * @package    mod_customcert
 * @category   test
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
     * Persistable (core) element writes JSON to data.
     */
    public function test_persistable_element_writes_json(): void {
        global $DB;

        ['pageid' => $pageid] = $this->create_template_and_page();

        // Build form submission for a Text element.
        $form = new stdClass();
        $form->name = 'Text';
        $form->text = 'Hello JSON';
        $form->posx = 10;
        $form->posy = 20;
        $form->refpoint = 1;
        $form->alignment = 'L';
        $form->element = 'text';
        $form->pageid = $pageid;

        // Instantiate the core Text element (persistable) with minimal legacy record.
        $legacyrecord = (object) [
            'id' => null,
            'pageid' => $pageid,
            'name' => 'Text',
            'data' => null,
            'posx' => null,
            'posy' => null,
            'refpoint' => null,
            'alignment' => null,
        ];
        $el = new text_element($legacyrecord);

        $newid = $el->save_form_elements($form);
        $this->assertIsNumeric($newid);

        $row = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $this->assertIsString($row->data);
        $decoded = json_decode($row->data, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Hello JSON', $decoded['value'] ?? null);
        // Visual fields (font, fontsize, colour, width) are no longer stored as separate columns
        // and may not be present in the JSON payload for text; assert core content only.
    }

    /**
     * Legacy elements (non-persistable) are JSON-encoded via fallback logic.
     */
    public function test_legacy_element_fallback_json_encoding(): void {
        global $DB;

        ['pageid' => $pageid] = $this->create_template_and_page();

        // Minimal anonymous legacy element: returns a plain string from save_unique_data().
        $legacyrecord = (object) [
            'id' => null,
            'pageid' => $pageid,
            'name' => 'LegacyPlain',
            'data' => null,
            'posx' => null,
            'posy' => null,
            'refpoint' => null,
            'alignment' => null,
        ];

        $legacy = new class ($legacyrecord) extends legacy_base_element {
            /**
             * Legacy save implementation returning a plain string.
             *
             * @param stdClass $data
             * @return string
             */
            public function save_unique_data($data) {
                return 'plainstring';
            }

            /**
             * Legacy render (unused in this test).
             *
             * @param \pdf $pdf The PDF instance
             * @param bool $preview Preview flag
             * @param stdClass $user User record
             * @param element_renderer|null $renderer Optional renderer
             * @return void
             */
            public function render(\pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
            }

            /**
             * Legacy HTML render (unused in this test).
             *
             * @param element_renderer|null $renderer Optional renderer
             * @return string
             */
            public function render_html(?element_renderer $renderer = null): string {
                return '';
            }
        };

        $form = (object) [
            'name' => 'LegacyPlain',
            'element' => 'legacyplain',
            'pageid' => $pageid,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 1,
            'alignment' => 'L',
        ];

        $newid = $legacy->save_form_elements($form);
        $this->assertIsNumeric($newid);

        $row = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $this->assertIsString($row->data);
        $decoded = json_decode($row->data, true);
        $this->assertIsArray($decoded, 'Expected JSON-encoded fallback for legacy element');
        $this->assertSame('plainstring', $decoded['value'] ?? null);
    }
}
