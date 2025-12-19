<?php
// This file is part of Moodle - http://moodle.org/
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
 * Unit tests for mod_customcert lib.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2023 Diego Felipe Monroy <dfelipe.monroyc@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/customcert/lib.php');
require_once($CFG->libdir . '/componentlib.class.php');

/**
 * Unit tests for mod_customcert lib.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2023 Diego Felipe Monroy <dfelipe.monroyc@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends advanced_testcase {
    /**
     * Test set up.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Tests apply_runtime_language() with valid and invalid languages.
     *
     * @covers ::mod_customcert_apply_runtime_language
     */
    public function test_mod_customcert_apply_runtime_language(): void {
        global $USER;

        $user1 = $this->getDataGenerator()->create_user();
        $USER = $user1;

        // 1) Not installed: should NOT switch.
        $this->assertFalse(mod_customcert_apply_runtime_language('pt_br'));

        // 2) English usually installed: switching to current language is a no-op.
        $current = current_language();
        $this->assertFalse(mod_customcert_apply_runtime_language($current));

        // 3) Install a single non-English language and verify switching works.
        $this->install_languages(['cs']);
        $this->assertTrue(mod_customcert_apply_runtime_language('cs'));
    }

    /**
     * Test get language to use certificate language.
     *
     * @covers ::mod_customcert_get_language_to_use
     */
    public function test_get_language_to_use_precedence_cert_forced(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->ensure_base_langs();

        $user = $this->getDataGenerator()->create_user(['lang' => 'es']);
        $USER = $user;

        $customcert = (object)[
            'language' => 'es_co', // Cert forces Colombian Spanish.
        ];

        $resolved = mod_customcert_get_language_to_use($customcert, $user, 'fr');
        $this->assertSame('es_co', $resolved);
    }

    /**
     * Test get language to use course language.
     *
     * @covers ::mod_customcert_get_language_to_use
     */
    public function test_get_language_to_use_precedence_course_when_cert_empty(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->ensure_base_langs();

        $user = $this->getDataGenerator()->create_user(['lang' => 'es']);
        $USER = $user;

        $customcert = (object)[
            'language' => '', // Use user preferences.
        ];

        $resolved = mod_customcert_get_language_to_use($customcert, $user, 'fr');
        $this->assertSame('fr', $resolved);
    }

    /**
     * Test what language to use when cert and course is empty.
     *
     * @covers ::mod_customcert_get_language_to_use
     */
    public function test_get_language_to_use_user_when_cert_and_course_empty(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->ensure_base_langs();

        $user = $this->getDataGenerator()->create_user(['lang' => 'es']);
        $USER = $user;

        $customcert = (object)[
            'language' => '',
        ];

        $resolved = mod_customcert_get_language_to_use($customcert, $user, null);
        $this->assertSame('es', $resolved);
    }

    /**
     * Test that site default is used when everything else is empty.
     *
     * @covers ::mod_customcert_get_language_to_use
     */
    public function test_get_language_to_use_site_default_when_all_empty(): void {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->ensure_base_langs();

        $user = $this->getDataGenerator()->create_user(['lang' => '']);
        $USER = $user;

        $customcert = (object)['language' => ''];

        $resolved = mod_customcert_get_language_to_use($customcert, $user, null);
        $this->assertSame($CFG->lang, $resolved);
    }

    /**
     * Test that we use the global user when user is not passed.
     *
     * @covers ::mod_customcert_get_language_to_use
     */
    public function test_get_language_to_use_defaults_to_global_user_when_user_null(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->ensure_base_langs();

        $user = $this->getDataGenerator()->create_user(['lang' => 'es']);
        $USER = $user;

        $customcert = (object)['language' => ''];

        $resolved = mod_customcert_get_language_to_use($customcert, null, null);
        $this->assertSame('es', $resolved);
    }

    /**
     * Test we do not switch language if it is already active.
     *
     * @covers ::mod_customcert_apply_runtime_language
     */
    public function test_apply_runtime_language_noop_for_same_language(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $current = current_language();
        $forced = mod_customcert_apply_runtime_language($current);
        $this->assertFalse($forced, 'Should not switch when language is already active.');
    }

    /**
     * Test we do not switch language if it is invalid.
     *
     * @covers ::mod_customcert_apply_runtime_language
     */
    public function test_apply_runtime_language_invalid_code_is_ignored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $before = current_language();
        $forced = mod_customcert_apply_runtime_language('xx_invalid');
        $this->assertFalse($forced);
        $this->assertSame($before, current_language());
    }

    /**
     * Test we switch and can restore language successfully.
     *
     * @covers ::mod_customcert_apply_runtime_language
     */
    public function test_apply_runtime_language_switch_and_restore(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->install_languages(['cs']);

        $before = current_language();

        $forced = mod_customcert_apply_runtime_language('cs');
        $this->assertTrue($forced);
        $this->assertSame('cs', current_language());

        // Restore to before.
        $restored = mod_customcert_apply_runtime_language($before);
        $this->assertTrue($restored);
        $this->assertSame($before, current_language());
    }

    /**
     * Resolver may return a code that is not installed; apply() decides if it can switch.
     *
     * @covers ::mod_customcert_get_language_to_use
     * @covers ::mod_customcert_apply_runtime_language
     */
    public function test_resolve_returns_uninstalled_code_but_apply_refuses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = (object)['lang' => 'xx_notinstalled']; // Avoid create_user() cleaning.
        $customcert = (object)['language' => ''];

        $resolved = mod_customcert_get_language_to_use($customcert, $user, null);
        $this->assertSame('xx_notinstalled', $resolved);

        $before = current_language();
        $forced = mod_customcert_apply_runtime_language($resolved);
        $this->assertFalse($forced);
        $this->assertSame($before, current_language());
    }

    /**
     * Install language packs by lang codes.
     *
     * @param array $codes
     * @return bool
     */
    private function install_languages(array $codes): bool {
        \core_php_time_limit::raise();
        get_string_manager()->reset_caches();

        $controller = new \tool_langimport\controller();
        try {
            $controller->install_languagepacks($codes);
            return true;
        } catch (\moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
        }

        return false;
    }

    /**
     * Ensures the base set of language packs are installed.
     */
    private function ensure_base_langs(): void {
        // Only what we truly need for test coverage.
        $this->install_languages(['es', 'fr', 'cs']);
    }
}
