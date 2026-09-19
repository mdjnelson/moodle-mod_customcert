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
 * Focused tests for the Moodle 5.3 legacy element compatibility adapter (#954).
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\form_service;
use mod_customcert\service\persistence_helper;
use mod_customcert\service\validation_service;
use mod_customcert\tests\fixtures\legacy_52_adapter_element;
use mod_customcert\tests\fixtures\legacy_genuine_45_element;
use mod_customcert\tests\fixtures\native_v2_control_element;
use MoodleQuickForm;
use stdClass;

/**
 * Focused tests for the Moodle 5.3 legacy element compatibility adapter.
 *
 * @covers \mod_customcert\element\legacy_element_adapter
 * @covers \mod_customcert\service\element_factory
 * @covers \mod_customcert\element
 */
final class legacy_element_adapter_test extends advanced_testcase {
    /**
     * Build a minimal element DB-shaped record.
     *
     * @param array $overrides
     * @return stdClass
     */
    private function make_record(array $overrides = []): stdClass {
        $record = (object) [
            'id' => 10,
            'pageid' => 20,
            'name' => 'Legacy element',
            'data' => json_encode([
                'font' => 'Helvetica',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 50,
                'value' => 'hello',
            ]),
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 1,
            'alignment' => 'L',
            'element' => 'legacytest',
        ];
        foreach ($overrides as $k => $v) {
            $record->$k = $v;
        }
        return $record;
    }

    /**
     * Genuine 4.5-era class with untyped render() must load and instantiate without a PHP fatal.
     */
    public function test_genuine_45_element_loads_and_exposes_legacy_properties(): void {
        $this->resetAfterTest();

        $record = $this->make_record();
        $inner = new legacy_genuine_45_element($record);

        $this->assertSame(10, $inner->get_id());
        $this->assertSame('Helvetica', $inner->font);
        $this->assertSame(12, $inner->fontsize);
        $this->assertSame('#000000', $inner->colour);
        $this->assertSame(50, $inner->width);
        $this->assertSame(10, $inner->element->id);
        $this->assertSame('legacy45:Helvetica', $inner->render_html());
    }

    /**
     * Factory must wrap genuine 4.5-era elements in the legacy adapter and expose the v2 surface.
     */
    public function test_factory_wraps_genuine_45_element_with_adapter(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        $registry->register('legacy45', legacy_genuine_45_element::class);
        $factory = new element_factory($registry);

        $instance = $factory->create('legacy45', $this->make_record());

        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertInstanceOf(renderable_element_interface::class, $instance);
        $this->assertInstanceOf(legacy_genuine_45_element::class, $instance->get_inner());
        $this->assertSame('legacy45:Helvetica', $instance->render_html());
        $this->assertSame(10, $instance->get_id());
        $this->assertSame('Helvetica', $instance->get_font());
    }

    /**
     * 5.2-adapter-style element (typed render + legacy hooks) must keep working via the adapter.
     */
    public function test_factory_wraps_52_adapter_style_element_and_persists_via_save_unique_data(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        $registry->register('legacy52', legacy_52_adapter_element::class);
        $factory = new element_factory($registry);

        $instance = $factory->create('legacy52', $this->make_record());
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertSame('legacy52', $instance->render_html());

        // Form bridge: build_form -> render_form_elements on inner.
        $mform = $this->create_stub_mform();
        $instance->build_form($mform);
        $this->assertTrue($instance->get_inner()->formcalled);

        // Persistence via save_unique_data through the helper.
        $formdata = (object) ['name' => 'X', 'legacyvalue' => 'payload52'];
        $json = persistence_helper::to_json_data($instance, $formdata);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('payload52', $instance->get_inner()->lastsaved);
        // JSON must be object-shaped; scalar return is wrapped as {"value": ...}.
        $this->assertArrayHasKey('value', $decoded);

        // Validation legacy fallback.
        $errors = (new validation_service())->validate($instance, ['name' => 'ok', 'colour' => '#ffffff']);
        $this->assertIsArray($errors);
    }

    /**
     * Genuine 4.5 element validation and form preparation go through the adapter + services.
     */
    public function test_services_dispatch_legacy_form_and_validation_hooks(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        $registry->register('legacy45', legacy_genuine_45_element::class);
        $factory = new element_factory($registry);
        $instance = $factory->create('legacy45', $this->make_record());

        $mform = $this->create_stub_mform();
        (new form_service())->prepare_after_data($mform, $instance);
        $this->assertTrue($instance->get_inner()->definitioncalled);

        $errors = (new validation_service())->validate($instance, ['name' => 'bad', 'colour' => '#ffffff']);
        $this->assertArrayHasKey('name', $errors);

        $json = persistence_helper::to_json_data($instance, (object) ['legacyvalue' => 'from45']);
        $decoded = json_decode($json, true);
        $this->assertSame('from45', $instance->get_inner()->lastsaved);
        $this->assertSame('from45', $decoded['value']);
    }

    /**
     * Native v2 elements must remain on the direct path (no adapter wrap).
     */
    public function test_native_v2_element_is_not_wrapped(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        $registry->register('nativev2', native_v2_control_element::class);
        $factory = new element_factory($registry);

        $instance = $factory->create('nativev2', $this->make_record());
        $this->assertInstanceOf(native_v2_control_element::class, $instance);
        $this->assertNotInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertSame('native-v2', $instance->render_html());

        $json = persistence_helper::to_json_data($instance, (object) ['value' => 'direct']);
        $this->assertSame(['value' => 'direct'], json_decode($json, true));
    }

    /**
     * Bundled text element still constructs as the concrete class (v2 path unchanged).
     */
    public function test_bundled_text_element_remains_unwrapped(): void {
        $this->resetAfterTest();

        $factory = element_factory::build_with_defaults();
        $instance = $factory->create('text', $this->make_record(['element' => 'text']));
        $this->assertInstanceOf(\customcertelement_text\element::class, $instance);
        $this->assertNotInstanceOf(legacy_element_adapter::class, $instance);
    }

    /**
     * Create a minimal MoodleQuickForm stub for form lifecycle tests.
     *
     * @return MoodleQuickForm
     */
    private function create_stub_mform(): MoodleQuickForm {
        /** @var MoodleQuickForm \$mform */
        $mform = $this->getMockBuilder(MoodleQuickForm::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['elementExists', 'getElement'])
            ->getMock();
        $mform->method('elementExists')->willReturn(false);
        return $mform;
    }
}
