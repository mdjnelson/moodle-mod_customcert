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
use core_external\external_api;
use customcertelement_border\element as border_element;
use mod_customcert\local\upgrade\row_migrator;
use stdClass;

/**
 * Unit tests covering the save-element refactor changes:
 *
 *  1. border::normalise_data() preserves existing decoded data instead of returning [].
 *  2. row_migrator::migrate_row() promotes a legacy border scalar width to {"width":N}.
 *  3. external::save_element() rejects a JSON array as the data payload (array_is_list guard).
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_element_changes_test extends advanced_testcase {
    // -----------------------------------------------------------------------
    // 1. border::normalise_data()
    // -----------------------------------------------------------------------

    /**
     * normalise_data() must return an empty array for border elements regardless of
     * what is stored in data. Border has no element-specific payload; width and colour
     * are visual fields merged into the JSON envelope by the caller (edit_element.php)
     * after this call — they are not the responsibility of normalise_data().
     *
     * @covers \customcertelement_border\element::normalise_data
     */
    public function test_border_normalise_data_returns_empty_array(): void {
        $this->resetAfterTest();

        $record = (object)[
            'id' => 1,
            'pageid' => 1,
            'name' => 'Border',
            'element' => 'border',
            'data' => json_encode(['width' => 3, 'colour' => '#ff0000']),
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $element = border_element::from_record($record);

        $result = $element->normalise_data(new stdClass());

        $this->assertIsArray($result);
        $this->assertEmpty($result, 'normalise_data() must return [] — visual fields are merged by the caller');
    }

    // -----------------------------------------------------------------------
    // 2. row_migrator — migrate_row() correctness
    // -----------------------------------------------------------------------

    /**
     * The most common pre-upgrade state for elements like text/date: data holds a plain
     * integer scalar (e.g. "12") and no visual columns were set. migrate_row() must wrap
     * it as {"value":12} so the decoded integer is preserved with the correct type.
     *
     * @covers \mod_customcert\local\upgrade\row_migrator::migrate_row
     */
    public function test_row_migrator_integer_scalar_no_visuals(): void {
        $migrated = row_migrator::migrate_row('12', null, null, null, null);

        $this->assertIsString($migrated);
        $decoded = json_decode($migrated, true);
        $this->assertIsArray($decoded);
        $this->assertSame(12, $decoded['value'] ?? null, 'Integer scalar must be stored as native int under "value"');
        $this->assertArrayNotHasKey('width', $decoded);
    }

    /**
     * A plain string (non-JSON) in data with visual columns set must produce
     * {"value":"<original string>", "font":..., "fontsize":..., "colour":..., "width":...}.
     *
     * @covers \mod_customcert\local\upgrade\row_migrator::migrate_row
     */
    public function test_row_migrator_string_scalar_with_visuals(): void {
        $migrated = row_migrator::migrate_row('Some text', 0, 'freesans', 12, '000000');

        $this->assertIsString($migrated);
        $decoded = json_decode($migrated, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Some text', $decoded['value'] ?? null, 'Non-JSON string must be preserved as-is under "value"');
        $this->assertSame(0, $decoded['width'] ?? null);
        $this->assertSame('freesans', $decoded['font'] ?? null);
        $this->assertSame(12, $decoded['fontsize'] ?? null);
        $this->assertSame('000000', $decoded['colour'] ?? null);
    }

    /**
     * An integer scalar in data with visual columns set must produce
     * {"value":12, "font":..., ...} with the integer decoded natively.
     *
     * @covers \mod_customcert\local\upgrade\row_migrator::migrate_row
     */
    public function test_row_migrator_integer_scalar_with_visuals(): void {
        $migrated = row_migrator::migrate_row('12', 0, 'freesans', 12, '000000');

        $decoded = json_decode($migrated, true);
        $this->assertSame(12, $decoded['value'] ?? null, 'Integer scalar must be decoded natively, not kept as string');
        $this->assertSame('freesans', $decoded['font'] ?? null);
    }

    /**
     * An existing JSON object in data must have visuals merged in without losing
     * existing element-specific keys.
     *
     * @covers \mod_customcert\local\upgrade\row_migrator::migrate_row
     */
    public function test_row_migrator_existing_json_object_merges_visuals(): void {
        $migrated = row_migrator::migrate_row('{"dateitem":1,"dateformat":"strftimedate"}', 0, 'freesans', 12, '000000');

        $decoded = json_decode($migrated, true);
        $this->assertSame(1, $decoded['dateitem'] ?? null, 'Existing element-specific keys must be preserved');
        $this->assertSame('strftimedate', $decoded['dateformat'] ?? null);
        $this->assertSame('freesans', $decoded['font'] ?? null);
        $this->assertSame(12, $decoded['fontsize'] ?? null);
    }

    // -----------------------------------------------------------------------
    // 3. row_migrator — legacy border scalar promotion
    // -----------------------------------------------------------------------

    /**
     * migrate_border_row() with a plain integer scalar in data and no width column
     * must promote the scalar to the 'width' key so get_width() returns the correct
     * value after upgrade.
     *
     * @covers \mod_customcert\local\upgrade\row_migrator::migrate_border_row
     */
    public function test_migrate_border_row_promotes_scalar_to_width(): void {
        $migrated = row_migrator::migrate_border_row('4', null, null, null, null);

        $this->assertIsString($migrated);
        $decoded = json_decode($migrated, true);
        $this->assertIsArray($decoded);
        $this->assertSame(4, $decoded['width'] ?? null, 'Scalar must be promoted to width key');
        $this->assertArrayNotHasKey('value', $decoded, 'Scalar must not also appear under value key');
    }

    /**
     * When the old width column was explicitly set, migrate_border_row() must use
     * that value (not the scalar in data) for the width key.
     *
     * @covers \mod_customcert\local\upgrade\row_migrator::migrate_border_row
     */
    public function test_migrate_border_row_explicit_width_column_wins(): void {
        $migrated = row_migrator::migrate_border_row('3', 5, null, null, null);

        $decoded = json_decode($migrated, true);
        $this->assertSame(5, $decoded['width'] ?? null, 'Explicit width column value must win');
        $this->assertSame(3, $decoded['value'] ?? null, 'Original scalar preserved as value');
    }

    /**
     * Without migrate_border_row(), a plain scalar "4" passed directly to migrate_row()
     * with no width column produces {"value":4} — 'width' key absent, get_width() returns
     * null. This documents why migrate_border_row() exists.
     *
     * @covers \mod_customcert\local\upgrade\row_migrator::migrate_row
     */
    public function test_row_migrator_border_scalar_without_promotion_loses_width(): void {
        $migrated = row_migrator::migrate_row('4', null, null, null, null);

        $decoded = json_decode($migrated, true);
        $this->assertArrayNotHasKey('width', $decoded, 'Without promotion, width key must be absent');
        $this->assertSame(4, $decoded['value'] ?? null, 'Scalar becomes value key without promotion');
    }

    /**
     * When a legacy border row had no data at all and a width column value,
     * migrate_row() must produce {"width":N}.
     *
     * @covers \mod_customcert\local\upgrade\row_migrator::migrate_row
     */
    public function test_row_migrator_null_data_with_width_column(): void {
        $migrated = row_migrator::migrate_row(null, 4, null, null, null);

        $decoded = json_decode($migrated, true);
        $this->assertSame(4, $decoded['width'] ?? null);
        $this->assertArrayNotHasKey('value', $decoded);
    }

    /**
     * When a legacy border row had no data and no width column (truly empty),
     * migrate_row() must return null unchanged — nothing to migrate.
     *
     * @covers \mod_customcert\local\upgrade\row_migrator::migrate_row
     */
    public function test_row_migrator_null_data_no_visuals_returns_null(): void {
        $migrated = row_migrator::migrate_row(null, null, null, null, null);
        $this->assertNull($migrated);
    }

    /**
     * A whitespace-only data string must be treated as empty — not decoded as JSON null
     * and stored as {"value":null}.
     *
     * @covers \mod_customcert\local\upgrade\row_migrator::migrate_row
     */
    public function test_row_migrator_whitespace_only_data_treated_as_empty(): void {
        $migrated = row_migrator::migrate_row('   ', null, null, null, null);
        $this->assertSame('', $migrated, 'Whitespace-only with no visuals must be trimmed to empty string');

        $migrated = row_migrator::migrate_row('   ', null, 'freesans', 12, '000000');
        $decoded = json_decode($migrated, true);
        $this->assertArrayNotHasKey('value', $decoded, 'Whitespace-only data must not produce a value key');
        $this->assertSame('freesans', $decoded['font'] ?? null);
    }

    // -----------------------------------------------------------------------
    // 3. external::save_element() — JSON array guard
    // -----------------------------------------------------------------------

    /**
     * Passing a JSON array (list) as the data value must NOT corrupt the stored
     * JSON object. The array_is_list() guard in external::save_element() must
     * silently ignore it, leaving the existing element data intact.
     *
     * @covers \mod_customcert\external::save_element
     */
    public function test_save_element_ignores_json_array_data_payload(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $template = (object)[
            'name' => 'Array Guard Template',
            'contextid' => \context_system::instance()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $template->id = (int)$DB->insert_record('customcert_templates', $template, true);

        $page = (object)[
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

        $element = (object)[
            'pageid' => $page->id,
            'element' => 'text',
            'name' => 'Text',
            'posx' => 10,
            'posy' => 20,
            'refpoint' => 1,
            'alignment' => 'L',
            'data' => json_encode(['existing_key' => 'existing_value']),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $element->id = (int)$DB->insert_record('customcert_elements', $element, true);

        $values = [
            ['name' => 'data', 'value' => json_encode([1, 2, 3])],
        ];

        $result = external::save_element($template->id, $element->id, $values);
        external_api::clean_returnvalue(external::save_element_returns(), $result);
        $this->assertTrue($result);

        $row = $DB->get_record('customcert_elements', ['id' => $element->id], '*', MUST_EXIST);
        $decoded = json_decode($row->data, true);
        $this->assertIsArray($decoded);
        $this->assertSame(
            'existing_value',
            $decoded['existing_key'] ?? null,
            'Existing JSON keys must be preserved when a JSON array is passed as data'
        );
        $this->assertArrayNotHasKey(0, $decoded, 'Numeric keys from JSON array must not appear in stored data');
    }

    /**
     * Passing a JSON object as the data value must be merged into the stored payload.
     * This is the positive counterpart to the array-guard test.
     *
     * @covers \mod_customcert\external::save_element
     */
    public function test_save_element_merges_json_object_data_payload(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $template = (object)[
            'name' => 'Object Merge Template',
            'contextid' => \context_system::instance()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $template->id = (int)$DB->insert_record('customcert_templates', $template, true);

        $page = (object)[
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

        $element = (object)[
            'pageid' => $page->id,
            'element' => 'text',
            'name' => 'Text',
            'posx' => 10,
            'posy' => 20,
            'refpoint' => 1,
            'alignment' => 'L',
            'data' => json_encode(['existing_key' => 'existing_value']),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $element->id = (int)$DB->insert_record('customcert_elements', $element, true);

        $values = [
            ['name' => 'data', 'value' => json_encode(['new_key' => 'new_value'])],
        ];

        $result = external::save_element($template->id, $element->id, $values);
        external_api::clean_returnvalue(external::save_element_returns(), $result);
        $this->assertTrue($result);

        $row = $DB->get_record('customcert_elements', ['id' => $element->id], '*', MUST_EXIST);
        $decoded = json_decode($row->data, true);
        $this->assertSame('existing_value', $decoded['existing_key'] ?? null, 'Existing key must be preserved');
        $this->assertSame('new_value', $decoded['new_key'] ?? null, 'New key from JSON object must be merged');
    }
}
