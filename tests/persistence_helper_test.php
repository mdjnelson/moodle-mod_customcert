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
use mod_customcert\element as legacy_base_element;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\service\persistence_helper;
use mod_customcert\tests\fixtures\legacy_plain_string_element;
use mod_customcert\tests\fixtures\minimal_persistable_element;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/legacy_plain_string_element.php');
require_once(__DIR__ . '/fixtures/minimal_persistable_element.php');

/**
 * Unit tests for the persistence_helper.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_customcert\service\persistence_helper::to_json_data
 * @covers \mod_customcert\service\persistence_helper::to_object_json
 */
final class persistence_helper_test extends advanced_testcase {
    /**
     * Data provider for to_object_json() invariant tests.
     *
     * Every case must produce a JSON object (associative array when decoded).
     *
     * @return array<string, array{mixed, mixed}>
     */
    public static function to_object_json_provider(): array {
        return [
            'plain string'        => ['hello', ['value' => 'hello']],
            'empty string'        => ['', ['value' => '']],
            'int'                 => [42, ['value' => 42]],
            'bool true'           => [true, ['value' => true]],
            'bool false'          => [false, ['value' => false]],
            'null'                => [null, null], // Null input decodes to empty object (decoded as []).
            'JSON scalar string'  => ['"scalar"', ['value' => '"scalar"']],
            'JSON list string'    => ['["a","b"]', ['value' => '["a","b"]']],
            'JSON object string'  => ['{"k":"v"}', ['k' => 'v']],
            // Empty object string must remain object-shaped, not become {"value":"{}"}.
            'JSON empty object string' => ['{}', null], // Decodes to [] (empty object); verifies it stays object JSON.
            // Empty PHP array is a list; it is wrapped to preserve the object invariant.
            'PHP empty list array'     => [[], ['value' => []]],
            'PHP list array'      => [['a', 'b'], ['value' => ['a', 'b']]],
            'associative array'   => [['k' => 'v', 'n' => 1], ['k' => 'v', 'n' => 1]],
        ];
    }

    /**
     * to_object_json() must always return a JSON object string.
     *
     * @dataProvider to_object_json_provider
     * @param mixed $input
     * @param mixed $expected decoded result (null means "just verify it is an empty object")
     */
    public function test_to_object_json_always_returns_object(mixed $input, mixed $expected): void {
        $json = persistence_helper::to_object_json($input);
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded, "Expected JSON object, got: $json");
        // An empty object {} decodes to [] which array_is_list considers a list; allow that special case.
        if ($decoded !== []) {
            $this->assertFalse(array_is_list($decoded), "Expected associative array (object JSON), got list: $json");
        }
        if ($expected !== null) {
            $this->assertEquals($expected, $decoded);
        }
    }

    /**
     * Persistable elements should return JSON from normalise_data().
     */
    public function test_persistable_path_returns_json(): void {
        $this->resetAfterTest();

        // Minimal persistable element stub.
        $persistable = new minimal_persistable_element();

        $form = (object)['text' => 'Hello helper'];
        $json = persistence_helper::to_json_data($persistable, $form);
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Hello helper', $decoded['value'] ?? null);
    }

    /**
     * Legacy elements should be JSON-encoded with a value envelope when returning a scalar string.
     */
    public function test_legacy_path_scalar_string_is_wrapped(): void {
        $this->resetAfterTest();

        // Anonymous legacy element that returns a plain string.
        $legacy = new legacy_plain_string_element((object)['id' => null, 'pageid' => 0, 'name' => 'L', 'data' => null]);

        $form = new stdClass();
        $json = persistence_helper::to_json_data($legacy, $form);
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('plainstring', $decoded['value'] ?? null);
    }
}
