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

/**
 * Tests for backwards-compatible get_data() unwrapping of generic migration wrappers.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use mod_customcert\local\upgrade\row_migrator;
use mod_customcert\tests\fixtures\legacy_get_data_element;
use stdClass;

/**
 * Tests for element::get_data() BC unwrapping and element::is_generic_migration_wrapper().
 *
 * @covers \mod_customcert\element::get_data
 * @covers \mod_customcert\element::is_generic_migration_wrapper
 */
final class element_bc_get_data_test extends \advanced_testcase {
    /**
     * Build a minimal element record with the given data string and optional element type.
     *
     * @param string|null $data
     * @param string $elementtype Element type name (e.g. 'text', 'somecustomtype').
     * @return stdClass
     */
    private function make_record(?string $data, string $elementtype = ''): stdClass {
        $rec = new stdClass();
        $rec->id = 1;
        $rec->pageid = 1;
        $rec->name = 'Test';
        $rec->element = $elementtype;
        $rec->data = $data;
        $rec->posx = null;
        $rec->posy = null;
        $rec->refpoint = null;
        $rec->alignment = element::ALIGN_LEFT;
        return $rec;
    }

    /**
     * Build a legacy_get_data_element from a raw data string.
     *
     * @param string|null $data
     * @param string $elementtype Element type name (defaults to 'somecustomtype' for BC unwrap tests).
     * @return legacy_get_data_element
     */
    private function make_element(?string $data, string $elementtype = 'somecustomtype'): legacy_get_data_element {
        require_once(__DIR__ . '/fixtures/legacy_get_data_element.php');
        return new legacy_get_data_element($this->make_record($data, $elementtype));
    }

    // Is_generic_migration_wrapper() unit tests.
    // -----------------------------------------------------------------------.

    /**
     * Data provider for is_generic_migration_wrapper().
     *
     * @return array
     */
    public static function wrapper_detection_provider(): array {
        return [
            'string value + all visuals => wrapper' => [
                '{"value":"legacy scalar","width":15,"font":"times","fontsize":12,"colour":"#000000"}',
                true,
            ],
            'string value only => wrapper' => [
                '{"value":"foo"}',
                true,
            ],
            'int value + width => wrapper' => [
                '{"value":123,"width":15}',
                true,
            ],
            'null value => wrapper' => [
                '{"value":null}',
                true,
            ],
            'value + alphachannel => wrapper' => [
                '{"value":"x","alphachannel":0.5}',
                true,
            ],
            'value + custom key => NOT wrapper' => [
                '{"value":"legacy scalar","customsetting":"keep"}',
                false,
            ],
            'value + custom key + visuals => NOT wrapper' => [
                '{"value":"foo","width":15,"mykey":"bar"}',
                false,
            ],
            'coursefield key => NOT wrapper' => [
                '{"coursefield":"shortname","width":15}',
                false,
            ],
            'userfield key => NOT wrapper' => [
                '{"userfield":"url","width":15}',
                false,
            ],
            'teacher key => NOT wrapper' => [
                '{"teacher":2,"width":15}',
                false,
            ],
            'gradeitem key => NOT wrapper' => [
                '{"gradeitem":3,"width":15}',
                false,
            ],
            'dateitem key => NOT wrapper' => [
                '{"dateitem":"0","dateformat":"strftimetime","width":15}',
                false,
            ],
            'no value key => NOT wrapper' => [
                '{"width":15,"font":"times"}',
                false,
            ],
            'plain string JSON => NOT wrapper' => [
                '"just a string"',
                false,
            ],
            'plain scalar JSON => NOT wrapper' => [
                '42',
                false,
            ],
            'empty object => NOT wrapper' => [
                '{}',
                false,
            ],
            'invalid JSON => NOT wrapper' => [
                'not json',
                false,
            ],
            'value object => NOT wrapper' => [
                '{"value":{"foo":"bar"},"width":15}',
                false,
            ],
            'value list => wrapper' => [
                '{"value":["a","b"],"width":15}',
                true,
            ],
        ];
    }

    /**
     * Tests is_generic_migration_wrapper() with various JSON inputs.
     *
     * @dataProvider wrapper_detection_provider
     * @param string $data
     * @param bool $expected
     */
    public function test_is_generic_migration_wrapper(string $data, bool $expected): void {
        $this->assertSame($expected, element::is_generic_migration_wrapper($data));
    }

    // Get_data() BC unwrapping via legacy fixture element.
    // -----------------------------------------------------------------------.

    /**
     * Generic migration wrapper with string value is unwrapped to scalar.
     */
    public function test_get_data_unwraps_string_value(): void {
        $el = $this->make_element('{"value":"legacy scalar","width":15,"font":"times","fontsize":12,"colour":"#000000"}');
        $this->assertSame('legacy scalar', $el->read_data());
    }

    /**
     * Generic migration wrapper with integer value is unwrapped to int.
     */
    public function test_get_data_unwraps_int_value(): void {
        $el = $this->make_element('{"value":123,"width":15}');
        $this->assertSame(123, $el->read_data());
    }

    /**
     * Generic migration wrapper with null value is unwrapped to null.
     */
    public function test_get_data_unwraps_null_value(): void {
        $el = $this->make_element('{"value":null}');
        $this->assertNull($el->read_data());
    }

    /**
     * JSON object with a custom key alongside value is NOT unwrapped (custom element type).
     */
    public function test_get_data_does_not_unwrap_custom_key_object(): void {
        $json = '{"value":"legacy scalar","customsetting":"keep"}';
        $el = $this->make_element($json, 'somecustomtype');
        $this->assertSame($json, $el->read_data());
    }

    /**
     * coursefield canonical payload is NOT unwrapped (bundled element type).
     */
    public function test_get_data_does_not_unwrap_coursefield_payload(): void {
        $json = '{"coursefield":"shortname","width":15,"font":"zapfdingbats","fontsize":15,"colour":"#C1B7FD"}';
        $el = $this->make_element($json, 'coursefield');
        $this->assertSame($json, $el->read_data());
    }

    /**
     * userfield canonical payload is NOT unwrapped (bundled element type).
     */
    public function test_get_data_does_not_unwrap_userfield_payload(): void {
        $json = '{"userfield":"url","width":15,"font":"zapfdingbats","fontsize":15,"colour":"#55FBA6"}';
        $el = $this->make_element($json, 'userfield');
        $this->assertSame($json, $el->read_data());
    }

    /**
     * teachername canonical payload is NOT unwrapped (bundled element type).
     */
    public function test_get_data_does_not_unwrap_teacher_payload(): void {
        $json = '{"teacher":2,"width":15,"font":"zapfdingbats","fontsize":15,"colour":"#01410D"}';
        $el = $this->make_element($json, 'teachername');
        $this->assertSame($json, $el->read_data());
    }

    /**
     * gradeitemname canonical payload is NOT unwrapped (bundled element type).
     */
    public function test_get_data_does_not_unwrap_gradeitem_payload(): void {
        $json = '{"gradeitem":3,"width":15,"font":"timesbi","fontsize":15,"colour":"#3CFB43"}';
        $el = $this->make_element($json, 'gradeitemname');
        $this->assertSame($json, $el->read_data());
    }

    /**
     * date element structured payload is NOT unwrapped (bundled element type).
     */
    public function test_get_data_does_not_unwrap_date_payload(): void {
        $json = '{"dateitem":"0","dateformat":"strftimetime","width":15,"font":"timesi","fontsize":15,"colour":"#5FFB85"}';
        $el = $this->make_element($json, 'date');
        $this->assertSame($json, $el->read_data());
    }

    /**
     * Bundled text element with value key is NOT unwrapped (regression test).
     */
    public function test_get_data_does_not_unwrap_bundled_text_element(): void {
        $json = '{"value":"legacy scalar","width":15}';
        $el = $this->make_element($json, 'text');
        $this->assertSame($json, $el->read_data());
    }

    /**
     * Custom element with structured payload and no value key is NOT unwrapped.
     */
    public function test_get_data_does_not_unwrap_custom_structured_payload_without_value(): void {
        $json = '{"timetaken":123,"verified":true}';
        $el = $this->make_element($json, 'somecustomtype');
        $this->assertSame($json, $el->read_data());
    }

    /**
     * Null data is returned as null.
     */
    public function test_get_data_null_returns_null(): void {
        $el = $this->make_element(null);
        $this->assertNull($el->read_data());
    }

    /**
     * Plain non-JSON string is returned as-is.
     */
    public function test_get_data_plain_string_returned_as_is(): void {
        $el = $this->make_element('plain legacy string');
        $this->assertSame('plain legacy string', $el->read_data());
    }

    // Migration fallback for unknown/custom element types.
    // -----------------------------------------------------------------------.

    /**
     * Unknown/custom element type migrates scalar into generic value key,
     * and legacy get_data() then unwraps it back to the scalar.
     */
    public function test_migrate_row_unknown_element_then_get_data_unwraps(): void {
        $migrated = row_migrator::migrate_row('legacy scalar', 15, 'times', 12, '#000000', 'somecustomtype');
        $this->assertNotNull($migrated);
        $arr = json_decode($migrated, true);
        $this->assertSame('legacy scalar', $arr['value']);
        $this->assertSame(15, $arr['width']);
        $this->assertSame('times', $arr['font']);
        $this->assertSame(12, $arr['fontsize']);
        $this->assertSame('#000000', $arr['colour']);

        // Now simulate a legacy element reading that migrated payload.
        $el = $this->make_element($migrated, 'somecustomtype');
        $this->assertSame('legacy scalar', $el->read_data());
    }

    /**
     * Unknown element type with no visuals migrates scalar into generic value key,
     * and legacy get_data() unwraps it.
     */
    public function test_migrate_row_unknown_element_no_visuals_then_get_data_unwraps(): void {
        $migrated = row_migrator::migrate_row('old data', null, null, null, null, 'mythirdelement');
        $this->assertNotNull($migrated);
        $arr = json_decode($migrated, true);
        $this->assertSame('old data', $arr['value']);

        $el = $this->make_element($migrated, 'mythirdelement');
        $this->assertSame('old data', $el->read_data());
    }
}
