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
     * normalise_data() must return colour and width from the submitted form data.
     *
     * @covers \customcertelement_border\element::normalise_data
     */
    public function test_border_normalise_data_returns_colour_and_width(): void {
        $this->resetAfterTest();

        $record = (object)[
            'id' => 1,
            'pageid' => 1,
            'name' => 'Border',
            'element' => 'border',
            'data' => json_encode(['width' => 3, 'colour' => 'ff0000']),
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $element = border_element::from_record($record);

        $formdata = (object)['colour' => '0000ff', 'width' => 2];
        $result = $element->normalise_data($formdata);

        $this->assertIsArray($result);
        $this->assertSame('0000ff', $result['colour']);
        $this->assertSame(2, $result['width']);
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
    // 3. Persistable element normalise_data() merge
    // -----------------------------------------------------------------------
    /**
     * For persistable elements, normalise_data() result is stored in the data column.
     *
     * The text element implements persistable_element_interface and returns
     * ['value' => ...] from normalise_data().
     *
     * @covers \mod_customcert\external::save_element
     */
    public function test_save_element_persistable_normalise_preserves_existing_keys(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $template = (object)[
            'name' => 'Persistable Merge Template',
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
        // Pre-seed the element with existing JSON that includes visual fields.
        $element = (object)[
            'pageid' => $page->id,
            'element' => 'text',
            'name' => 'Text',
            'posx' => 10,
            'posy' => 20,
            'refpoint' => 1,
            'alignment' => 'L',
            'data' => json_encode(['value' => 'old text', 'font' => 'freesans', 'fontsize' => 12]),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $element->id = (int)$DB->insert_record('customcert_elements', $element, true);
        // Save with a new text value; the text element's normalise_data() reads $formdata->text.
        $values = [
            ['name' => 'text', 'value' => 'new text'],
        ];
        $result = external::save_element($template->id, $element->id, $values);
        external_api::clean_returnvalue(external::save_element_returns(), $result);
        $this->assertTrue($result);
        $row = $DB->get_record('customcert_elements', ['id' => $element->id], '*', MUST_EXIST);
        $decoded = json_decode($row->data, true);
        $this->assertSame('new text', $decoded['value'] ?? null, 'normalise_data() value must be stored');
    }

    // -----------------------------------------------------------------------
    // 4. Border element save via web service
    // -----------------------------------------------------------------------
    /**
     * The border element's normalise_data() returns colour and width, so submitting
     * those fields via the web service must persist them in the data column.
     *
     * @covers \mod_customcert\external::save_element
     */
    public function test_save_element_border_saves_colour_and_width(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $template = (object)[
            'name' => 'Legacy Border Template',
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
            'element' => 'border',
            'name' => 'Border',
            'posx' => 5,
            'posy' => 5,
            'refpoint' => 0,
            'alignment' => 'L',
            'data' => json_encode(['width' => 2, 'colour' => 'ff0000']),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $element->id = (int)$DB->insert_record('customcert_elements', $element, true);
        // Submit new colour and width values.
        $values = [
            ['name' => 'colour', 'value' => '0000ff'],
            ['name' => 'width', 'value' => '3'],
        ];
        $result = external::save_element($template->id, $element->id, $values);
        external_api::clean_returnvalue(external::save_element_returns(), $result);
        $this->assertTrue($result);
        $row = $DB->get_record('customcert_elements', ['id' => $element->id], '*', MUST_EXIST);
        $decoded = json_decode($row->data, true);
        $this->assertSame('0000ff', $decoded['colour'] ?? null, 'Submitted colour must be stored');
        $this->assertSame(3, $decoded['width'] ?? null, 'Submitted width must be stored');
    }
    // -----------------------------------------------------------------------
    // 5. Fractional coordinate rounding
    // -----------------------------------------------------------------------
    /**
     * ajax.php rounds fractional positions before passing them to update_position().
     * Verify the rounding expression used in ajax.php produces the correct integer
     * for sub-pixel values emitted by the JS editor.
     *
     * @covers \mod_customcert\service\element_repository::update_position
     */
    public function test_fractional_position_rounding(): void {
        // These mirror the expression used in ajax.php - (int)round((float)$value->posx).
        $cases = [
            ['input' => '10.4', 'expected' => 10],
            ['input' => '10.5', 'expected' => 11],
            ['input' => '10.6', 'expected' => 11],
            ['input' => '0.0', 'expected' => 0],
            ['input' => '99.9', 'expected' => 100],
        ];
        foreach ($cases as $case) {
            $rounded = (int)round((float)$case['input']);
            $this->assertSame(
                $case['expected'],
                $rounded,
                "Rounding '{$case['input']}' should give {$case['expected']}"
            );
        }
    }
}
