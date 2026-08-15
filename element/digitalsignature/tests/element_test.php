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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

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
     * Test that the plaintext password does not appear in persisted element JSON after saving.
     *
     * @covers \customcertelement_digitalsignature\element::save_unique_data
     */
    public function test_save_unique_data_does_not_store_plaintext_password(): void {
        $record = $this->make_element_record();
        $element = new element($record);

        $data = $this->make_form_data('s3cr3t');
        $json = $element->save_unique_data($data);

        $this->assertStringNotContainsString(
            's3cr3t',
            $json,
            'The plaintext password must not appear in the persisted JSON.'
        );
    }

    /**
     * Test that the persisted encrypted value decrypts back to the original password.
     *
     * @covers \customcertelement_digitalsignature\element::save_unique_data
     */
    public function test_save_unique_data_encrypted_value_decrypts_correctly(): void {
        $record = $this->make_element_record();
        $element = new element($record);

        $data = $this->make_form_data('s3cr3t');
        $json = $element->save_unique_data($data);

        $decoded = json_decode($json, true);
        $this->assertSame(
            's3cr3t',
            \core\encryption::decrypt($decoded['signaturepassword']),
            'The stored encrypted password must decrypt to the original plaintext.'
        );
    }

    /**
     * Test that leaving the password field blank on edit preserves the existing encrypted password.
     *
     * When an administrator reopens the element form, the password field is intentionally
     * left blank. The existing stored (encrypted) password must be preserved rather than
     * overwritten with an empty string.
     *
     * @covers \customcertelement_digitalsignature\element::save_unique_data
     */
    public function test_save_unique_data_preserves_password_when_field_is_blank(): void {
        $encryptedpassword = \core\encryption::encrypt('original_secret');
        $existingdata = json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => $encryptedpassword,
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
            $encryptedpassword,
            $decoded['signaturepassword'],
            'Existing encrypted password must be preserved when the password field is left blank.'
        );
        $this->assertSame(
            'original_secret',
            \core\encryption::decrypt($decoded['signaturepassword']),
            'Preserved password must still decrypt to the original plaintext.'
        );
    }

    /**
     * Test that providing a new password on edit stores the replacement encrypted.
     *
     * @covers \customcertelement_digitalsignature\element::save_unique_data
     */
    public function test_save_unique_data_replaces_password_when_new_value_provided(): void {
        $encryptedpassword = \core\encryption::encrypt('original_secret');
        $existingdata = json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => $encryptedpassword,
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
        $this->assertStringNotContainsString(
            'new_secret',
            $json,
            'The replacement plaintext password must not appear in the persisted JSON.'
        );
        $this->assertSame(
            'new_secret',
            \core\encryption::decrypt($decoded['signaturepassword']),
            'Replacement password must be stored encrypted and decrypt to the new plaintext.'
        );
    }

    /**
     * Test that the upgrade migration encrypts existing plaintext passwords.
     *
     * @covers \xmldb_customcertelement_digitalsignature_upgrade
     */
    public function test_upgrade_encrypts_plaintext_passwords(): void {
        global $DB, $CFG;

        $plaindata = json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => 's3cr3t',
            'signaturelocation' => 'Location',
            'signaturereason' => 'Reason',
            'signaturecontactinfo' => 'info@example.com',
            'width' => 100,
            'height' => 50,
        ]);

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = $DB->get_record(
            'customcert_templates',
            ['contextid' => \context_module::instance($customcert->cmid)->id]
        );
        $pageid = $DB->insert_record('customcert_pages', [
            'templateid' => $template->id,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $elementid = $DB->insert_record('customcert_elements', [
            'pageid' => $pageid,
            'name' => 'Sig',
            'element' => 'digitalsignature',
            'data' => $plaindata,
            'font' => null,
            'fontsize' => null,
            'colour' => null,
            'posx' => 0,
            'posy' => 0,
            'width' => 0,
            'height' => 0,
            'refpoint' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // The upgrade_plugin_savepoint() function lives in upgradelib.php, which is not loaded
        // by the standard PHPUnit bootstrap since it is normally only required during a real
        // upgrade.
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');

        // The test site already has this plugin installed at its current version, so force the
        // recorded version back down to simulate upgrading from an older release; otherwise
        // upgrade_plugin_savepoint() treats setting the same version as a downgrade and throws.
        set_config('version', 2023042400, 'customcertelement_digitalsignature');
        xmldb_customcertelement_digitalsignature_upgrade(2023042400);

        $stored = $DB->get_field('customcert_elements', 'data', ['id' => $elementid]);
        $decoded = json_decode($stored, true);

        $this->assertStringNotContainsString(
            's3cr3t',
            $stored,
            'After upgrade, the plaintext password must not appear in stored data.'
        );
        $this->assertSame(
            's3cr3t',
            \core\encryption::decrypt($decoded['signaturepassword']),
            'After upgrade, the stored value must decrypt to the original plaintext.'
        );
    }

    /**
     * Test that the upgrade migration does not double-encrypt already-migrated values.
     *
     * @covers \xmldb_customcertelement_digitalsignature_upgrade
     */
    public function test_upgrade_does_not_double_encrypt_already_migrated_passwords(): void {
        global $DB, $CFG;

        $encryptedpassword = \core\encryption::encrypt('s3cr3t');
        $encrypteddata = json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => $encryptedpassword,
            'signaturelocation' => 'Location',
            'signaturereason' => 'Reason',
            'signaturecontactinfo' => 'info@example.com',
            'width' => 100,
            'height' => 50,
        ]);

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = $DB->get_record(
            'customcert_templates',
            ['contextid' => \context_module::instance($customcert->cmid)->id]
        );
        $pageid = $DB->insert_record('customcert_pages', [
            'templateid' => $template->id,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $elementid = $DB->insert_record('customcert_elements', [
            'pageid' => $pageid,
            'name' => 'Sig',
            'element' => 'digitalsignature',
            'data' => $encrypteddata,
            'font' => null,
            'fontsize' => null,
            'colour' => null,
            'posx' => 0,
            'posy' => 0,
            'width' => 0,
            'height' => 0,
            'refpoint' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // The upgrade_plugin_savepoint() function lives in upgradelib.php, which is not loaded
        // by the standard PHPUnit bootstrap since it is normally only required during a real
        // upgrade.
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');

        // The test site already has this plugin installed at its current version, so force the
        // recorded version back down to simulate upgrading from an older release; otherwise
        // upgrade_plugin_savepoint() treats setting the same version as a downgrade and throws.
        set_config('version', 2023042400, 'customcertelement_digitalsignature');
        xmldb_customcertelement_digitalsignature_upgrade(2023042400);

        $stored = $DB->get_field('customcert_elements', 'data', ['id' => $elementid]);
        $decoded = json_decode($stored, true);

        $this->assertSame(
            $encryptedpassword,
            $decoded['signaturepassword'],
            'Already-encrypted password must not be modified by the upgrade step.'
        );
        $this->assertSame(
            's3cr3t',
            \core\encryption::decrypt($decoded['signaturepassword']),
            'Already-migrated password must still decrypt correctly after upgrade runs again.'
        );
    }

    /**
     * Test that normalise_restore_password() encrypts a legacy plaintext password.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_restore_password
     */
    public function test_normalise_restore_password_encrypts_plaintext(): void {
        $result = element::normalise_restore_password('s3cr3t');
        $this->assertNotSame(
            's3cr3t',
            $result,
            'Plaintext password must not be returned as-is.'
        );
        $this->assertSame(
            's3cr3t',
            \core\encryption::decrypt($result),
            'Normalised value must decrypt to the original plaintext.'
        );
    }

    /**
     * Test that normalise_restore_password() does not store plaintext in the result.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_restore_password
     */
    public function test_normalise_restore_password_plaintext_absent_from_result(): void {
        $result = element::normalise_restore_password('s3cr3t');
        $this->assertStringNotContainsString(
            's3cr3t',
            $result,
            'The sentinel plaintext password must not appear in the normalised value.'
        );
    }

    /**
     * Test that normalise_restore_password() preserves valid same-site ciphertext.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_restore_password
     */
    public function test_normalise_restore_password_preserves_valid_ciphertext(): void {
        $encrypted = \core\encryption::encrypt('s3cr3t');
        $result = element::normalise_restore_password($encrypted);
        $this->assertSame(
            $encrypted,
            $result,
            'Valid same-site ciphertext must be preserved as-is.'
        );
    }

    /**
     * Test that normalise_restore_password() clears foreign-site ciphertext.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_restore_password
     */
    public function test_normalise_restore_password_clears_foreign_ciphertext(): void {
        // Simulate a sodium: prefixed value that cannot be decrypted on this site.
        $foreignciphertext = \core\encryption::METHOD_SODIUM . ':' . base64_encode('this-is-not-valid-ciphertext');
        $result = element::normalise_restore_password($foreignciphertext);
        $this->assertSame(
            '',
            $result,
            'Foreign-site ciphertext that cannot be decrypted must be cleared.'
        );
    }

    /**
     * Test that normalise_restore_password() clears foreign-site legacy OpenSSL ciphertext.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_restore_password
     */
    public function test_normalise_restore_password_clears_foreign_openssl_ciphertext(): void {
        // Simulate an openssl-aes-256-ctr: prefixed value that cannot be decrypted on this site.
        $foreignciphertext = \core\encryption::METHOD_OPENSSL . ':' . base64_encode('this-is-not-valid-ciphertext');
        $result = element::normalise_restore_password($foreignciphertext);
        $this->assertSame(
            '',
            $result,
            'Foreign-site legacy OpenSSL ciphertext that cannot be decrypted must be cleared.'
        );
    }

    /**
     * Test that normalise_restore_password() handles empty password.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_restore_password
     */
    public function test_normalise_restore_password_handles_empty(): void {
        $result = element::normalise_restore_password('');
        $this->assertSame(
            '',
            $result,
            'Empty password must remain empty after normalisation.'
        );
    }

    /**
     * Test that normalise_restore_password() treats plaintext containing a colon as plaintext.
     *
     * A broad "/^[a-z]+:/" ciphertext heuristic would misclassify a legitimate plaintext
     * password such as "secret:1234" as encrypted data and could clear it during restore.
     * Only the encryption method prefixes this site's core encryption API actually supports
     * (Sodium and legacy OpenSSL) must be treated as ciphertext.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_restore_password
     */
    public function test_normalise_restore_password_treats_plaintext_with_colon_as_plaintext(): void {
        $plaintext = 'secret:1234';
        $result = element::normalise_restore_password($plaintext);
        $this->assertNotSame(
            $plaintext,
            $result,
            'Plaintext containing a colon must still be encrypted, not stored as-is.'
        );
        $this->assertStringNotContainsString(
            $plaintext,
            json_encode(['signaturepassword' => $result]),
            'The plaintext password must not appear in the persisted JSON.'
        );
        $this->assertSame(
            $plaintext,
            \core\encryption::decrypt($result),
            'The stored value must decrypt back to the exact original plaintext.'
        );
    }

    /**
     * Test that normalise_restore_password() treats another plaintext-with-colon value correctly.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_restore_password
     */
    public function test_normalise_restore_password_treats_another_plaintext_with_colon_as_plaintext(): void {
        $plaintext = 'password:hunter2';
        $result = element::normalise_restore_password($plaintext);
        $this->assertNotSame(
            $plaintext,
            $result,
            'Plaintext containing a colon must still be encrypted, not stored as-is.'
        );
        $this->assertSame(
            $plaintext,
            \core\encryption::decrypt($result),
            'The stored value must decrypt back to the exact original plaintext.'
        );
    }

    /**
     * Test that render() decrypts the stored password before passing it to the PDF signing
     * library, and never passes the encrypted stored representation.
     *
     * @covers \customcertelement_digitalsignature\element::render
     */
    public function test_render_uses_decrypted_password_for_signing(): void {
        $syscontextid = \context_system::instance()->id;
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $syscontextid,
            'component' => 'mod_customcert',
            'filearea' => 'signature',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'test.crt',
        ];
        $fs->create_file_from_string($filerecord, 'fake-certificate-content');

        $plaintext = 'signing_secret';
        $encryptedpassword = \core\encryption::encrypt($plaintext);

        $data = json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => $encryptedpassword,
            'signaturelocation' => 'Location',
            'signaturereason' => 'Reason',
            'signaturecontactinfo' => 'info@example.com',
            'width' => 100,
            'height' => 50,
            'signaturecontextid' => $syscontextid,
            'signaturefilearea' => 'signature',
            'signatureitemid' => 0,
            'signaturefilepath' => '/',
            'signaturefilename' => 'test.crt',
        ]);

        $record = $this->make_element_record($data);
        $element = new element($record);

        $capturedpassword = null;
        // The setSignature()/setSignatureAppearance() methods are inherited from TCPDF, which
        // is loaded via the pdflib.php require above, so onlyMethods() (not addMethods()) is
        // required to stub them.
        $pdf = $this->getMockBuilder(\pdf::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setSignature', 'setSignatureAppearance'])
            ->getMock();
        $pdf->expects($this->once())
            ->method('setSignature')
            ->willReturnCallback(function (...$args) use (&$capturedpassword) {
                $capturedpassword = $args[2];
                return null;
            });

        $element->render($pdf, false, new \stdClass());

        $this->assertSame(
            $plaintext,
            $capturedpassword,
            'The PDF signing library must receive the decrypted plaintext password.'
        );
        $this->assertNotSame(
            $encryptedpassword,
            $capturedpassword,
            'The encrypted stored representation must never be passed to setSignature().'
        );
    }
}
