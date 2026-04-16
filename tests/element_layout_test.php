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

namespace mod_customcert;

use mod_customcert\element as element_base;
use mod_customcert\service\element_layout;
use stdClass;

/**
 * Unit tests for element_layout DTO.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_customcert\service\element_layout
 */
final class element_layout_test extends \advanced_testcase {
    /**
     * Constructor stores values as-is.
     */
    public function test_constructor_stores_values(): void {
        $layout = new element_layout(10, 20, 1, 'C');

        $this->assertSame(10, $layout->posx);
        $this->assertSame(20, $layout->posy);
        $this->assertSame(1, $layout->refpoint);
        $this->assertSame('C', $layout->alignment);
    }

    /**
     * Constructor default alignment is ALIGN_LEFT.
     */
    public function test_constructor_default_alignment(): void {
        $layout = new element_layout(null, null, null);

        $this->assertSame(element_base::ALIGN_LEFT, $layout->alignment);
    }

    /**
     * from_record() maps populated fields correctly.
     */
    public function test_from_record_with_all_fields(): void {
        $record = new stdClass();
        $record->posx = '15';
        $record->posy = '25';
        $record->refpoint = '2';
        $record->alignment = 'R';

        $layout = element_layout::from_record($record);

        $this->assertSame(15, $layout->posx);
        $this->assertSame(25, $layout->posy);
        $this->assertSame(2, $layout->refpoint);
        $this->assertSame('R', $layout->alignment);
    }

    /**
     * from_record() treats missing fields as null / default alignment.
     */
    public function test_from_record_with_missing_fields(): void {
        $layout = element_layout::from_record(new stdClass());

        $this->assertNull($layout->posx);
        $this->assertNull($layout->posy);
        $this->assertNull($layout->refpoint);
        $this->assertSame(element_base::ALIGN_LEFT, $layout->alignment);
    }

    /**
     * from_record() treats empty-string fields as null / default alignment.
     */
    public function test_from_record_with_empty_string_fields(): void {
        $record = new stdClass();
        $record->posx = '';
        $record->posy = '';
        $record->refpoint = '';
        $record->alignment = '';

        $layout = element_layout::from_record($record);

        $this->assertNull($layout->posx);
        $this->assertNull($layout->posy);
        $this->assertNull($layout->refpoint);
        $this->assertSame(element_base::ALIGN_LEFT, $layout->alignment);
    }

    /**
     * from_record() casts numeric strings to int for positional fields.
     */
    public function test_from_record_casts_to_int(): void {
        $record = new stdClass();
        $record->posx = '0';
        $record->posy = '0';
        $record->refpoint = '0';
        $record->alignment = 'L';

        $layout = element_layout::from_record($record);

        $this->assertSame(0, $layout->posx);
        $this->assertSame(0, $layout->posy);
        $this->assertSame(0, $layout->refpoint);
    }
}
