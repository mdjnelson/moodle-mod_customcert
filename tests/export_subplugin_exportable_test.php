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
use mod_customcert\export\subplugin_exportable;
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\export\template_appendix_manager_interface;
use mod_customcert\export\datatypes\field_interface;
use mod_customcert\export\datatypes\string_field;
use mod_customcert\export\datatypes\format_error;

/**
 * Tests for subplugin_exportable.
 *
 * @package    mod_customcert
 * @category   test
 * @group      mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\export\subplugin_exportable
 */
final class export_subplugin_exportable_test extends advanced_testcase {
    /**
     * Build a minimal concrete subplugin_exportable with given fields.
     *
     * @param array $fields
     * @return subplugin_exportable
     */
    private function make_exportable(array $fields): subplugin_exportable {
        $logger = $this->createMock(template_import_logger_interface::class);
        $filemng = $this->createMock(template_appendix_manager_interface::class);
        return new class ('testplugin', $logger, $filemng, $fields) extends subplugin_exportable {
            /** @var array Test fields for this anonymous exportable. */
            private array $testfields;

            /**
             * Constructor.
             *
             * @param string $pluginname Plugin name.
             * @param template_import_logger_interface $logger Logger.
             * @param template_appendix_manager_interface $filemng File manager.
             * @param array $fields Fields to use.
             */
            public function __construct(
                string $pluginname,
                template_import_logger_interface $logger,
                template_appendix_manager_interface $filemng,
                array $fields,
            ) {
                parent::__construct($pluginname, $logger, $filemng);
                $this->testfields = $fields;
            }

            /**
             * Returns the test fields.
             *
             * @return field_interface[]
             */
            protected function get_fields(): array {
                return $this->testfields;
            }
        };
    }

    /**
     * Test read_custom_data returns empty array for empty string.
     */
    public function test_read_custom_data_empty_string(): void {
        $exportable = $this->make_exportable([]);
        $this->assertSame([], $exportable->read_custom_data(''));
    }

    /**
     * Test read_custom_data decodes valid JSON array.
     */
    public function test_read_custom_data_valid_json_array(): void {
        $exportable = $this->make_exportable([]);
        $result = $exportable->read_custom_data('{"foo":"bar"}');
        $this->assertSame(['foo' => 'bar'], $result);
    }

    /**
     * Test read_custom_data wraps scalar JSON in value key.
     */
    public function test_read_custom_data_scalar_json(): void {
        $exportable = $this->make_exportable([]);
        $result = $exportable->read_custom_data('"hello"');
        $this->assertSame(['value' => 'hello'], $result);
    }

    /**
     * Test read_custom_data wraps non-JSON string in value key.
     */
    public function test_read_custom_data_non_json(): void {
        $exportable = $this->make_exportable([]);
        $result = $exportable->read_custom_data('plaintext');
        $this->assertSame(['value' => 'plaintext'], $result);
    }

    /**
     * Test get_relevant_data returns direct value for plain key.
     */
    public function test_get_relevant_data_plain_key(): void {
        $exportable = $this->make_exportable([]);
        $data = ['color' => 'red', 'size' => 'large'];
        $this->assertSame('red', $exportable->get_relevant_data('color', $data));
    }

    /**
     * Test get_relevant_data returns null for missing plain key.
     */
    public function test_get_relevant_data_missing_key(): void {
        $exportable = $this->make_exportable([]);
        $this->assertNull($exportable->get_relevant_data('missing', []));
    }

    /**
     * Test get_relevant_data expands $ placeholder into file subkeys.
     */
    public function test_get_relevant_data_dollar_placeholder(): void {
        $exportable = $this->make_exportable([]);
        $data = [
            'img_contextid'  => 1,
            'img_component'  => 'mod_customcert',
            'img_filearea'   => 'image',
            'img_itemid'     => 0,
            'img_filepath'   => '/',
            'img_filename'   => 'test.png',
        ];
        $result = $exportable->get_relevant_data('img_$', $data);
        $this->assertSame([
            'contextid' => 1,
            'component' => 'mod_customcert',
            'filearea'  => 'image',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.png',
        ], $result);
    }

    /**
     * Test convert_for_import encodes fields to JSON.
     */
    public function test_convert_for_import_encodes_fields(): void {
        $fields = ['title' => new string_field(true, '')];
        $exportable = $this->make_exportable($fields);
        $result = $exportable->convert_for_import(['title' => ['value' => 'My cert']]);
        $this->assertSame(['title' => 'My cert'], json_decode($result, true));
    }

    /**
     * Test convert_for_import uses fallback on format_exception and logs warning.
     */
    public function test_convert_for_import_uses_fallback_on_exception(): void {
        $logger = $this->createMock(template_import_logger_interface::class);
        $logger->expects($this->once())->method('warning');
        $filemng = $this->createMock(template_appendix_manager_interface::class);
        $fields = ['title' => new string_field(false, 'FALLBACK')];
        $exportable = new class ('testplugin', $logger, $filemng, $fields) extends subplugin_exportable {
            /** @var array Test fields for this anonymous exportable. */
            private array $testfields;

            /**
             * Constructor.
             *
             * @param string $pluginname Plugin name.
             * @param template_import_logger_interface $logger Logger.
             * @param template_appendix_manager_interface $filemng File manager.
             * @param array $fields Fields to use.
             */
            public function __construct(
                string $pluginname,
                template_import_logger_interface $logger,
                template_appendix_manager_interface $filemng,
                array $fields,
            ) {
                parent::__construct($pluginname, $logger, $filemng);
                $this->testfields = $fields;
            }

            /**
             * Returns the test fields.
             *
             * @return field_interface[]
             */
            protected function get_fields(): array {
                return $this->testfields;
            }
        };
        // Empty string triggers format_exception in string_field (emptyallowed=false).
        $result = $exportable->convert_for_import(['title' => ['value' => '']]);
        $this->assertSame(['title' => 'FALLBACK'], json_decode($result, true));
    }

    /**
     * Test convert_for_import throws format_error when field data is not an array.
     */
    public function test_convert_for_import_throws_format_error_on_missing_data(): void {
        $fields = ['title' => new string_field(true, '')];
        $exportable = $this->make_exportable($fields);
        $this->expectException(format_error::class);
        $exportable->convert_for_import(['title' => 'not-an-array']);
    }

    /**
     * Test export returns structured array from customdata.
     */
    public function test_export_returns_structured_array(): void {
        $fields = ['title' => new string_field(true, '')];
        $exportable = $this->make_exportable($fields);
        $customdata = json_encode(['title' => 'Hello']);
        $result = $exportable->export($customdata);
        $this->assertSame(['title' => ['value' => 'Hello']], $result);
    }
}
