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

namespace customcertelement_image;

use advanced_testcase;
use mod_customcert\element\element_payload_interface;

/**
 * Tests for image_payload.
 *
 * @package    customcertelement_image
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \customcertelement_image\image_payload
 */
final class image_payload_test extends advanced_testcase {
    /**
     * image_payload implements element_payload_interface.
     */
    public function test_implements_payload_interface(): void {
        $payload = image_payload::from_array([]);
        $this->assertInstanceOf(element_payload_interface::class, $payload);
    }

    /**
     * from_array() populates all fields from a full array including file and alphachannel.
     */
    public function test_from_array_full_data(): void {
        $payload = image_payload::from_array([
            'width'        => 100,
            'height'       => 80,
            'alphachannel' => 0.5,
            'contextid'    => 42,
            'filearea'     => 'image',
            'itemid'       => 0,
            'filepath'     => '/',
            'filename'     => 'logo.png',
        ]);

        $this->assertSame(100, $payload->width);
        $this->assertSame(80, $payload->height);
        $this->assertSame(0.5, $payload->alphachannel);
        $this->assertSame(42, $payload->contextid);
        $this->assertSame('image', $payload->filearea);
        $this->assertSame(0, $payload->itemid);
        $this->assertSame('/', $payload->filepath);
        $this->assertSame('logo.png', $payload->filename);
    }

    /**
     * from_array() applies safe defaults when the array is empty.
     */
    public function test_from_array_defaults_on_empty(): void {
        $payload = image_payload::from_array([]);

        $this->assertSame(0, $payload->width);
        $this->assertSame(0, $payload->height);
        $this->assertNull($payload->alphachannel);
        $this->assertNull($payload->contextid);
        $this->assertNull($payload->filearea);
        $this->assertNull($payload->itemid);
        $this->assertNull($payload->filepath);
        $this->assertNull($payload->filename);
    }

    /**
     * to_array() returns only width and height when no optional fields are set.
     */
    public function test_to_array_minimal_keys(): void {
        $payload = image_payload::from_array([]);
        $this->assertSame(['width', 'height'], array_keys($payload->to_array()));
    }

    /**
     * to_array() includes alphachannel when set.
     */
    public function test_to_array_includes_alphachannel_when_set(): void {
        $payload = image_payload::from_array(['alphachannel' => 1.0]);
        $keys = array_keys($payload->to_array());
        $this->assertContains('alphachannel', $keys);
    }

    /**
     * to_array() includes file keys when contextid is set.
     */
    public function test_to_array_includes_file_keys_when_set(): void {
        $payload = image_payload::from_array([
            'contextid' => 42,
            'filearea'  => 'image',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'logo.png',
        ]);
        $keys = array_keys($payload->to_array());
        $this->assertContains('contextid', $keys);
        $this->assertContains('filename', $keys);
    }

    /**
     * to_array() round-trips cleanly through from_array() with all fields set.
     */
    public function test_round_trip_full(): void {
        $original = [
            'width'        => 100,
            'height'       => 80,
            'alphachannel' => 0.5,
            'contextid'    => 42,
            'filearea'     => 'image',
            'itemid'       => 0,
            'filepath'     => '/',
            'filename'     => 'logo.png',
        ];

        $payload = image_payload::from_array($original);
        $this->assertSame($original, $payload->to_array());
    }

    /**
     * to_array() round-trips cleanly through from_array() with only width and height.
     */
    public function test_round_trip_minimal(): void {
        $original = ['width' => 50, 'height' => 40];

        $payload = image_payload::from_array($original);
        $this->assertSame($original, $payload->to_array());
    }

    /**
     * from_array() treats partial file metadata as no file (all null).
     */
    public function test_from_array_ignores_partial_file_metadata(): void {
        $payload = image_payload::from_array(['contextid' => 42]);
        $this->assertNull($payload->contextid);
        $this->assertNull($payload->filename);
    }

    /**
     * validate() passes when no file metadata is set.
     */
    public function test_validate_passes_no_file(): void {
        $payload = image_payload::from_array([]);
        $payload->validate();
        $this->assertTrue(true);
    }

    /**
     * validate() passes when all file metadata fields are set.
     */
    public function test_validate_passes_full_file(): void {
        $payload = image_payload::from_array([
            'contextid' => 42,
            'filearea'  => 'image',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'logo.png',
        ]);
        $payload->validate();
        $this->assertTrue(true);
    }

    /**
     * validate() throws when only some file metadata fields are set.
     */
    public function test_validate_throws_on_partial_file_metadata(): void {
        $this->expectException(\coding_exception::class);
        $payload = new image_payload(
            width: 100,
            height: 80,
            alphachannel: null,
            contextid: 42,
            filearea: 'image',
            itemid: null,
            filepath: null,
            filename: null,
        );
        $payload->validate();
    }
}
