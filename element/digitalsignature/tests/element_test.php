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

declare(strict_types=1);

namespace customcertelement_digitalsignature;

use advanced_testcase;
use context_system;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\export\template_appendix_manager_interface;
use mod_customcert\export\template_import_logger_interface;
use pdf;
use stdClass;

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
 */
final class element_test extends advanced_testcase {
    /**
     * Helper to build a minimal element DB record.
     *
     * @param array $override
     * @return stdClass
     */
    private function make_record(array $override = []): stdClass {
        return (object) array_merge([
            'id' => 1,
            'pageid' => 1,
            'name' => 'Digital signature',
            'element' => 'digitalsignature',
            'data' => null,
            'font' => null,
            'fontsize' => null,
            'colour' => null,
            'posx' => 10,
            'posy' => 10,
            'width' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $override);
    }

    /**
     * Helper to build an exporter instance with mocked logger and file manager dependencies.
     *
     * @return exporter
     */
    private function make_exporter(): exporter {
        $logger = $this->createMock(template_import_logger_interface::class);
        $filemng = $this->createMock(template_appendix_manager_interface::class);
        return new exporter('digitalsignature', $logger, $filemng);
    }

    /**
     * Test that the element can be instantiated.
     *
     * @covers \customcertelement_digitalsignature\element
     */
    public function test_instantiation(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     *
     * @covers \customcertelement_digitalsignature\element
     */
    public function test_implements_interfaces(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test that normalise_data() returns expected keys.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_data
     */
    public function test_normalise_data_returns_expected_keys(): void {
        $el = new element($this->make_record());
        $formdata = (object) [
            'width' => 100,
            'height' => 50,
        ];
        $result = $el->normalise_data($formdata);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
    }

    /**
     * Test that validate() returns an empty array.
     *
     * @covers \customcertelement_digitalsignature\element::validate
     */
    public function test_validate_returns_empty_array(): void {
        $el = new element($this->make_record());
        $this->assertSame([], $el->validate([]));
    }

    /**
     * Test that render_html() returns an empty string (digital signature has no HTML preview).
     *
     * @covers \customcertelement_digitalsignature\element::render_html
     */
    public function test_render_html_returns_empty_string(): void {
        $el = new element($this->make_record());
        $html = $el->render_html();
        $this->assertIsString($html);
        $this->assertSame('', $html);
    }

    /**
     * Test that get_width() returns the stored width value.
     *
     * @covers \customcertelement_digitalsignature\element::get_width
     */
    public function test_get_width_returns_stored_value(): void {
        $el = new element($this->make_record([
            'data' => json_encode(['width' => 100, 'height' => 50]),
        ]));
        $this->assertSame(100, $el->get_width());
    }

    /**
     * Test that get_height() returns the stored height value.
     *
     * @covers \customcertelement_digitalsignature\element::get_height
     */
    public function test_get_height_returns_stored_value(): void {
        $el = new element($this->make_record([
            'data' => json_encode(['width' => 100, 'height' => 50]),
        ]));
        $this->assertSame(50, $el->get_height());
    }

    /**
     * Test that has_save_and_continue() returns true.
     *
     * @covers \customcertelement_digitalsignature\element::has_save_and_continue
     */
    public function test_has_save_and_continue(): void {
        $el = new element($this->make_record());
        $this->assertTrue($el->has_save_and_continue());
    }

    /**
     * Test that get_type() returns 'digitalsignature'.
     *
     * @covers \customcertelement_digitalsignature\element
     */
    public function test_get_type(): void {
        $el = new element($this->make_record());
        $this->assertSame('digitalsignature', $el->get_type());
    }

    /**
     * Test that normalise_data() stores the password when provided on first save.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_data
     */
    public function test_normalise_data_does_not_store_plaintext_password(): void {
        $el = new element($this->make_record());
        $formdata = (object) [
            'signaturename' => 'Signer',
            'signaturepassword' => 's3cr3t',
            'signaturelocation' => 'Location',
            'signaturereason' => 'Reason',
            'signaturecontactinfo' => 'info@example.com',
            'width' => 100,
            'height' => 50,
        ];
        $result = $el->normalise_data($formdata);
        $this->assertNotSame(
            's3cr3t',
            $result['signaturepassword'],
            'The plaintext password must not be stored directly.'
        );
        $this->assertSame(
            's3cr3t',
            \core\encryption::decrypt($result['signaturepassword']),
            'The stored value must decrypt to the original plaintext.'
        );
    }

    /**
     * Test that normalise_data() preserves the existing password when the field is left blank.
     *
     * When an administrator reopens the element form, the password field is intentionally
     * left blank. The existing stored password must be preserved rather than overwritten
     * with an empty string.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_data
     */
    public function test_normalise_data_preserves_password_when_field_is_blank(): void {
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
        $el = new element($this->make_record(['data' => $existingdata]));
        $formdata = (object) [
            'signaturename' => 'Signer',
            'signaturepassword' => '',
            'signaturelocation' => 'Location',
            'signaturereason' => 'Reason',
            'signaturecontactinfo' => 'info@example.com',
            'width' => 100,
            'height' => 50,
        ];
        $result = $el->normalise_data($formdata);
        $this->assertSame(
            $encryptedpassword,
            $result['signaturepassword'],
            'Existing encrypted password must be preserved when the password field is left blank.'
        );
        $this->assertSame(
            'original_secret',
            \core\encryption::decrypt($result['signaturepassword']),
            'Preserved password must still decrypt to the original plaintext.'
        );
    }

    /**
     * Test that normalise_data() replaces the password when a new value is provided.
     *
     * @covers \customcertelement_digitalsignature\element::normalise_data
     */
    public function test_normalise_data_replaces_password_when_new_value_provided(): void {
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
        $el = new element($this->make_record(['data' => $existingdata]));
        $formdata = (object) [
            'signaturename' => 'Signer',
            'signaturepassword' => 'new_secret',
            'signaturelocation' => 'Location',
            'signaturereason' => 'Reason',
            'signaturecontactinfo' => 'info@example.com',
            'width' => 100,
            'height' => 50,
        ];
        $result = $el->normalise_data($formdata);
        $this->assertNotSame(
            'new_secret',
            $result['signaturepassword'],
            'The replacement plaintext password must not be stored directly.'
        );
        $this->assertSame(
            'new_secret',
            \core\encryption::decrypt($result['signaturepassword']),
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

        $this->resetAfterTest();

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

        $this->resetAfterTest();

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
     * Test that the upgrade migration correctly encrypts a plaintext password of "0".
     *
     * empty('0') evaluates to true in PHP, so a naive empty() check on signaturepassword would
     * incorrectly skip migrating a valid password consisting solely of "0".
     *
     * @covers \xmldb_customcertelement_digitalsignature_upgrade
     */
    public function test_upgrade_encrypts_zero_string_password(): void {
        global $DB, $CFG;

        $this->resetAfterTest();

        $plaindata = json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => '0',
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

        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');

        set_config('version', 2023042400, 'customcertelement_digitalsignature');
        xmldb_customcertelement_digitalsignature_upgrade(2023042400);

        $stored = $DB->get_field('customcert_elements', 'data', ['id' => $elementid]);
        $decoded = json_decode($stored, true);

        $this->assertNotSame(
            '0',
            $decoded['signaturepassword'],
            'A plaintext password of "0" must be encrypted, not left as-is or skipped.'
        );
        $this->assertSame(
            '0',
            \core\encryption::decrypt($decoded['signaturepassword']),
            'After upgrade, the stored value must decrypt back to the original "0" password.'
        );
    }

    /**
     * Test that the upgrade migration does not error, and does not fabricate a password, when
     * signaturepassword is absent from the stored element data entirely.
     *
     * This is distinct from an empty-string password: it specifically exercises the
     * array_key_exists() check, as a missing array key and an empty string are not the same
     * thing in PHP.
     *
     * @covers \xmldb_customcertelement_digitalsignature_upgrade
     */
    public function test_upgrade_skips_missing_signaturepassword_key(): void {
        global $DB, $CFG;

        $this->resetAfterTest();

        // Deliberately omit signaturepassword entirely, rather than setting it to ''.
        $data = json_encode([
            'signaturename' => 'Signer',
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
            'data' => $data,
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

        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');

        set_config('version', 2023042400, 'customcertelement_digitalsignature');
        xmldb_customcertelement_digitalsignature_upgrade(2023042400);

        $stored = $DB->get_field('customcert_elements', 'data', ['id' => $elementid]);
        $decoded = json_decode($stored, true);

        $this->assertArrayNotHasKey(
            'signaturepassword',
            $decoded,
            'The upgrade must not fabricate a signaturepassword value when the key is absent entirely.'
        );
        $this->assertSame(
            'Signer',
            $decoded['signaturename'],
            'Other element data must be left untouched by the upgrade.'
        );
    }

    /**
     * Test that the upgrade migration clears ciphertext that cannot be decrypted with this
     * site's key, rather than double-encrypting it.
     *
     * Decryption can fail for a value that is already encrypted but was encrypted with a
     * different site's key, or is corrupt. Encrypting such a value again would produce ciphertext
     * that, once decrypted, yields the previous unusable ciphertext rather than the actual
     * private-key password.
     *
     * @covers \xmldb_customcertelement_digitalsignature_upgrade
     */
    public function test_upgrade_clears_undecryptable_ciphertext_password(): void {
        global $DB, $CFG;

        $this->resetAfterTest();

        // Build a value that is a structurally real, correctly-shaped ciphertext (correct
        // method prefix, valid base64, correct length) so it is unambiguously recognisable as
        // Moodle-encrypted data, but fails Sodium's integrity check on decryption. This
        // simulates ciphertext that was encrypted with a different site's key, or has become
        // corrupted, rather than an arbitrary malformed string.
        $realciphertext = \core\encryption::encrypt('some-other-secret');
        $prefixlength = strlen(\core\encryption::METHOD_SODIUM) + 1;
        $rawbytes = base64_decode(substr($realciphertext, $prefixlength));
        $rawbytes[strlen($rawbytes) - 1] = chr(ord($rawbytes[strlen($rawbytes) - 1]) ^ 0xFF);
        $undecryptable = \core\encryption::METHOD_SODIUM . ':' . base64_encode($rawbytes);
        $data = json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => $undecryptable,
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
            'data' => $data,
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

        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');

        set_config('version', 2023042400, 'customcertelement_digitalsignature');
        xmldb_customcertelement_digitalsignature_upgrade(2023042400);

        $stored = $DB->get_field('customcert_elements', 'data', ['id' => $elementid]);
        $decoded = json_decode($stored, true);

        $this->assertSame(
            '',
            $decoded['signaturepassword'],
            'Ciphertext that cannot be decrypted with this site\'s key must be cleared, not ' .
                'double-encrypted.'
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
     * must be treated as ciphertext.
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
     * Test that the exporter does not include signaturepassword in its field definitions.
     *
     * @covers \customcertelement_digitalsignature\exporter
     */
    public function test_exporter_does_not_include_signaturepassword(): void {
        $exporter = $this->make_exporter();
        $exported = $exporter->export(json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => 's3cr3t',
            'signaturelocation' => 'Location',
            'signaturereason' => 'Reason',
            'signaturecontactinfo' => 'info@example.com',
            'width' => 100,
            'height' => 50,
        ]));
        $this->assertArrayNotHasKey(
            'signaturepassword',
            $exported,
            'Template export must not include signaturepassword.'
        );
        $this->assertStringNotContainsString(
            's3cr3t',
            json_encode($exported),
            'The plaintext password must not appear anywhere in the exported data.'
        );
    }

    /**
     * Test that the exporter still exports other fields correctly.
     *
     * @covers \customcertelement_digitalsignature\exporter
     */
    public function test_exporter_exports_other_fields(): void {
        $exporter = $this->make_exporter();
        $exported = $exporter->export(json_encode([
            'signaturename' => 'Signer',
            'signaturepassword' => 's3cr3t',
            'signaturelocation' => 'Location',
            'signaturereason' => 'Reason',
            'signaturecontactinfo' => 'info@example.com',
            'width' => 100.0,
            'height' => 50.0,
        ]));
        $this->assertSame('Signer', $exported['signaturename']['value']);
        $this->assertSame('Location', $exported['signaturelocation']['value']);
        $this->assertSame('Reason', $exported['signaturereason']['value']);
        $this->assertSame('info@example.com', $exported['signaturecontactinfo']['value']);
    }

    /**
     * Test that importing a template that contains signaturepassword discards it.
     *
     * @covers \customcertelement_digitalsignature\exporter
     */
    public function test_importer_discards_signaturepassword(): void {
        $exporter = $this->make_exporter();
        // Simulate an old/external template that contains signaturepassword.
        // convert_for_import only processes fields defined in get_fields(), so
        // signaturepassword is silently discarded because it is no longer a defined field.
        $importdata = [
            '$' => [],
            'signature$' => [],
            'signaturename' => ['value' => 'Signer'],
            'signaturelocation' => ['value' => 'Location'],
            'signaturereason' => ['value' => 'Reason'],
            'signaturecontactinfo' => ['value' => 'info@example.com'],
            'width' => ['value' => 100.0],
            'height' => ['value' => 50.0],
            // Extra field that old templates may contain — must be ignored.
            'signaturepassword' => ['value' => 's3cr3t'],
        ];
        $imported = json_decode($exporter->convert_for_import($importdata), true);
        $this->assertArrayNotHasKey(
            'signaturepassword',
            $imported,
            'Import must discard signaturepassword from template data.'
        );
        $this->assertStringNotContainsString(
            's3cr3t',
            json_encode($imported),
            'The plaintext password must not appear in imported data.'
        );
    }

    /**
     * Test that render() decrypts the stored password before passing it to the PDF signing
     * library, and never passes the encrypted stored representation.
     *
     * @covers \customcertelement_digitalsignature\element::render
     */
    public function test_render_uses_decrypted_password_for_signing(): void {
        $this->resetAfterTest();

        $syscontextid = context_system::instance()->id;
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

        $el = new element($this->make_record(['data' => $data]));

        $capturedpassword = null;
        // The setSignature()/setSignatureAppearance() methods are inherited from TCPDF, which
        // is loaded via the pdflib.php require above, so onlyMethods() (not addMethods()) is
        // required to stub them.
        $pdf = $this->getMockBuilder(pdf::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setSignature', 'setSignatureAppearance'])
            ->getMock();
        $pdf->expects($this->once())
            ->method('setSignature')
            ->willReturnCallback(function (...$args) use (&$capturedpassword) {
                $capturedpassword = $args[2];
                return null;
            });

        $el->render($pdf, false, new stdClass());

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
