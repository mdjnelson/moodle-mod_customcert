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

namespace customcertelement_qrcode;

use advanced_testcase;
use mod_customcert\element\element_payload_interface;

/**
 * Tests for qrcode_payload.
 *
 * @package    customcertelement_qrcode
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \customcertelement_qrcode\qrcode_payload
 */
final class qrcode_payload_test extends advanced_testcase {
    /**
     * qrcode_payload implements element_payload_interface.
     */
    public function test_implements_payload_interface(): void {
        $payload = qrcode_payload::from_array([]);
        $this->assertInstanceOf(element_payload_interface::class, $payload);
    }

    /**
     * from_array() populates all fields from a full array.
     */
    public function test_from_array_full_data(): void {
        $payload = qrcode_payload::from_array([
            'width'  => 50,
            'height' => 50,
        ]);

        $this->assertSame(50, $payload->width);
        $this->assertSame(50, $payload->height);
    }

    /**
     * from_array() applies safe defaults when the array is empty.
     */
    public function test_from_array_defaults_on_empty(): void {
        $payload = qrcode_payload::from_array([]);

        $this->assertSame(0, $payload->width);
        $this->assertSame(0, $payload->height);
    }

    /**
     * from_array() casts string values to their canonical types.
     */
    public function test_from_array_casts_values(): void {
        $payload = qrcode_payload::from_array(['width' => '40', 'height' => '40']);
        $this->assertSame(40, $payload->width);
        $this->assertSame(40, $payload->height);
    }

    /**
     * to_array() returns exactly the two expected keys.
     */
    public function test_to_array_returns_expected_keys(): void {
        $payload = qrcode_payload::from_array([]);
        $this->assertSame(['width', 'height'], array_keys($payload->to_array()));
    }

    /**
     * to_array() round-trips cleanly through from_array().
     */
    public function test_round_trip(): void {
        $original = ['width' => 50, 'height' => 50];

        $payload = qrcode_payload::from_array($original);
        $this->assertSame($original, $payload->to_array());
    }

    /**
     * validate() runs without throwing.
     */
    public function test_validate_passes(): void {
        $payload = qrcode_payload::from_array([]);
        $payload->validate();
        $this->assertTrue(true);
    }
}
