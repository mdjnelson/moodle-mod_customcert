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
 * Tests for the restored historical \mod_customcert\element_factory entry point (#974).
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/customcertelement_legacy45/element.php');
require_once(__DIR__ . '/fixtures/customcertelement_legacythrows974/element.php');
require_once(__DIR__ . '/fixtures/native_v2_control_element.php');

use advanced_testcase;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\tests\fixtures\native_v2_control_element;
use stdClass;
use Throwable;

/**
 * Tests for the restored historical static compatibility entry point.
 *
 * @covers \mod_customcert\element_factory
 */
final class historical_element_factory_test extends advanced_testcase {
    /**
     * Build a minimal historical DB-shaped record.
     *
     * @param array $overrides
     * @return stdClass
     */
    private function make_record(array $overrides = []): stdClass {
        $record = (object) [
            'id' => 10,
            'pageid' => 20,
            'name' => 'Legacy element',
            'element' => 'legacy45',
            'data' => 'hello',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'posx' => 5,
            'posy' => 6,
            'width' => 50,
            'refpoint' => 1,
            'alignment' => 'L',
        ];
        foreach ($overrides as $k => $v) {
            $record->$k = $v;
        }
        return $record;
    }

    /**
     * The historical class must exist again, with the original static entry point.
     */
    public function test_historical_class_and_method_exist(): void {
        $this->assertTrue(class_exists(element_factory::class));
        $this->assertTrue(method_exists(element_factory::class, 'get_element_instance'));
    }

    /**
     * Genuine legacy45 fixture (#958) must return the raw plugin object, not the v2 adapter.
     */
    public function test_genuine_legacy_fixture_returns_raw_plugin_object(): void {
        $this->resetAfterTest();

        $result = element_factory::get_element_instance($this->make_record());
        $this->assertDebuggingCalled();

        $this->assertInstanceOf(\customcertelement_legacy45\element::class, $result);
        $this->assertInstanceOf(\mod_customcert\element::class, $result);
        $this->assertNotInstanceOf(legacy_element_adapter::class, $result);

        // Must remain directly usable, not merely constructible.
        $this->assertSame('legacy45:Helvetica', $result->render_html());
    }

    /**
     * All 13 historical constructor fields must be populated on the constructed instance.
     *
     * font/fontsize/colour/width are asserted via the legacy protected properties: the
     * typed getters instead read from the JSON 'data' payload (#968), out of scope here.
     */
    public function test_thirteen_historical_fields_are_populated(): void {
        $this->resetAfterTest();

        $result = element_factory::get_element_instance($this->make_record());
        $this->assertDebuggingCalled();

        $this->assertSame(10, $result->get_id());
        $this->assertSame(20, $result->get_pageid());
        $this->assertSame('Legacy element', $result->get_name());
        $this->assertSame('legacy45', $result->get_type());
        $this->assertSame(5, $result->get_posx());
        $this->assertSame(6, $result->get_posy());
        $this->assertSame(1, $result->get_refpoint());
        $this->assertSame('L', $result->get_alignment());

        $this->assertSame('Helvetica', $this->get_protected_property($result, 'font'));
        $this->assertSame(12, $this->get_protected_property($result, 'fontsize'));
        $this->assertSame('#000000', $this->get_protected_property($result, 'colour'));
        $this->assertSame(50, $this->get_protected_property($result, 'width'));

        $legacyrecord = $this->get_protected_property($result, 'element');
        $this->assertSame(10, $legacyrecord->id);
        $this->assertSame('legacy45', $legacyrecord->element);
    }

    /**
     * Read a protected/private property value via reflection for assertions.
     *
     * @param object $object
     * @param string $property
     * @return mixed
     */
    private function get_protected_property(object $object, string $property): mixed {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        return $ref->getValue($object);
    }

    /**
     * Historical defaults: an installed plugin with no explicit name falls back to its
     * pluginname string, and omitted optional fields become null rather than erroring.
     */
    public function test_defaults_for_installed_plugin_with_omitted_fields(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 1,
            'pageid' => 2,
            'element' => 'studentname',
            'data' => null,
        ];

        $result = element_factory::get_element_instance($record);
        $this->assertDebuggingCalled();

        $this->assertInstanceOf(\customcertelement_studentname\element::class, $result);
        $this->assertSame(get_string('pluginname', 'customcertelement_studentname'), $result->get_name());
        $this->assertNull($result->get_posx());
        $this->assertNull($result->get_posy());
        $this->assertNull($result->get_refpoint());
        $this->assertSame(\mod_customcert\element::ALIGN_LEFT, $result->get_alignment());
    }

    /**
     * Historical scalar data must survive construction unchanged.
     */
    public function test_scalar_data_survives_construction(): void {
        $this->resetAfterTest();

        $result = element_factory::get_element_instance($this->make_record(['data' => 'a scalar value']));
        $this->assertDebuggingCalled();

        $this->assertSame('a scalar value', $result->get_data());
    }

    /**
     * Structured JSON data must survive construction in both #968 views: raw
     * (get_raw_data()) and the legacy compatibility scalar view (get_data()).
     */
    public function test_structured_data_survives_construction(): void {
        $this->resetAfterTest();

        $json = json_encode(['value' => 'payload', 'font' => 'Helvetica', 'width' => 50]);
        $result = element_factory::get_element_instance($this->make_record(['data' => $json]));
        $this->assertDebuggingCalled();

        $this->assertSame($json, $result->get_raw_data());
        $this->assertSame('payload', $result->get_data());
    }

    /**
     * A missing plugin class must return strict false, never null, 'unknown', or an adapter.
     */
    public function test_missing_plugin_returns_strict_false(): void {
        $this->resetAfterTest();

        $result = element_factory::get_element_instance(
            $this->make_record(['element' => 'nonexistent_plugin_xyz'])
        );
        $this->assertDebuggingCalled();

        $this->assertFalse($result);
    }

    /**
     * Missing-plugin detection must happen before the default name lookup, so an
     * uninstalled plugin never triggers get_string() for it.
     */
    public function test_missing_plugin_does_not_evaluate_default_name_first(): void {
        $this->resetAfterTest();

        $record = $this->make_record(['element' => 'nonexistent_plugin_xyz']);
        unset($record->name);

        // A get_string() lookup for a non-installed component would throw; strict false
        // here proves the class_exists() check ran first.
        $result = element_factory::get_element_instance($record);
        $this->assertDebuggingCalled();

        $this->assertFalse($result);
    }

    /**
     * A constructor exception must remain observable, never silently converted to false.
     */
    public function test_constructor_exception_remains_observable(): void {
        $this->resetAfterTest();

        $this->expectException(Throwable::class);
        try {
            element_factory::get_element_instance($this->make_record(['element' => 'legacythrows974']));
        } finally {
            $this->assertDebuggingCalled();
        }
    }

    /**
     * Native-v2 boundary: native_v2_control_element lives outside
     * `\customcertelement_<type>\element`, so it's unreachable here.
     */
    public function test_native_v2_class_outside_naming_convention_is_unreachable(): void {
        $this->resetAfterTest();

        $result = element_factory::get_element_instance(
            $this->make_record(['element' => 'nativev2boundary974'])
        );
        $this->assertDebuggingCalled();

        $this->assertFalse($result);
    }

    /**
     * Native-v2 boundary: a genuine bundled native-v2 class (studentname) does live at
     * that address, so it's returned directly, unwrapped.
     */
    public function test_bundled_native_v2_element_returns_raw_object(): void {
        $this->resetAfterTest();

        $result = element_factory::get_element_instance($this->make_record(['element' => 'studentname']));
        $this->assertDebuggingCalled();

        $this->assertInstanceOf(\customcertelement_studentname\element::class, $result);
        $this->assertInstanceOf(renderable_element_interface::class, $result);
        $this->assertNotInstanceOf(legacy_element_adapter::class, $result);
    }

    /**
     * Native v2 classes extend the same base constructor, so historical-shaped
     * construction works directly.
     */
    public function test_native_v2_class_is_constructible_with_historical_record_shape(): void {
        $this->resetAfterTest();

        $data = new stdClass();
        $data->id = 10;
        $data->pageid = 20;
        $data->name = 'Native element';
        $data->element = 'nativev2boundary974';
        $data->data = 'value';
        $data->font = 'Helvetica';
        $data->fontsize = 12;
        $data->colour = '#000000';
        $data->posx = 5;
        $data->posy = 6;
        $data->width = 50;
        $data->refpoint = 1;
        $data->alignment = 'L';

        $instance = new native_v2_control_element($data);

        $this->assertSame('native-v2', $instance->render_html());
    }

    /**
     * The shim must emit its own DEBUG_DEVELOPER deprecation, naming the current
     * replacement API and the removal release.
     */
    public function test_deprecation_diagnostic_is_emitted(): void {
        $this->resetAfterTest();

        element_factory::get_element_instance($this->make_record());

        $debugging = $this->getDebuggingMessages();
        $this->assertCount(1, $debugging);
        $this->resetDebugging();

        $message = $debugging[0]->message;
        $this->assertStringContainsString('get_element_instance() is deprecated', $message);
        $this->assertStringContainsString(
            '\mod_customcert\service\element_factory::build_with_defaults()->create_from_record()',
            $message
        );
        $this->assertStringContainsString('Moodle 6.0-compatible release', $message);
        $this->assertSame(DEBUG_DEVELOPER, $debugging[0]->level);
    }

    /**
     * Unlike the #956 legacy-adapter diagnostic, which deduplicates per component, this
     * shim's own deprecation fires on every call, matching real debugging() semantics.
     */
    public function test_deprecation_diagnostic_is_not_deduplicated_across_calls(): void {
        $this->resetAfterTest();

        element_factory::get_element_instance($this->make_record());
        $this->assertDebuggingCalledCount(1);

        element_factory::get_element_instance($this->make_record());
        $this->assertDebuggingCalledCount(1);
    }
}
