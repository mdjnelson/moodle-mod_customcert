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
 * Unit tests for the helper class.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use advanced_testcase;
use context_system;
use moodle_exception;

/**
 * Unit tests for the helper class.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class helper_test extends advanced_testcase {
    /**
     * Test a user can view their own certificates.
     *
     * @covers \mod_customcert\helper::require_view_user_certificates
     */
    public function test_require_view_user_certificates_own(): void {
        $this->resetAfterTest();
        $this->expectNotToPerformAssertions();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        helper::require_view_user_certificates((int)$user->id);
    }

    /**
     * Test a user without the capability cannot view another user's certificates.
     *
     * @covers \mod_customcert\helper::require_view_user_certificates
     */
    public function test_require_view_user_certificates_other_user_denied(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('You are not allowed to view these certificates');
        helper::require_view_user_certificates((int)$other->id);
    }

    /**
     * Test a user with the capability can view another user's certificates.
     *
     * @covers \mod_customcert\helper::require_view_user_certificates
     */
    public function test_require_view_user_certificates_other_user_allowed(): void {
        $this->resetAfterTest();
        $this->expectNotToPerformAssertions();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = context_system::instance();
        assign_capability('mod/customcert:viewallcertificates', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);

        helper::require_view_user_certificates((int)$other->id);
    }

    /**
     * Test my_certificates.php authorises the request after login and before looking up any certificate.
     *
     * @coversNothing
     */
    public function test_my_certificates_authorises_before_certificate_lookup(): void {
        $source = file_get_contents(__DIR__ . '/../my_certificates.php');

        $login = strpos($source, 'require_login(');
        $authorise = strpos($source, 'helper::require_view_user_certificates(');
        $lookup = strpos($source, "required_param('certificateid'");

        $this->assertNotFalse($login);
        $this->assertNotFalse($authorise);
        $this->assertNotFalse($lookup);
        $this->assertLessThan($authorise, $login);
        $this->assertLessThan($lookup, $authorise);
    }
}
