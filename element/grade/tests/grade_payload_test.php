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

namespace customcertelement_grade;

use advanced_testcase;
use mod_customcert\element\element_payload_interface;
use mod_customcert\element\stylable_payload;

/**
 * Tests for grade_payload.
 *
 * @package    customcertelement_grade
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \customcertelement_grade\grade_payload
 */
final class grade_payload_test extends advanced_testcase {
    /**
     * grade_payload implements element_payload_interface.
     */
    public function test_implements_payload_interface(): void {
        $payload = grade_payload::from_array([]);
        $this->assertInstanceOf(element_payload_interface::class, $payload);
    }

    /**
     * from_array() populates all fields from a full array.
     */
    public function test_from_array_full_data(): void {
        $payload = grade_payload::from_array([
            'gradeitem'   => '42',
            'gradeformat' => '1',
            'font'        => 'Times',
            'fontsize'    => 14,
            'colour'      => 'FF0000',
            'width'       => 80,
        ]);

        $this->assertSame('42', $payload->gradeitem);
        $this->assertSame('1', $payload->gradeformat);
        $this->assertInstanceOf(stylable_payload::class, $payload->style);
        $this->assertSame('Times', $payload->style->font);
        $this->assertSame(14, $payload->style->fontsize);
        $this->assertSame('FF0000', $payload->style->colour);
        $this->assertSame(80, $payload->style->width);
    }

    /**
     * from_array() applies safe defaults when the array is empty.
     */
    public function test_from_array_defaults_on_empty(): void {
        $payload = grade_payload::from_array([]);

        $this->assertSame('', $payload->gradeitem);
        $this->assertSame('', $payload->gradeformat);
        $this->assertSame('', $payload->style->font);
        $this->assertSame(0, $payload->style->fontsize);
        $this->assertSame('', $payload->style->colour);
        $this->assertSame(0, $payload->style->width);
    }

    /**
     * to_array() returns exactly the six expected keys.
     */
    public function test_to_array_returns_expected_keys(): void {
        $payload = grade_payload::from_array([]);
        $this->assertSame(
            ['gradeitem', 'gradeformat', 'font', 'fontsize', 'colour', 'width'],
            array_keys($payload->to_array())
        );
    }

    /**
     * to_array() round-trips cleanly through from_array().
     */
    public function test_round_trip(): void {
        $original = [
            'gradeitem'   => '7',
            'gradeformat' => '2',
            'font'        => 'Helvetica',
            'fontsize'    => 12,
            'colour'      => '000000',
            'width'       => 100,
        ];

        $payload = grade_payload::from_array($original);
        $this->assertSame($original, $payload->to_array());
    }

    /**
     * validate() runs without throwing.
     */
    public function test_validate_passes(): void {
        $payload = grade_payload::from_array([]);
        $payload->validate();
        $this->assertTrue(true);
    }
}
