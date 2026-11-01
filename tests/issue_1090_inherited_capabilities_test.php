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
 * Regression tests for #1090: a released Moodle 5.2 v2 element that inherits
 * renderable_element_interface must keep its optional capabilities when adapted in 5.3.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/customcertelement_inheritedv2/element.php');
require_once(__DIR__ . '/fixtures/customcertelement_legacy52/element.php');
require_once(__DIR__ . '/legacy_compatibility_diagnostic_test_trait.php');

use advanced_testcase;
use context_system;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_layout;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\form_service;
use mod_customcert\service\page_repository;
use mod_customcert\service\persistence_helper;
use mod_customcert\service\template_repository;
use mod_customcert\service\validation_service;
use MoodleQuickForm;

/**
 * Tests capability dispatch for adapted released-5.2 v2 elements.
 *
 * @covers \mod_customcert\service\persistence_helper
 * @covers \mod_customcert\service\form_service
 * @covers \mod_customcert\service\validation_service
 * @covers \mod_customcert\service\element_repository
 */
final class issue_1090_inherited_capabilities_test extends advanced_testcase {
    use \mod_customcert\tests\legacy_compatibility_diagnostic_test_trait;

    /** @var array Stored payload used by the tests. */
    private const STORED = [
        'font' => 'Courier',
        'fontsize' => 14,
        'colour' => '#112233',
        'width' => 40,
        'mode' => 'stored',
        'items' => ['a', 'b'],
    ];

    protected function setUp(): void {
        parent::setUp();
        // The factory's diagnostic is de-duplicated per component in static state.
        $this->reset_legacy_compatibility_diagnostic_state();
    }

    /**
     * Build a factory knowing the given element type.
     *
     * @param string $type
     * @param string $class
     * @return element_factory
     */
    private function make_factory(string $type, string $class): element_factory {
        $registry = new element_registry();
        $registry->register($type, $class);
        return new element_factory($registry);
    }

    /**
     * Create the element through the registry and factory.
     *
     * @param string $type
     * @param string $class
     * @param array $payload
     * @return element\element_interface
     */
    private function create_element(string $type, string $class, array $payload) {
        $instance = $this->make_factory($type, $class)->create_from_record((object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Element',
            'element' => $type,
            'data' => json_encode($payload),
            'posx' => 1,
            'posy' => 2,
            'refpoint' => 0,
            'alignment' => 'L',
        ]);
        $this->assertDebuggingCalled();
        return $instance;
    }

    /**
     * Create the released 5.2 v2 element under test.
     *
     * @return legacy_element_adapter
     */
    private function create_inherited_v2(): legacy_element_adapter {
        $instance = $this->create_element('inheritedv2', \customcertelement_inheritedv2\element::class, self::STORED);
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        return $instance;
    }

    /**
     * The adapter must not advertise capabilities itself, but the inner element has them.
     */
    public function test_inherited_v2_element_is_adapted_and_inner_has_capabilities(): void {
        $this->resetAfterTest();
        $instance = $this->create_inherited_v2();

        $this->assertNotInstanceOf(persistable_element_interface::class, $instance);
        $this->assertNotInstanceOf(preparable_form_interface::class, $instance);
        $this->assertNotInstanceOf(validatable_element_interface::class, $instance);
        $inner = $instance->get_inner();
        $this->assertInstanceOf(persistable_element_interface::class, $inner);
        $this->assertInstanceOf(preparable_form_interface::class, $inner);
        $this->assertInstanceOf(validatable_element_interface::class, $inner);
    }

    /**
     * Saving must run normalise_data() and keep element-specific JSON fields and styling.
     */
    public function test_save_honours_inner_normalise_data(): void {
        $this->resetAfterTest();
        $instance = $this->create_inherited_v2();

        $json = persistence_helper::to_json_data($instance, (object) [
            'font' => 'Times',
            'fontsize' => 11,
            'colour' => '#445566',
            'width' => 55,
            'mode' => 'edited',
            'items' => ['x', 'y', 'z'],
        ]);

        $this->assertTrue($instance->get_inner()->normalisecalled);
        $this->assertSame([
            'font' => 'Times',
            'fontsize' => 11,
            'colour' => '#445566',
            'width' => 55,
            'mode' => 'edited',
            'items' => ['x', 'y', 'z'],
        ], json_decode($json, true));
    }

    /**
     * Editing a persisted element must save the normalised payload, not an empty object.
     *
     * Mirrors edit_element.php: build the element from the submitted data, normalise it with
     * persistence_helper, then save it through element_repository.
     */
    public function test_editing_persisted_element_preserves_structured_json(): void {
        global $DB;
        $this->resetAfterTest();

        $templateid = (new template_repository())->create((object) [
            'name' => 'Issue 1090 template',
            'contextid' => context_system::instance()->id,
        ]);
        $pageid = (new page_repository())->create((object) [
            'templateid' => $templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);
        $now = time();
        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Before edit',
            'element' => 'inheritedv2',
            'data' => json_encode(self::STORED),
            'posx' => 10,
            'posy' => 20,
            'refpoint' => 1,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $factory = $this->make_factory('inheritedv2', \customcertelement_inheritedv2\element::class);
        $repository = new element_repository($factory);

        $stored = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $existing = $factory->create_from_record($stored);
        $this->assertDebuggingCalled();
        $this->assertInstanceOf(legacy_element_adapter::class, $existing);

        $data = (object) [
            'id' => $elementid,
            'pageid' => $pageid,
            'element' => 'inheritedv2',
            'name' => 'After edit',
            'font' => 'Times',
            'fontsize' => 11,
            'colour' => '#445566',
            'width' => 55,
            'mode' => 'edited',
            'items' => ['x', 'y', 'z'],
            'posx' => 30,
            'posy' => 40,
            'refpoint' => 2,
            'alignment' => 'C',
        ];

        $submitted = $factory->create_from_record($data);
        $record = (object) [
            'id' => $elementid,
            'pageid' => $pageid,
            'element' => 'inheritedv2',
            'name' => $data->name,
            'posx' => $data->posx,
            'posy' => $data->posy,
            'refpoint' => $data->refpoint,
            'alignment' => $data->alignment,
            'data' => persistence_helper::to_json_data($submitted, $data),
        ];
        $repository->save($factory->create('inheritedv2', $record), element_layout::from_record($record));

        $saved = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $this->assertNotSame('{}', $saved->data);
        $this->assertSame([
            'font' => 'Times',
            'fontsize' => 11,
            'colour' => '#445566',
            'width' => 55,
            'mode' => 'edited',
            'items' => ['x', 'y', 'z'],
        ], json_decode($saved->data, true));
        $this->assertSame('After edit', $saved->name);
        $this->assertEquals(30, $saved->posx);
        $this->assertSame('C', $saved->alignment);
    }

    /**
     * The element's own build_form() and prepare_form() must work together through form_service.
     */
    public function test_form_build_and_preparation_honour_inner_prepare_form(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/mod/customcert/includes/colourpicker.php');
        MoodleQuickForm::registerElementType(
            'customcert_colourpicker',
            $CFG->dirroot . '/mod/customcert/includes/colourpicker.php',
            'MoodleQuickForm_customcert_colourpicker'
        );
        $this->resetAfterTest();
        $instance = $this->create_inherited_v2();

        $mform = new MoodleQuickForm('issue1090', 'post', '#');
        $service = new form_service();
        $service->build_form($mform, $instance);
        $this->assertTrue($mform->elementExists('mode'));
        $this->assertTrue($mform->elementExists('items'));
        $this->assertTrue($mform->elementExists('colour'));

        $service->prepare_after_data($mform, $instance);

        $this->assertEquals(['stored'], (array) $mform->getElement('mode')->getValue());
        $this->assertEquals(['a', 'b'], (array) $mform->getElement('items')->getValue());
    }

    /**
     * Validation must include errors from the inner element's validate().
     */
    public function test_validation_honours_inner_validate(): void {
        $this->resetAfterTest();
        $instance = $this->create_inherited_v2();
        $service = new validation_service();

        $this->assertSame(['mode' => 'Invalid mode'], $service->validate($instance, ['mode' => 'invalid']));
        $this->assertSame([], $service->validate($instance, ['mode' => 'fine']));
    }

    /**
     * A released 5.2 element that uses the legacy hooks keeps using them.
     */
    public function test_legacy_hook_element_still_uses_legacy_save(): void {
        $this->resetAfterTest();
        $instance = $this->create_element('legacy52', \customcertelement_legacy52\element::class, ['value' => 'old']);
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);

        $json = persistence_helper::to_json_data($instance, (object) ['legacyvalue' => 'new']);

        $this->assertSame('new', json_decode($json, true)['value']);
        $this->assertDebuggingCalled();
    }
}
