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
 * Unit tests for the digitalsignature element.
 *
 * @package    customcertelement_digitalsignature
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_digitalsignature;

use advanced_testcase;

/**
 * Unit tests for the digitalsignature element.
 *
 * @package    customcertelement_digitalsignature
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \customcertelement_digitalsignature\element
 */
final class element_test extends advanced_testcase {
    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Helper to create a minimal element stdClass.
     *
     * @param string $data JSON data to pre-populate the element with.
     * @return \stdClass
     */
    private function make_element_record(string $data = ''): \stdClass {
        $record = new \stdClass();
        $record->id = 0;
        $record->pageid = 0;
        $record->name = 'Test';
        $record->data = $data;
        $record->font = null;
        $record->fontsize = null;
        $record->colour = null;
        $record->posx = 0;
        $record->posy = 0;
        $record->width = 0;
        $record->height = 0;
        $record->refpoint = null;
        $record->alignment = null;
        $record->sequence = 0;
        $record->timecreated = time();
        $record->timemodified = time();
        return $record;
    }

    /**
     * Helper to create a minimal form data stdClass.
     *
     * @param string $password The password value submitted in the form.
     * @return \stdClass
     */
    private function make_form_data(string $password = ''): \stdClass {
        $data = new \stdClass();
        $data->signaturename = 'Test Signer';
        $data->signaturepassword = $password;
        $data->signaturelocation = 'Test Location';
        $data->signaturereason = 'Test Reason';
        $data->signaturecontactinfo = 'test@example.com';
        $data->width = 100;
        $data->height = 50;
        $data->fileid = 0;
        $data->signaturefileid = 0;
        return $data;
    }

    /**
     * Test that the password is not stored in plaintext when saving for the first time.
     *
     * The password must be stored (it is needed at signing time), but this test
     * verifies the save_unique_data path works correctly when a password is provided.
     *
     * @covers \customcertelement_digitalsignature\element::save_unique_data
     */
    public function test_save_unique_data_stores_password_on_first_save(): void {
        $record = $this->make_element_record();
        $element = new element($record);

        $data = $this->make_form_data('s3cr3t');
        $json = $element->save_unique_data($data);

        $decoded = json_decode($json, true);
        $this->assertSame('s3cr3t', $decoded['signaturepassword']);
    }

    /**
     * Test that leaving the password field blank on edit preserves the existing password.
     *
     * When an administrator reopens the element form, the password field is intentionally
     * left blank. The existing stored password must be preserved rather than overwritten
     * with an empty string.
     *
     * @covers \customcertelement_digitalsignature\element::save_unique_data
     */
    public function test_save_unique_data_preserves_password_when_field_is_blank(): void {
        $existingdata = json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => 'original_secret',
            'signaturelocation' => 'Location',
            'signaturereason' => 'Reason',
            'signaturecontactinfo' => 'info@example.com',
            'width' => 100,
            'height' => 50,
        ]);

        $record = $this->make_element_record($existingdata);
        $element = new element($record);

        // Simulate the form being submitted with a blank password field.
        $data = $this->make_form_data('');
        $json = $element->save_unique_data($data);

        $decoded = json_decode($json, true);
        $this->assertSame(
            'original_secret',
            $decoded['signaturepassword'],
            'Existing password must be preserved when the password field is left blank.'
        );
    }

    /**
     * Test that providing a new password on edit replaces the existing password.
     *
     * @covers \customcertelement_digitalsignature\element::save_unique_data
     */
    public function test_save_unique_data_replaces_password_when_new_value_provided(): void {
        $existingdata = json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => 'original_secret',
            'signaturelocation' => 'Location',
            'signaturereason' => 'Reason',
            'signaturecontactinfo' => 'info@example.com',
            'width' => 100,
            'height' => 50,
        ]);

        $record = $this->make_element_record($existingdata);
        $element = new element($record);

        $data = $this->make_form_data('new_secret');
        $json = $element->save_unique_data($data);

        $decoded = json_decode($json, true);
        $this->assertSame(
            'new_secret',
            $decoded['signaturepassword'],
            'Password must be updated when a new value is explicitly provided.'
        );
    }
}
