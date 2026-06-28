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

namespace customcertelement_coursename;

use advanced_testcase;
use coding_exception;
use mod_customcert\element\element_payload_interface;

/**
 * Tests for coursename_payload — the prototype element_payload_interface implementation.
 *
 * @package    customcertelement_coursename
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \customcertelement_coursename\coursename_payload
 */
final class coursename_payload_test extends advanced_testcase {
    /**
     * coursename_payload implements element_payload_interface.
     */
    public function test_implements_payload_interface(): void {
        $payload = coursename_payload::from_array([]);
        $this->assertInstanceOf(element_payload_interface::class, $payload);
    }

    /**
     * from_array() populates all fields with correct types from a full array.
     */
    public function test_from_array_full_data(): void {
        $payload = coursename_payload::from_array([
            'coursenamedisplay' => 1,
            'font'              => 'Times',
            'fontsize'          => 14,
            'colour'            => 'FF0000',
            'width'             => 80,
        ]);

        $this->assertSame(1, $payload->coursenamedisplay);
        $this->assertSame('Times', $payload->style->font);
        $this->assertSame(14, $payload->style->fontsize);
        $this->assertSame('FF0000', $payload->style->colour);
        $this->assertSame(80, $payload->style->width);
    }

    /**
     * from_array() applies safe defaults when the array is empty.
     */
    public function test_from_array_defaults_on_empty(): void {
        $payload = coursename_payload::from_array([]);

        $this->assertSame(element::COURSE_FULL_NAME, $payload->coursenamedisplay);
        $this->assertSame('', $payload->style->font);
        $this->assertSame(0, $payload->style->fontsize);
        $this->assertSame('', $payload->style->colour);
        $this->assertSame(0, $payload->style->width);
    }

    /**
     * from_array() casts string values to their canonical types.
     */
    public function test_from_array_casts_string_values(): void {
        $payload = coursename_payload::from_array([
            'coursenamedisplay' => '2',
            'fontsize'          => '12',
            'width'             => '100',
        ]);

        $this->assertSame(2, $payload->coursenamedisplay);
        $this->assertSame(12, $payload->style->fontsize);
        $this->assertSame(100, $payload->style->width);
    }

    /**
     * to_array() returns exactly the five expected keys in order.
     */
    public function test_to_array_returns_expected_keys(): void {
        $payload = coursename_payload::from_array([]);
        $this->assertSame(
            ['coursenamedisplay', 'font', 'fontsize', 'colour', 'width'],
            array_keys($payload->to_array())
        );
    }

    /**
     * to_array() round-trips cleanly through from_array().
     */
    public function test_round_trip(): void {
        $original = [
            'coursenamedisplay' => 2,
            'font'              => 'Helvetica',
            'fontsize'          => 12,
            'colour'            => '000000',
            'width'             => 100,
        ];

        $payload = coursename_payload::from_array($original);
        $this->assertSame($original, $payload->to_array());
    }

    /**
     * validate() passes for COURSE_FULL_NAME.
     */
    public function test_validate_passes_for_full_name(): void {
        $payload = coursename_payload::from_array(['coursenamedisplay' => element::COURSE_FULL_NAME]);
        // No exception expected.
        $payload->validate();
        $this->assertTrue(true);
    }

    /**
     * validate() passes for COURSE_SHORT_NAME.
     */
    public function test_validate_passes_for_short_name(): void {
        $payload = coursename_payload::from_array(['coursenamedisplay' => element::COURSE_SHORT_NAME]);
        $payload->validate();
        $this->assertTrue(true);
    }

    /**
     * validate() throws coding_exception for an unrecognised coursenamedisplay value.
     */
    public function test_validate_throws_for_invalid_display(): void {
        $payload = coursename_payload::from_array(['coursenamedisplay' => 99]);
        $this->expectException(coding_exception::class);
        $payload->validate();
    }

    /**
     * normalise_data() on the coursename element produces a payload that round-trips via coursename_payload.
     */
    public function test_element_normalise_data_produces_valid_payload(): void {
        $record = (object)[
            'id'           => 1,
            'pageid'       => 1,
            'name'         => 'Test',
            'element'      => 'coursename',
            'data'         => null,
            'font'         => null,
            'fontsize'     => null,
            'colour'       => null,
            'width'        => null,
            'posx'         => 0,
            'posy'         => 0,
            'refpoint'     => 0,
            'alignment'    => 'L',
            'sequence'     => 1,
            'timecreated'  => time(),
            'timemodified' => time(),
        ];

        $el = new element($record);

        $form = new \stdClass();
        $form->coursenamedisplay = 2;
        $form->font     = 'Helvetica';
        $form->fontsize = 12;
        $form->colour   = '000000';
        $form->width    = 100;

        $array = $el->normalise_data($form);

        // Must be usable directly by coursename_payload::from_array().
        $payload = coursename_payload::from_array($array);
        $payload->validate();

        $this->assertSame(2, $payload->coursenamedisplay);
        $this->assertSame('Helvetica', $payload->style->font);
        $this->assertSame(12, $payload->style->fontsize);
        $this->assertSame('000000', $payload->style->colour);
        $this->assertSame(100, $payload->style->width);
    }
}
