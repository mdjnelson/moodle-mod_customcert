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

namespace customcertelement_digitalsignature;

use advanced_testcase;
use mod_customcert\element\element_payload_interface;

/**
 * Tests for digitalsignature_payload.
 *
 * @package    customcertelement_digitalsignature
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \customcertelement_digitalsignature\digitalsignature_payload
 */
final class digitalsignature_payload_test extends advanced_testcase {
    /**
     * digitalsignature_payload implements element_payload_interface.
     */
    public function test_implements_payload_interface(): void {
        $payload = digitalsignature_payload::from_array([]);
        $this->assertInstanceOf(element_payload_interface::class, $payload);
    }

    /**
     * from_array() populates all scalar fields from a full array.
     */
    public function test_from_array_scalar_fields(): void {
        $payload = digitalsignature_payload::from_array([
            'signaturename'        => 'Test Signer',
            'signaturepassword'    => 'secret',
            'signaturelocation'    => 'London',
            'signaturereason'      => 'Approved',
            'signaturecontactinfo' => 'test@example.com',
            'width'                => 100,
            'height'               => 50,
        ]);

        $this->assertSame('Test Signer', $payload->signaturename);
        $this->assertSame('secret', $payload->signaturepassword);
        $this->assertSame('London', $payload->signaturelocation);
        $this->assertSame('Approved', $payload->signaturereason);
        $this->assertSame('test@example.com', $payload->signaturecontactinfo);
        $this->assertSame(100, $payload->width);
        $this->assertSame(50, $payload->height);
    }

    /**
     * from_array() applies safe defaults when the array is empty.
     */
    public function test_from_array_defaults_on_empty(): void {
        $payload = digitalsignature_payload::from_array([]);

        $this->assertSame('', $payload->signaturename);
        $this->assertSame('', $payload->signaturepassword);
        $this->assertSame('', $payload->signaturelocation);
        $this->assertSame('', $payload->signaturereason);
        $this->assertSame('', $payload->signaturecontactinfo);
        $this->assertSame(0, $payload->width);
        $this->assertSame(0, $payload->height);
        $this->assertNull($payload->contextid);
        $this->assertNull($payload->signaturecontextid);
    }

    /**
     * from_array() populates image file fields when contextid is present.
     */
    public function test_from_array_with_image_file(): void {
        $payload = digitalsignature_payload::from_array([
            'contextid' => 10,
            'filearea'  => 'digitalsignature',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'sig.png',
        ]);

        $this->assertSame(10, $payload->contextid);
        $this->assertSame('digitalsignature', $payload->filearea);
        $this->assertSame(0, $payload->itemid);
        $this->assertSame('/', $payload->filepath);
        $this->assertSame('sig.png', $payload->filename);
    }

    /**
     * from_array() populates signature file fields when signaturecontextid is present.
     */
    public function test_from_array_with_signature_file(): void {
        $payload = digitalsignature_payload::from_array([
            'signaturecontextid' => 20,
            'signaturefilearea'  => 'digitalsignature',
            'signatureitemid'    => 0,
            'signaturefilepath'  => '/',
            'signaturefilename'  => 'cert.p12',
        ]);

        $this->assertSame(20, $payload->signaturecontextid);
        $this->assertSame('digitalsignature', $payload->signaturefilearea);
        $this->assertSame(0, $payload->signatureitemid);
        $this->assertSame('/', $payload->signaturefilepath);
        $this->assertSame('cert.p12', $payload->signaturefilename);
    }

    /**
     * to_array() returns the seven scalar keys when no files are set.
     */
    public function test_to_array_minimal_keys(): void {
        $payload = digitalsignature_payload::from_array([]);
        $this->assertSame(
            [
                'signaturename',
                'signaturepassword',
                'signaturelocation',
                'signaturereason',
                'signaturecontactinfo',
                'width',
                'height',
            ],
            array_keys($payload->to_array())
        );
    }

    /**
     * to_array() includes image file keys when contextid is set.
     */
    public function test_to_array_includes_image_file_keys(): void {
        $payload = digitalsignature_payload::from_array([
            'contextid' => 10,
            'filearea'  => 'digitalsignature',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'sig.png',
        ]);
        $keys = array_keys($payload->to_array());
        $this->assertContains('contextid', $keys);
        $this->assertContains('filename', $keys);
    }

    /**
     * to_array() includes signature file keys when signaturecontextid is set.
     */
    public function test_to_array_includes_signature_file_keys(): void {
        $payload = digitalsignature_payload::from_array([
            'signaturecontextid' => 20,
            'signaturefilearea'  => 'digitalsignature',
            'signatureitemid'    => 0,
            'signaturefilepath'  => '/',
            'signaturefilename'  => 'cert.p12',
        ]);
        $keys = array_keys($payload->to_array());
        $this->assertContains('signaturecontextid', $keys);
        $this->assertContains('signaturefilename', $keys);
    }

    /**
     * to_array() round-trips cleanly through from_array() with all fields set.
     */
    public function test_round_trip_full(): void {
        $original = [
            'signaturename'        => 'Test Signer',
            'signaturepassword'    => 'secret',
            'signaturelocation'    => 'London',
            'signaturereason'      => 'Approved',
            'signaturecontactinfo' => 'test@example.com',
            'width'                => 100,
            'height'               => 50,
            'contextid'            => 10,
            'filearea'             => 'digitalsignature',
            'itemid'               => 0,
            'filepath'             => '/',
            'filename'             => 'sig.png',
            'signaturecontextid'   => 20,
            'signaturefilearea'    => 'digitalsignature',
            'signatureitemid'      => 0,
            'signaturefilepath'    => '/',
            'signaturefilename'    => 'cert.p12',
        ];

        $payload = digitalsignature_payload::from_array($original);
        $this->assertSame($original, $payload->to_array());
    }

    /**
     * from_array() treats partial image file metadata as no file (all null).
     */
    public function test_from_array_ignores_partial_image_file_metadata(): void {
        $payload = digitalsignature_payload::from_array(['contextid' => 10]);
        $this->assertNull($payload->contextid);
        $this->assertNull($payload->filename);
    }

    /**
     * from_array() treats partial signature file metadata as no file (all null).
     */
    public function test_from_array_ignores_partial_signature_file_metadata(): void {
        $payload = digitalsignature_payload::from_array(['signaturecontextid' => 20]);
        $this->assertNull($payload->signaturecontextid);
        $this->assertNull($payload->signaturefilename);
    }

    /**
     * validate() passes when no file metadata is set.
     */
    public function test_validate_passes_no_files(): void {
        $payload = digitalsignature_payload::from_array([]);
        $payload->validate();
        $this->assertTrue(true);
    }

    /**
     * validate() passes when all image file metadata fields are set.
     */
    public function test_validate_passes_full_image_file(): void {
        $payload = digitalsignature_payload::from_array([
            'contextid' => 10,
            'filearea'  => 'digitalsignature',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'sig.png',
        ]);
        $payload->validate();
        $this->assertTrue(true);
    }

    /**
     * validate() passes when all signature file metadata fields are set.
     */
    public function test_validate_passes_full_signature_file(): void {
        $payload = digitalsignature_payload::from_array([
            'signaturecontextid' => 20,
            'signaturefilearea'  => 'digitalsignature',
            'signatureitemid'    => 0,
            'signaturefilepath'  => '/',
            'signaturefilename'  => 'cert.p12',
        ]);
        $payload->validate();
        $this->assertTrue(true);
    }

    /**
     * validate() throws when only some image file metadata fields are set.
     */
    public function test_validate_throws_on_partial_image_file_metadata(): void {
        $this->expectException(\coding_exception::class);
        $payload = new digitalsignature_payload(
            signaturename: '',
            signaturepassword: '',
            signaturelocation: '',
            signaturereason: '',
            signaturecontactinfo: '',
            width: 0,
            height: 0,
            contextid: 10,
            filearea: 'digitalsignature',
            itemid: null,
            filepath: null,
            filename: null,
            signaturecontextid: null,
            signaturefilearea: null,
            signatureitemid: null,
            signaturefilepath: null,
            signaturefilename: null,
        );
        $payload->validate();
    }

    /**
     * validate() throws when only some signature file metadata fields are set.
     */
    public function test_validate_throws_on_partial_signature_file_metadata(): void {
        $this->expectException(\coding_exception::class);
        $payload = new digitalsignature_payload(
            signaturename: '',
            signaturepassword: '',
            signaturelocation: '',
            signaturereason: '',
            signaturecontactinfo: '',
            width: 0,
            height: 0,
            contextid: null,
            filearea: null,
            itemid: null,
            filepath: null,
            filename: null,
            signaturecontextid: 20,
            signaturefilearea: 'digitalsignature',
            signatureitemid: null,
            signaturefilepath: null,
            signaturefilename: null,
        );
        $payload->validate();
    }
}
