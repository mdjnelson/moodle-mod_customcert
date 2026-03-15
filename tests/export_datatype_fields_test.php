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
use mod_customcert\export\datatypes\string_field;
use mod_customcert\export\datatypes\int_field;
use mod_customcert\export\datatypes\float_field;
use mod_customcert\export\datatypes\enum_field;
use mod_customcert\export\datatypes\format_exception;

/**
 * Tests for export datatype field classes.
 *
 * @package    mod_customcert
 * @category   test
 * @group      mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\export\datatypes\string_field
 * @covers     \mod_customcert\export\datatypes\int_field
 * @covers     \mod_customcert\export\datatypes\float_field
 * @covers     \mod_customcert\export\datatypes\enum_field
 */
final class export_datatype_fields_test extends advanced_testcase {

    // -------------------------------------------------------------------------
    // string_field
    // -------------------------------------------------------------------------

    /**
     * Test string_field imports a valid string.
     */
    public function test_string_field_import_valid(): void {
        $field = new string_field();
        $this->assertSame('hello', $field->import(['value' => 'hello']));
    }

    /**
     * Test string_field throws on empty string when not allowed.
     */
    public function test_string_field_import_empty_not_allowed(): void {
        $field = new string_field(false);
        $this->expectException(format_exception::class);
        $field->import(['value' => '']);
    }

    /**
     * Test string_field allows empty string when configured.
     */
    public function test_string_field_import_empty_allowed(): void {
        $field = new string_field(true);
        $this->assertSame('', $field->import(['value' => '']));
    }

    /**
     * Test string_field export wraps value in array.
     */
    public function test_string_field_export(): void {
        $field = new string_field();
        $this->assertSame(['value' => 'test'], $field->export('test'));
    }

    /**
     * Test string_field fallback returns configured default.
     */
    public function test_string_field_fallback(): void {
        $field = new string_field(false, 'mydefault');
        $this->assertSame('mydefault', $field->get_fallback());
    }

    // -------------------------------------------------------------------------
    // float_field
    // -------------------------------------------------------------------------

    /**
     * Test float_field imports a value within range.
     */
    public function test_float_field_import_in_range(): void {
        $field = new float_field(0.0, 100.0);
        $this->assertSame(50.5, $field->import(['value' => 50.5]));
    }

    /**
     * Test float_field throws when value is below minimum.
     */
    public function test_float_field_import_below_min(): void {
        $field = new float_field(10.0, null);
        $this->expectException(format_exception::class);
        $field->import(['value' => 5.0]);
    }

    /**
     * Test float_field throws when value is above maximum.
     */
    public function test_float_field_import_above_max(): void {
        $field = new float_field(null, 10.0);
        $this->expectException(format_exception::class);
        $field->import(['value' => 15.0]);
    }

    /**
     * Test float_field export wraps value in array.
     */
    public function test_float_field_export(): void {
        $field = new float_field();
        $this->assertSame(['value' => 3.14], $field->export(3.14));
    }

    /**
     * Test float_field fallback returns min when set.
     */
    public function test_float_field_fallback_uses_min(): void {
        $field = new float_field(5.0, 10.0);
        $this->assertSame(5.0, $field->get_fallback());
    }

    /**
     * Test float_field fallback returns max when min is null.
     */
    public function test_float_field_fallback_uses_max_when_no_min(): void {
        $field = new float_field(null, 10.0);
        $this->assertSame(10.0, $field->get_fallback());
    }

    // -------------------------------------------------------------------------
    // int_field
    // -------------------------------------------------------------------------

    /**
     * Test int_field imports and casts to integer.
     */
    public function test_int_field_import_casts_to_int(): void {
        $field = new int_field(0.0, 100.0);
        $this->assertSame(7, $field->import(['value' => 7.9]));
    }

    /**
     * Test int_field fallback returns ceiling of min.
     */
    public function test_int_field_fallback_ceil_min(): void {
        $field = new int_field(1.2, 10.0);
        $this->assertSame(2.0, $field->get_fallback());
    }

    /**
     * Test int_field fallback returns floor of max when no min.
     */
    public function test_int_field_fallback_floor_max(): void {
        $field = new int_field(null, 9.8);
        $this->assertSame(9.0, $field->get_fallback());
    }

    // -------------------------------------------------------------------------
    // enum_field
    // -------------------------------------------------------------------------

    /**
     * Test enum_field imports a valid option.
     */
    public function test_enum_field_import_valid(): void {
        $field = new enum_field(['a', 'b', 'c']);
        $this->assertSame('b', $field->import(['value' => 'b']));
    }

    /**
     * Test enum_field throws on invalid option.
     */
    public function test_enum_field_import_invalid(): void {
        $field = new enum_field(['a', 'b', 'c']);
        $this->expectException(format_exception::class);
        $field->import(['value' => 'z']);
    }

    /**
     * Test enum_field export wraps value in array.
     */
    public function test_enum_field_export(): void {
        $field = new enum_field(['a', 'b']);
        $this->assertSame(['value' => 'a'], $field->export('a'));
    }

    /**
     * Test enum_field fallback returns first option.
     */
    public function test_enum_field_fallback_first(): void {
        $field = new enum_field(['x', 'y', 'z']);
        $this->assertSame('x', $field->get_fallback());
    }

    /**
     * Test enum_field fallback returns null when firstasdefault is false.
     */
    public function test_enum_field_fallback_null(): void {
        $field = new enum_field(['x', 'y'], false);
        $this->assertNull($field->get_fallback());
    }
}
