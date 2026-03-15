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
namespace mod_customcert;
use advanced_testcase;
use mod_customcert\export\datatypes\format_exception;
use mod_customcert\export\datatypes\unimported_field;
use mod_customcert\export\datatypes\user_field;
use mod_customcert\export\table_exporter;
/**
 * Tests for user_field, unimported_field and table_exporter.
 *
 * @package    mod_customcert
 * @category   test
 * @group      mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\export\datatypes\user_field
 * @covers     \mod_customcert\export\datatypes\unimported_field
 * @covers     \mod_customcert\export\table_exporter
 */
final class export_remaining_fields_test extends advanced_testcase {
    // Unimported_field tests.

    /**
     * Test unimported_field import always throws.
     */
    public function test_unimported_field_import_throws(): void {
        $field = new unimported_field();
        $this->expectException(format_exception::class);
        $field->import(['value' => 'anything']);
    }

    /**
     * Test unimported_field export returns empty array.
     */
    public function test_unimported_field_export_returns_empty(): void {
        $field = new unimported_field();
        $this->assertSame([], $field->export('anything'));
    }

    /**
     * Test unimported_field fallback returns empty string.
     */
    public function test_unimported_field_fallback_returns_empty_string(): void {
        $field = new unimported_field();
        $this->assertSame('', $field->get_fallback());
    }

    // User_field tests.

    /**
     * Test user_field export returns userid and fullname for a valid user.
     */
    public function test_user_field_export_valid_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Jane', 'lastname' => 'Doe']);
        $field = new user_field();
        $result = $field->export($user->id);
        $this->assertSame((int) $user->id, $result['userid']);
        $this->assertSame(fullname($user), $result['fullname']);
    }

    /**
     * Test user_field export returns empty array for empty value.
     */
    public function test_user_field_export_empty_value(): void {
        $this->resetAfterTest();
        $field = new user_field();
        $this->assertSame([], $field->export(0));
    }

    /**
     * Test user_field export returns empty array for non-existent user.
     */
    public function test_user_field_export_nonexistent_user(): void {
        $this->resetAfterTest();
        $field = new user_field();
        $this->assertSame([], $field->export(99999999));
    }

    /**
     * Test user_field import succeeds when userid and fullname match.
     */
    public function test_user_field_import_valid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'John', 'lastname' => 'Smith']);
        $field = new user_field();
        $result = $field->import(['userid' => (int) $user->id, 'fullname' => fullname($user)]);
        $this->assertSame((int) $user->id, $result);
    }

    /**
     * Test user_field import throws when user does not exist.
     */
    public function test_user_field_import_nonexistent_user(): void {
        $this->resetAfterTest();
        $field = new user_field();
        $this->expectException(format_exception::class);
        $field->import(['userid' => 99999999, 'fullname' => 'Nobody']);
    }

    /**
     * Test user_field import throws when fullname does not match.
     */
    public function test_user_field_import_fullname_mismatch(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Brown']);
        $field = new user_field();
        $this->expectException(format_exception::class);
        $field->import(['userid' => (int) $user->id, 'fullname' => 'Wrong Name']);
    }

    /**
     * Test user_field import throws when userid is missing from data.
     */
    public function test_user_field_import_missing_userid(): void {
        $this->resetAfterTest();
        $field = new user_field();
        $this->expectException(format_exception::class);
        $field->import(['fullname' => 'Someone']);
    }

    /**
     * Test user_field fallback returns empty string.
     */
    public function test_user_field_fallback_returns_empty_string(): void {
        $field = new user_field();
        $this->assertSame('', $field->get_fallback());
    }

    // Table_exporter tests.

    /**
     * Test table_exporter export returns requested fields from a record.
     */
    public function test_table_exporter_export_returns_fields(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Test', 'lastname' => 'User']);
        $exporter = new table_exporter('user');
        $result = $exporter->export((int) $user->id, ['firstname', 'lastname']);
        $this->assertSame('Test', $result['firstname']);
        $this->assertSame('User', $result['lastname']);
    }

    /**
     * Test table_exporter tablename property is set correctly.
     */
    public function test_table_exporter_tablename_property(): void {
        $exporter = new table_exporter('customcert_pages');
        $this->assertSame('customcert_pages', $exporter->tablename);
    }
}
