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

namespace customcertelement_bgimage;

use advanced_testcase;
use mod_customcert\element\element_payload_interface;

/**
 * Tests for bgimage_payload.
 *
 * @package    customcertelement_bgimage
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \customcertelement_bgimage\bgimage_payload
 */
final class bgimage_payload_test extends advanced_testcase {
    /**
     * bgimage_payload implements element_payload_interface.
     */
    public function test_implements_payload_interface(): void {
        $payload = bgimage_payload::from_array([]);
        $this->assertInstanceOf(element_payload_interface::class, $payload);
    }

    /**
     * from_array() populates all fields when contextid is present.
     */
    public function test_from_array_with_file(): void {
        $payload = bgimage_payload::from_array([
            'contextid' => 42,
            'filearea'  => 'bgimage',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'bg.jpg',
        ]);

        $this->assertSame(42, $payload->contextid);
        $this->assertSame('bgimage', $payload->filearea);
        $this->assertSame(0, $payload->itemid);
        $this->assertSame('/', $payload->filepath);
        $this->assertSame('bg.jpg', $payload->filename);
    }

    /**
     * from_array() sets all fields to null when contextid is absent.
     */
    public function test_from_array_without_file(): void {
        $payload = bgimage_payload::from_array([]);

        $this->assertNull($payload->contextid);
        $this->assertNull($payload->filearea);
        $this->assertNull($payload->itemid);
        $this->assertNull($payload->filepath);
        $this->assertNull($payload->filename);
    }

    /**
     * to_array() returns an empty array when no file is set.
     */
    public function test_to_array_empty_when_no_file(): void {
        $payload = bgimage_payload::from_array([]);
        $this->assertSame([], $payload->to_array());
    }

    /**
     * to_array() returns the five file keys when a file is set.
     */
    public function test_to_array_returns_file_keys_when_file_set(): void {
        $payload = bgimage_payload::from_array([
            'contextid' => 42,
            'filearea'  => 'bgimage',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'bg.jpg',
        ]);

        $this->assertSame(['contextid', 'filearea', 'itemid', 'filepath', 'filename'], array_keys($payload->to_array()));
    }

    /**
     * to_array() round-trips cleanly through from_array() when a file is set.
     */
    public function test_round_trip_with_file(): void {
        $original = [
            'contextid' => 42,
            'filearea'  => 'bgimage',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'bg.jpg',
        ];

        $payload = bgimage_payload::from_array($original);
        $this->assertSame($original, $payload->to_array());
    }

    /**
     * from_array() treats partial file metadata as no file (all null).
     */
    public function test_from_array_ignores_partial_file_metadata(): void {
        $payload = bgimage_payload::from_array(['contextid' => 42]);
        $this->assertNull($payload->contextid);
        $this->assertNull($payload->filename);
    }

    /**
     * validate() passes when no file metadata is set.
     */
    public function test_validate_passes_no_file(): void {
        $payload = bgimage_payload::from_array([]);
        $payload->validate();
        $this->assertTrue(true);
    }

    /**
     * validate() passes when all file metadata fields are set.
     */
    public function test_validate_passes_full_file(): void {
        $payload = bgimage_payload::from_array([
            'contextid' => 42,
            'filearea'  => 'bgimage',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'bg.jpg',
        ]);
        $payload->validate();
        $this->assertTrue(true);
    }

    /**
     * validate() throws when only some file metadata fields are set.
     */
    public function test_validate_throws_on_partial_file_metadata(): void {
        $this->expectException(\coding_exception::class);
        $payload = new bgimage_payload(
            contextid: 42,
            filearea: 'bgimage',
            itemid: null,
            filepath: null,
            filename: null,
        );
        $payload->validate();
    }
}
