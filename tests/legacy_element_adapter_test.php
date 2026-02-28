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
 * Unit tests for legacy_element_adapter.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use customcertelement_text\element as text_element;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\template_service;
use mod_customcert\tests\fixtures\legacy_save_unique_data_element;
use mod_customcert\tests\fixtures\legacy_definition_after_data_element;
use mod_customcert\tests\fixtures\legacy_after_restore_element;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/legacy_save_unique_data_element.php');
require_once(__DIR__ . '/fixtures/legacy_definition_after_data_element.php');
require_once(__DIR__ . '/fixtures/legacy_after_restore_element.php');

/**
 * Tests for the legacy adapter mapping of getters.
 */
final class legacy_element_adapter_test extends advanced_testcase {
    /**
     * Ensure adapter delegates all getters to the wrapped legacy element.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::get_inner
     * @covers \mod_customcert\element\legacy_element_adapter::get_id
     * @covers \mod_customcert\element\legacy_element_adapter::get_pageid
     * @covers \mod_customcert\element\legacy_element_adapter::get_name
     * @covers \mod_customcert\element\legacy_element_adapter::get_data
     * @covers \mod_customcert\element\legacy_element_adapter::get_font
     * @covers \mod_customcert\element\legacy_element_adapter::get_fontsize
     * @covers \mod_customcert\element\legacy_element_adapter::get_colour
     * @covers \mod_customcert\element\legacy_element_adapter::get_posx
     * @covers \mod_customcert\element\legacy_element_adapter::get_posy
     * @covers \mod_customcert\element\legacy_element_adapter::get_width
     * @covers \mod_customcert\element\legacy_element_adapter::get_refpoint
     * @covers \mod_customcert\element\legacy_element_adapter::get_alignment
     */
    public function test_adapter_mirrors_legacy_getters(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 42,
            'pageid' => 7,
            'name' => 'Legacy Text',
            // New source of truth is JSON data; include expected values here.
            'data' => json_encode([
                'value' => 'hello',
                'font' => 'helvetica',
                'fontsize' => 12,
                'colour' => '#112233',
                'width' => 100,
            ]),
            'font' => 'helvetica', // Ignored by getters now; kept for legacy shape only.
            'fontsize' => 12, // Ignored by getters.
            'colour' => '#112233', // Ignored by getters.
            'posx' => 10,
            'posy' => 20,
            'width' => 100, // Ignored by getters; width comes from JSON.
            'refpoint' => 0,
            'alignment' => 'L',
        ];

        $legacy = new text_element($record);
        $adapter = new legacy_element_adapter($legacy);

        $this->assertSame(42, $adapter->get_id());
        $this->assertSame(7, $adapter->get_pageid());
        $this->assertSame('Legacy Text', $adapter->get_name());
        // Get_data() on the legacy adapter delegates to the legacy element, whose
        // data now stores JSON; extract the value to compare with the original scalar.
        $decoded = json_decode((string)$adapter->get_data(), true);
        $this->assertIsArray($decoded);
        $this->assertSame('hello', (string)$decoded['value']);
        $this->assertSame('helvetica', $adapter->get_font());
        $this->assertSame(12, $adapter->get_fontsize());
        $this->assertSame('#112233', $adapter->get_colour());
        $this->assertSame(10, $adapter->get_posx());
        $this->assertSame(20, $adapter->get_posy());
        $this->assertSame(100, $adapter->get_width());
        $this->assertSame(0, $adapter->get_refpoint());
        $this->assertSame('L', $adapter->get_alignment());

        // Ensure get_inner returns the original instance.
        $this->assertSame($legacy, $adapter->get_inner());
    }

    /**
     * Ensure factory's helper wraps a legacy element in the adapter.
     *
     * @covers \mod_customcert\service\element_factory::wrap_legacy
     */
    public function test_factory_wraps_legacy(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'X',
            'data' => '',
            'font' => null,
            'fontsize' => null,
            'colour' => null,
            'posx' => null,
            'posy' => null,
            'width' => null,
            'refpoint' => null,
            'alignment' => 'L',
        ];

        $legacy = new text_element($record);
        $factory = new element_factory(new element_registry());
        $adapter = $factory->wrap_legacy($legacy);
        $this->assertInstanceOf(legacy_element_adapter::class, $adapter);
        $this->assertSame($legacy, $adapter->get_inner());
    }

    /**
     * Ensure adapter delegates set_edit_element_form to inner element.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::set_edit_element_form
     */
    public function test_adapter_delegates_set_edit_element_form(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Test',
            'data' => '',
        ];

        $legacy = new text_element($record);
        $adapter = new legacy_element_adapter($legacy);

        // Create a mock form.
        $form = $this->createMock(\mod_customcert\edit_element_form::class);

        // Should not throw; delegates to inner element.
        $adapter->set_edit_element_form($form);

        // Verify the form was set on the inner element.
        $this->expectNotToPerformAssertions();
    }

    /**
     * Ensure adapter delegates render_form_elements to inner element.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::render_form_elements
     */
    public function test_adapter_delegates_render_form_elements(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Test',
            'data' => '',
        ];

        $legacy = new text_element($record);
        $adapter = new legacy_element_adapter($legacy);

        // Create a mock MoodleQuickForm.
        $mform = $this->getMockBuilder(\MoodleQuickForm::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Should not throw; delegates to inner element's deprecated render_form_elements.
        $adapter->render_form_elements($mform);

        // Assert that the deprecation warning was triggered.
        $this->assertDebuggingCalled(
            'render_form_elements() is deprecated since Moodle 5.2. ' .
            'Use element_helper::render_common_form_elements() instead.'
        );
    }

    /**
     * Ensure adapter delegates has_save_and_continue to inner element when method exists.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::has_save_and_continue
     */
    public function test_adapter_delegates_has_save_and_continue(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Test',
            'data' => '',
        ];

        // Text element doesn't have has_save_and_continue, so should return false.
        $legacy = new text_element($record);
        $adapter = new legacy_element_adapter($legacy);

        $this->assertFalse($adapter->has_save_and_continue());
    }

    /**
     * Ensure adapter delegates validate_form_elements to inner element when method exists.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::validate_form_elements
     */
    public function test_adapter_delegates_validate_form_elements(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Test',
            'data' => '',
        ];

        $legacy = new text_element($record);
        $adapter = new legacy_element_adapter($legacy);

        // Provide valid form data to avoid validation errors.
        $data = [
            'name' => 'Test',
            'colour' => '#000000',
            'width' => 100,
            'posx' => 10,
            'posy' => 10,
        ];

        // Should delegate to inner element's deprecated validate_form_elements.
        $errors = $adapter->validate_form_elements($data, []);

        // Assert that the deprecation warning was triggered.
        $this->assertDebuggingCalled(
            'validate_form_elements() is deprecated since Moodle 5.2. ' .
            'Implement mod_customcert\element\validatable_element_interface::validate() instead.'
        );

        // With valid data, should return empty array.
        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    /**
     * Ensure adapter delegates render_html to inner element.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::render_html
     */
    public function test_adapter_delegates_render_html(): void {
        global $DB;
        $this->resetAfterTest();

        // Create necessary database records for render_html to work.
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $templatedata = $DB->get_record('customcert_templates', ['id' => $customcert->templateid]);
        $template = template::load((int)$templatedata->id);
        $templateservice = template_service::create();
        $pageid = $templateservice->add_page($template);

        // Insert element record to get valid ID.
        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Test',
            'element' => 'text',
            'data' => json_encode(['value' => 'Hello World']),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $record = (object) [
            'id' => $elementid,
            'pageid' => $pageid,
            'name' => 'Test',
            'data' => json_encode(['value' => 'Hello World']),
        ];

        $legacy = new text_element($record);
        $adapter = new legacy_element_adapter($legacy);

        // Should delegate to inner element's render_html.
        $html = $adapter->render_html();

        // Should return non-empty HTML string.
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    /**
     * Ensure adapter delegates save_unique_data to inner element when method exists.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::save_unique_data
     */
    public function test_adapter_delegates_save_unique_data(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Test',
            'data' => '',
        ];

        // Create a legacy element with save_unique_data.
        $legacy = new legacy_save_unique_data_element($record);

        $adapter = new legacy_element_adapter($legacy);

        // Create form data.
        $formdata = (object) [
            'testfield' => 'Test content',
        ];

        // Should delegate to inner element's save_unique_data (deprecation notice fires at the call site, not the adapter).
        $result = $adapter->save_unique_data($formdata);
        // Should return the value from the inner element's save_unique_data.
        $this->assertIsString($result);
        $this->assertSame('Test content', $result);
    }

    /**
     * Ensure adapter delegates definition_after_data to inner element when method exists.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::definition_after_data
     */
    public function test_adapter_delegates_definition_after_data(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Test',
            'data' => json_encode(['dateitem' => '-1', 'fallbackstring' => 'Test']),
        ];

        // Create a legacy element with definition_after_data.
        $legacy = new legacy_definition_after_data_element($record);

        $adapter = new legacy_element_adapter($legacy);

        // Create a mock MoodleQuickForm.
        $mform = $this->getMockBuilder(\MoodleQuickForm::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Should delegate to inner element's deprecated definition_after_data.
        $adapter->definition_after_data($mform);

        // Verify the inner element's method was called.
        $this->assertTrue($legacy->called);
    }

    /**
     * Ensure adapter delegates after_restore to inner element when method exists.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::after_restore
     */
    public function test_adapter_delegates_after_restore(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Test',
            'data' => json_encode(['dateitem' => '-1']),
        ];

        // Create a legacy element with after_restore.
        $legacy = new legacy_after_restore_element($record);

        $adapter = new legacy_element_adapter($legacy);

        // Create a simple mock restore task (stdClass is sufficient for testing delegation).
        $restore = new \stdClass();

        // Should delegate to inner element's after_restore (deprecation notice fires at the call site, not the adapter).
        $adapter->after_restore($restore);

        // Verify the inner element's method was called.
        $this->assertTrue($legacy->called);
    }

    /**
     * Ensure element::delete() emits deprecation notice and still removes the record.
     *
     * @covers \mod_customcert\element::delete
     */
    public function test_element_delete_emits_deprecation_and_removes_record(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $templatedata = $DB->get_record('customcert_templates', ['id' => $customcert->templateid]);
        $template = template::load((int)$templatedata->id);
        $templateservice = template_service::create();
        $pageid = $templateservice->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Test',
            'element' => 'text',
            'data' => json_encode(['value' => 'Test']),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $record = (object) [
            'id' => $elementid,
            'pageid' => $pageid,
            'name' => 'Test',
            'data' => json_encode(['value' => 'Test']),
        ];

        $legacy = new text_element($record);

        $this->assertTrue($DB->record_exists('customcert_elements', ['id' => $elementid]));

        $result = $legacy->delete();

        $this->assertDebuggingCalled(
            'element::delete() is deprecated since Moodle 5.2. Use element_repository::delete() instead.',
            DEBUG_DEVELOPER
        );
        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $elementid]));
    }

    /**
     * Ensure adapter delegates delete to inner element.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::delete
     */
    public function test_adapter_delegates_delete(): void {
        global $DB;
        $this->resetAfterTest();

        // Create necessary database records.
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $templatedata = $DB->get_record('customcert_templates', ['id' => $customcert->templateid]);
        $template = template::load((int)$templatedata->id);
        $templateservice = template_service::create();
        $pageid = $templateservice->add_page($template);

        // Insert element record.
        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Test',
            'element' => 'text',
            'data' => json_encode(['value' => 'Test']),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $record = (object) [
            'id' => $elementid,
            'pageid' => $pageid,
            'name' => 'Test',
            'data' => json_encode(['value' => 'Test']),
        ];

        $legacy = new text_element($record);
        $adapter = new legacy_element_adapter($legacy);

        // Verify element exists.
        $this->assertTrue($DB->record_exists('customcert_elements', ['id' => $elementid]));

        // Delegates to inner element's delete(), which emits the deprecation notice.
        $result = $adapter->delete();

        $this->assertDebuggingCalled(
            'element::delete() is deprecated since Moodle 5.2. Use element_repository::delete() instead.',
            DEBUG_DEVELOPER
        );

        // Should return true and delete the record.
        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $elementid]));
    }
}
