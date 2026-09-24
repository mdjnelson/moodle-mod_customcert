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
 * Permanent legacy element runtime lifecycle regression coverage (#958).
 *
 * Broader than the focused #954 adapter tests. Uses dedicated realistic fixtures:
 * - customcertelement_legacy45\element: genuine Moodle 4.5-era untyped render signatures
 * - customcertelement_legacy52\element: 5.2-adapter-compatible typed-ish render + legacy hooks
 * - native_v2_control_element: native v2 control that must remain unwrapped
 *
 * Also includes the permanent regression coverage for #968 (fixed): saving a legacy
 * element through the real repository path must preserve the normalised JSON object
 * representation in storage, not the unwrapped legacy scalar compatibility view.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/customcertelement_legacy45/element.php');
require_once(__DIR__ . '/fixtures/customcertelement_legacy52/element.php');
require_once(__DIR__ . '/fixtures/native_v2_control_element.php');
// Restore base classes must load before the minimal_restore_task fixture subclass.
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/customcert/backup/moodle2/restore_customcert_activity_task.class.php');
require_once(__DIR__ . '/fixtures/minimal_restore_task.php');

use advanced_testcase;
use context_system;
use mod_customcert\element\copyable_element_interface;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\restorable_element_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\form_service;
use mod_customcert\service\html_renderer;
use mod_customcert\service\page_repository;
use mod_customcert\service\persistence_helper;
use mod_customcert\service\template_repository;
use mod_customcert\service\validation_service;
use mod_customcert\tests\fixtures\minimal_restore_task;
use mod_customcert\tests\fixtures\native_v2_control_element;
use MoodleQuickForm;
use stdClass;

/**
 * Permanent lifecycle regression tests for supported legacy third-party elements.
 *
 * @covers \mod_customcert\element\legacy_element_adapter
 * @covers \mod_customcert\service\element_factory
 * @covers \mod_customcert\service\element_repository
 * @covers \mod_customcert\service\form_service
 * @covers \mod_customcert\service\validation_service
 * @covers \mod_customcert\service\persistence_helper
 */
final class legacy_element_lifecycle_test extends advanced_testcase {
    /** Registry type key for the genuine 4.5-era fixture. */
    private const TYPE_LEGACY45 = 'legacy45';

    /** Registry type key for the 5.2-adapter-compatible fixture. */
    private const TYPE_LEGACY52 = 'legacy52';

    /** Registry type key for the native v2 control fixture. */
    private const TYPE_NATIVE = 'nativev2lifecycle';

    /**
     * Build a factory that knows the permanent #958 lifecycle fixtures.
     *
     * @return element_factory
     */
    private function make_factory(): element_factory {
        $registry = new element_registry();
        $registry->register(self::TYPE_LEGACY45, \customcertelement_legacy45\element::class);
        $registry->register(self::TYPE_LEGACY52, \customcertelement_legacy52\element::class);
        $registry->register(self::TYPE_NATIVE, native_v2_control_element::class);
        return new element_factory($registry);
    }

    /**
     * Build a repository wired to the lifecycle fixture factory.
     *
     * @return element_repository
     */
    private function make_repository(): element_repository {
        return new element_repository($this->make_factory());
    }

    /**
     * Create a template + page and return [templateid, pageid].
     *
     * @return array{0:int,1:int}
     */
    private function create_template_and_page(): array {
        $trepo = new template_repository();
        $prepo = new page_repository();

        $templateid = $trepo->create((object) [
            'name' => 'Legacy lifecycle template',
            'contextid' => context_system::instance()->id,
        ]);
        $pageid = $prepo->create((object) [
            'templateid' => $templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);

        return [$templateid, $pageid];
    }

    /**
     * Insert a customcert_elements row for a fixture type.
     *
     * @param int $pageid
     * @param string $type
     * @param string $name
     * @param array $data
     * @param int $sequence
     * @param array $overrides
     * @return stdClass Inserted record (with id).
     */
    private function insert_element_record(
        int $pageid,
        string $type,
        string $name,
        array $data = [],
        int $sequence = 1,
        array $overrides = []
    ): stdClass {
        global $DB;

        $now = time();
        $payload = $data ?: [
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
            'value' => 'hello',
        ];
        $record = (object) array_merge([
            'pageid' => $pageid,
            'name' => $name,
            'element' => $type,
            'data' => json_encode($payload),
            'posx' => 10,
            'posy' => 20,
            'refpoint' => 1,
            'alignment' => 'L',
            'sequence' => $sequence,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $overrides);
        $record->id = (int) $DB->insert_record('customcert_elements', $record, true);
        return $record;
    }

    /**
     * Create a minimal MoodleQuickForm stub for form lifecycle tests.
     *
     * @return MoodleQuickForm
     */
    private function create_stub_mform(): MoodleQuickForm {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        /** @var MoodleQuickForm $mform */
        $mform = $this->getMockBuilder(MoodleQuickForm::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['elementExists', 'getElement'])
            ->getMock();
        $mform->method('elementExists')->willReturn(false);
        return $mform;
    }

    /**
     * Construction/loading through the real factory + repository path for genuine 4.5 fixtures.
     */
    public function test_repository_loads_genuine_45_element_through_adapter(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'Genuine 4.5 element');

        $loaded = $this->make_repository()->load_by_page_id($pageid);
        $this->assertCount(1, $loaded);

        $instance = $loaded[0];
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertInstanceOf(renderable_element_interface::class, $instance);
        $this->assertInstanceOf(restorable_element_interface::class, $instance);
        $this->assertInstanceOf(\customcertelement_legacy45\element::class, $instance->get_inner());
        $this->assertSame('legacy45', $instance->get_inner()->get_type());
        $this->assertSame('legacy45:Helvetica', $instance->render_html());
        $this->assertSame('Genuine 4.5 element', $instance->get_name());
        $this->assertSame($pageid, $instance->get_pageid());
    }

    /**
     * Construction/loading for the distinct 5.2-adapter-compatible fixture shape.
     */
    public function test_repository_loads_52_adapter_style_element_through_adapter(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $this->insert_element_record($pageid, self::TYPE_LEGACY52, '5.2 adapter element');

        $loaded = $this->make_repository()->load_by_page_id($pageid);
        $this->assertCount(1, $loaded);

        $instance = $loaded[0];
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertInstanceOf(\customcertelement_legacy52\element::class, $instance->get_inner());
        $this->assertSame('legacy52', $instance->get_inner()->get_type());
        $this->assertSame('legacy52', $instance->render_html());
    }

    /**
     * Native v2 control remains unwrapped when loaded through the same repository path.
     */
    public function test_repository_loads_native_v2_control_unwrapped(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $this->insert_element_record($pageid, self::TYPE_NATIVE, 'Native v2 control');

        $loaded = $this->make_repository()->load_by_page_id($pageid);
        $this->assertCount(1, $loaded);
        $this->assertInstanceOf(native_v2_control_element::class, $loaded[0]);
        $this->assertNotInstanceOf(legacy_element_adapter::class, $loaded[0]);
        $this->assertSame('native-v2', $loaded[0]->render_html());
    }

    /**
     * Genuine 4.5 edit lifecycle: form generation, preparation and validation.
     *
     * Exercises the current service layer rather than calling adapter methods only.
     * Persistence (persistence_helper + repository save/reload) coverage for this fixture
     * lives in test_legacy_save_and_reload_preserves_json_object_and_legacy_view() to
     * avoid duplicating the same assertions in two tests.
     */
    public function test_genuine_45_edit_lifecycle_through_services(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'Edit lifecycle 45');
        $instance = $this->make_factory()->create_from_record($record);
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);

        $formservice = new form_service();
        $mform = $this->create_stub_mform();

        // Legacy editing form generation: form_service -> adapter -> build_form -> render_form_elements.
        $formservice->build_form($mform, $instance);
        $this->assertTrue($instance->get_inner()->formcalled);

        // Form preparation where applicable (definition_after_data bridge).
        $formservice->prepare_after_data($mform, $instance);
        $this->assertTrue($instance->get_inner()->definitioncalled);
        $this->assertDebuggingCalled();

        // Validation through validation_service legacy fallback.
        $validator = new validation_service();
        $errors = $validator->validate($instance, ['name' => 'bad', 'colour' => '#ffffff']);
        $this->assertArrayHasKey('name', $errors);
        $this->assertDebuggingCalled();
        $ok = $validator->validate($instance, ['name' => 'ok', 'colour' => '#ffffff']);
        $this->assertArrayNotHasKey('name', $ok);
        $this->assertDebuggingCalled();
    }

    /**
     * 5.2-adapter-compatible edit lifecycle remains distinct and functional.
     */
    public function test_legacy_52_edit_lifecycle_through_services(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY52, 'Edit lifecycle 52');
        $instance = $this->make_factory()->create_from_record($record);
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);

        $formservice = new form_service();
        $mform = $this->create_stub_mform();
        $formservice->build_form($mform, $instance);
        $this->assertTrue($instance->get_inner()->formcalled);

        // 5.2 fixture does not override definition_after_data; preparation is a no-op path.
        $formservice->prepare_after_data($mform, $instance);
        $this->assertDebuggingNotCalled();

        $errors = (new validation_service())->validate($instance, ['name' => 'anything', 'colour' => '#ffffff']);
        $this->assertIsArray($errors);
        // Validate_form_elements override still emits the service-layer deprecation.
        $this->assertDebuggingCalled();

        $json = persistence_helper::to_json_data($instance, (object) ['legacyvalue' => 'persisted52']);
        $decoded = json_decode($json, true);
        $this->assertSame('persisted52', $instance->get_inner()->lastsaved);
        $this->assertSame('persisted52', $decoded['value']);
        $this->assertDebuggingCalled();
    }

    /**
     * HTML rendering through the designer renderer path for both legacy fixture shapes.
     */
    public function test_html_rendering_through_html_renderer(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();
        $renderer = new html_renderer();

        [, $pageid] = $this->create_template_and_page();
        $legacy45 = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'HTML 45')
        );
        $legacy52 = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY52, 'HTML 52', [], 2)
        );

        $this->assertInstanceOf(legacy_element_adapter::class, $legacy45);
        $this->assertInstanceOf(legacy_element_adapter::class, $legacy52);
        $this->assertSame('legacy45:Helvetica', $renderer->render_html($legacy45));
        $this->assertSame('legacy52', $renderer->render_html($legacy52));
    }

    /**
     * PDF rendering through the adapter's strict v2 signature for both legacy fixtures.
     *
     * Asserts real delegation to the wrapped historical render() method: that it is
     * actually invoked, and that the same $pdf/$preview/$user arguments the adapter
     * received are the ones forwarded to the inner legacy element. See
     * legacy_element_adapter::render(): it always calls the inner render() with exactly
     * three positional arguments (pdf, preview, user); the optional v2 $renderer
     * argument is deliberately never forwarded, since the historical 4.5-era signature
     * is untyped/3-arg and the 5.2-era optional $renderer parameter is only meant to
     * default when not supplied by the caller.
     */
    public function test_pdf_rendering_through_adapter(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/pdflib.php');

        $factory = $this->make_factory();
        [, $pageid] = $this->create_template_and_page();

        $legacy45 = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'PDF 45')
        );
        $legacy52 = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY52, 'PDF 52', [], 2)
        );

        $pdf = $this->getMockBuilder(\pdf::class)->disableOriginalConstructor()->getMock();
        $user = new stdClass();

        $legacy45->render($pdf, true, $user);
        $inner45 = $legacy45->get_inner();
        $this->assertTrue($inner45->rendercalled, 'The historical render() must be invoked for the 4.5 fixture.');
        [$receivedpdf45, $receivedpreview45, $receiveduser45] = $inner45->lastrenderargs;
        $this->assertSame($pdf, $receivedpdf45);
        $this->assertTrue($receivedpreview45);
        $this->assertSame($user, $receiveduser45);

        $legacy52->render($pdf, false, $user);
        $inner52 = $legacy52->get_inner();
        $this->assertTrue($inner52->rendercalled, 'The historical render() must be invoked for the 5.2 fixture.');
        [$receivedpdf52, $receivedpreview52, $receiveduser52, $receivedrenderer52] = $inner52->lastrenderargs;
        $this->assertSame($pdf, $receivedpdf52);
        $this->assertFalse($receivedpreview52);
        $this->assertSame($user, $receiveduser52);
        // The adapter never forwards an explicit renderer to the historical signature;
        // the 5.2-era optional $renderer parameter is left to fall back to its default.
        $this->assertNull($receivedrenderer52);
    }

    /**
     * Copy behaviour through the current repository path.
     *
     * Scope boundary (#954 deliberately did not restore historical copy_element() on the
     * base class, and legacy_element_adapter does not implement copyable_element_interface).
     * Supported behaviour is DB-level record copy via element_repository::copy_element()
     * without a custom copy_from() hook.
     */
    public function test_copy_legacy_element_via_repository_without_copyable_hook(): void {
        global $DB;
        $this->resetAfterTest();

        [$templateid, $sourcepageid] = $this->create_template_and_page();
        $prepo = new page_repository();
        $targetpageid = $prepo->create((object) [
            'templateid' => $templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 2,
        ]);

        $source = $this->insert_element_record(
            $sourcepageid,
            self::TYPE_LEGACY45,
            'Copy me',
            ['value' => 'copy-payload', 'font' => 'Times']
        );

        $repository = $this->make_repository();
        $loaded = $repository->load_by_page_id($sourcepageid);
        $this->assertCount(1, $loaded);
        $this->assertInstanceOf(legacy_element_adapter::class, $loaded[0]);
        // Explicit contract: adapted legacy elements are not copyable_element_interface.
        $this->assertNotInstanceOf(copyable_element_interface::class, $loaded[0]);

        $copied = $repository->copy_element($source, $targetpageid);
        $this->assertNotNull($copied);
        $this->assertInstanceOf(legacy_element_adapter::class, $copied);
        $this->assertInstanceOf(\customcertelement_legacy45\element::class, $copied->get_inner());
        $this->assertNotEquals($source->id, $copied->get_id());
        $this->assertSame($targetpageid, $copied->get_pageid());
        $this->assertSame('Copy me', $copied->get_name());

        $newrecord = $DB->get_record('customcert_elements', ['id' => $copied->get_id()], '*', MUST_EXIST);
        $this->assertSame(self::TYPE_LEGACY45, $newrecord->element);
        $this->assertSame($source->data, $newrecord->data);
        $this->assertSame('legacy45:Times', $copied->render_html());
    }

    /**
     * Page-copy copies mixed element types on a page, preserving adapter vs native paths.
     */
    public function test_copy_page_with_legacy_and_native_elements(): void {
        $this->resetAfterTest();

        [$templateid, $sourcepageid] = $this->create_template_and_page();
        $prepo = new page_repository();
        $targetpageid = $prepo->create((object) [
            'templateid' => $templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 2,
        ]);

        $this->insert_element_record($sourcepageid, self::TYPE_LEGACY45, 'Legacy on page', [], 1);
        $this->insert_element_record($sourcepageid, self::TYPE_NATIVE, 'Native on page', [], 2);

        $repository = $this->make_repository();
        $count = $repository->copy_page($sourcepageid, $targetpageid);
        $this->assertSame(2, $count);

        $copied = $repository->load_by_page_id($targetpageid);
        $this->assertCount(2, $copied);

        $types = [];
        foreach ($copied as $element) {
            if ($element instanceof legacy_element_adapter) {
                $types[] = 'legacy';
                $this->assertInstanceOf(\customcertelement_legacy45\element::class, $element->get_inner());
            } else {
                $types[] = 'native';
                $this->assertInstanceOf(native_v2_control_element::class, $element);
            }
        }
        sort($types);
        $this->assertSame(['legacy', 'native'], $types);
    }

    /**
     * Deletion through the current repository path removes the legacy-adapted element row.
     *
     * Scope boundary: broad historical base-class delete() behaviour was intentionally not
     * restored by #954. This asserts the supported repository::delete() path only.
     */
    public function test_delete_legacy_element_via_repository(): void {
        global $DB;
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'Delete me');

        $repository = $this->make_repository();
        $instance = $repository->load_by_page_id($pageid)[0];
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertTrue($DB->record_exists('customcert_elements', ['id' => $record->id]));

        $result = $repository->delete($instance);
        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $record->id]));
        $this->assertSame([], $repository->load_by_page_id($pageid));
    }

    /**
     * Backup/restore hook delegation through the adapter (runtime contract only, not full mbz).
     *
     * The adapter implements restorable_element_interface and forwards to the inner
     * legacy after_restore() override when present.
     */
    public function test_after_restore_delegates_through_adapter(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'Restore me');
        $instance = $this->make_factory()->create_from_record($record);

        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertInstanceOf(restorable_element_interface::class, $instance);

        $task = new minimal_restore_task('rid-legacy-lifecycle', 1, 1);
        $this->assertFalse($instance->get_inner()->restorecalled);

        // Direct adapter contract: v2 restore hook reaches the historical after_restore().
        $instance->after_restore_from_backup($task);
        $this->assertTrue($instance->get_inner()->restorecalled);
        $this->assertSame($task, $instance->get_inner()->lastrestore);
        // Adapter emits the historical-hook deprecation once while still delegating.
        $this->assertDebuggingCalled();
    }

    /**
     * Mixed legacy (adapter-wrapped) + native v2 control on one realistic page lifecycle.
     *
     * Exercises construction, load, form/validation/persist services, HTML render, copy and
     * delete through the current factory/registry/repository/services infrastructure.
     */
    public function test_mixed_legacy_and_native_v2_page_lifecycle(): void {
        global $DB;
        $this->resetAfterTest();

        [$templateid, $pageid] = $this->create_template_and_page();
        $factory = $this->make_factory();
        $repository = new element_repository($factory);
        $formservice = new form_service();
        $validator = new validation_service();
        $htmlrenderer = new html_renderer();

        // Seed both shapes on the same page.
        $legacyrecord = $this->insert_element_record(
            $pageid,
            self::TYPE_LEGACY45,
            'Mixed legacy',
            ['font' => 'Courier', 'value' => 'L'],
            1
        );
        $nativerecord = $this->insert_element_record(
            $pageid,
            self::TYPE_NATIVE,
            'Mixed native',
            ['value' => 'N'],
            2
        );

        // Load through the real repository path.
        $loaded = $repository->load_by_page_id($pageid);
        $this->assertCount(2, $loaded);

        $legacy = null;
        $native = null;
        foreach ($loaded as $element) {
            if ($element instanceof legacy_element_adapter) {
                $legacy = $element;
            } else if ($element instanceof native_v2_control_element) {
                $native = $element;
            }
        }
        $this->assertInstanceOf(legacy_element_adapter::class, $legacy);
        $this->assertInstanceOf(native_v2_control_element::class, $native);
        $this->assertInstanceOf(\customcertelement_legacy45\element::class, $legacy->get_inner());

        // Form + validation + persistence for the legacy side via services.
        $mform = $this->create_stub_mform();
        $formservice->build_form($mform, $legacy);
        $formservice->prepare_after_data($mform, $legacy);
        $this->assertTrue($legacy->get_inner()->formcalled);
        $this->assertTrue($legacy->get_inner()->definitioncalled);
        $this->assertDebuggingCalled();

        $errors = $validator->validate($legacy, ['name' => 'bad', 'colour' => '#abcdef']);
        $this->assertArrayHasKey('name', $errors);
        $this->assertDebuggingCalled();

        $legacyjson = persistence_helper::to_json_data($legacy, (object) ['legacyvalue' => 'mixed-save']);
        $this->assertSame('mixed-save', json_decode($legacyjson, true)['value']);
        $this->assertDebuggingCalled();

        // Native side uses the direct persistable path (no adapter).
        $nativejson = persistence_helper::to_json_data($native, (object) ['value' => 'native-save']);
        $this->assertSame(['value' => 'native-save'], json_decode($nativejson, true));
        $this->assertDebuggingNotCalled();

        // HTML rendering of both on the same page.
        $this->assertSame('legacy45:Courier', $htmlrenderer->render_html($legacy));
        $this->assertSame('native-v2', $htmlrenderer->render_html($native));

        // Page copy keeps both shapes and wrapping rules.
        $prepo = new page_repository();
        $targetpageid = $prepo->create((object) [
            'templateid' => $templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 2,
        ]);
        $this->assertSame(2, $repository->copy_page($pageid, $targetpageid));
        $copied = $repository->load_by_page_id($targetpageid);
        $this->assertCount(2, $copied);

        $copiedlegacy = null;
        $copiednative = null;
        foreach ($copied as $element) {
            if ($element instanceof legacy_element_adapter) {
                $copiedlegacy = $element;
            } else if ($element instanceof native_v2_control_element) {
                $copiednative = $element;
            }
        }
        $this->assertNotNull($copiedlegacy);
        $this->assertNotNull($copiednative);
        $this->assertSame('legacy45:Courier', $copiedlegacy->render_html());
        $this->assertSame('native-v2', $copiednative->render_html());

        // Delete only the legacy element from the source page via repository.
        $this->assertTrue($repository->delete($legacy));
        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $legacyrecord->id]));
        $this->assertTrue($DB->record_exists('customcert_elements', ['id' => $nativerecord->id]));

        $remaining = $repository->load_by_page_id($pageid);
        $this->assertCount(1, $remaining);
        $this->assertInstanceOf(native_v2_control_element::class, $remaining[0]);
    }

    /**
     * Permanent regression coverage for #968 (fixed on main): element_repository::save()
     * must persist the normalised JSON object produced by persistence_helper::to_json_data(),
     * not the unwrapped legacy scalar returned by element::get_data(), and the legacy
     * scalar compatibility view must still work after the element is reloaded.
     *
     * Exercises the real end-to-end pipeline: a legacy form submission (new legacy value
     * plus visual metadata: font/fontsize/colour/width) -> persistence_helper::to_json_data()
     * -> factory reconstruction -> element_repository::save() -> direct DB read -> reload
     * through the factory/repository again.
     */
    public function test_legacy_save_and_reload_preserves_json_object_and_legacy_view(): void {
        global $DB;
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'Save lifecycle 45');
        $factory = $this->make_factory();
        $instance = $factory->create_from_record($record);
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);

        // Step 1: normalise a legacy form submission (new legacy value + visual metadata)
        // through the real persistence_helper bridge.
        $formdata = (object) [
            'legacyvalue' => 'persisted45',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
        ];
        $json = persistence_helper::to_json_data($instance, $formdata);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded, 'persistence_helper::to_json_data() must produce a JSON object.');
        $this->assertSame('persisted45', $decoded['value']);
        $this->assertSame('Courier', $decoded['font']);
        $this->assertSame(18, $decoded['fontsize']);
        $this->assertSame('#123456', $decoded['colour']);
        $this->assertSame(75, $decoded['width']);
        $this->assertDebuggingCalled();

        // Step 2: reconstruct the element from that normalised record, as save() would see it
        // after form processing writes the normalised JSON onto the in-memory element.
        $normalisedrecord = clone $record;
        $normalisedrecord->data = $json;
        $tosave = $factory->create_from_record($normalisedrecord);
        $this->assertInstanceOf(legacy_element_adapter::class, $tosave);

        // Step 3: persist through the actual repository save() path.
        $layout = new \mod_customcert\service\element_layout(10, 20, 1, 'L');
        $this->make_repository()->save($tosave, $layout);

        // Step 4: read the stored representation directly from the database.
        $stored = $DB->get_field('customcert_elements', 'data', ['id' => $record->id], MUST_EXIST);

        // The customcert_elements.data column must remain a JSON object (persistence_helper's
        // documented invariant), not the unwrapped legacy scalar, and visual metadata
        // submitted with the edit must survive the save.
        $storeddecoded = json_decode($stored, true);
        $this->assertIsArray(
            $storeddecoded,
            'customcert_elements.data must remain a JSON object after element_repository::save(); ' .
            'got: ' . var_export($stored, true)
        );
        $this->assertSame('persisted45', $storeddecoded['value'] ?? null);
        $this->assertSame('Courier', $storeddecoded['font'] ?? null);
        $this->assertSame(18, $storeddecoded['fontsize'] ?? null);
        $this->assertSame('#123456', $storeddecoded['colour'] ?? null);
        $this->assertSame(75, $storeddecoded['width'] ?? null);

        // Step 5: reload through the factory/repository and confirm the legacy scalar
        // compatibility view (get_data()) on the inner element still works.
        $dbrecord = $DB->get_record('customcert_elements', ['id' => $record->id], '*', MUST_EXIST);
        $reloaded = $factory->create_from_record($dbrecord);
        $this->assertInstanceOf(legacy_element_adapter::class, $reloaded);
        $this->assertSame('persisted45', $reloaded->get_inner()->get_data());

        $reloadedviarepository = $this->make_repository()->load_by_page_id($pageid);
        $this->assertCount(1, $reloadedviarepository);
        $this->assertSame('persisted45', $reloadedviarepository[0]->get_inner()->get_data());
    }

    /**
     * create_from_record is the factory path used by restore and tolerant copy flows.
     * Legacy element types are wrapped in the adapter while native v2 types stay unwrapped.
     */
    public function test_create_from_record_distinguishes_legacy_and_native(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();
        [, $pageid] = $this->create_template_and_page();

        $legacy = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY52, 'From record 52')
        );
        $this->assertInstanceOf(legacy_element_adapter::class, $legacy);
        $this->assertInstanceOf(\customcertelement_legacy52\element::class, $legacy->get_inner());

        $native = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_NATIVE, 'From record native', [], 2)
        );
        $this->assertInstanceOf(native_v2_control_element::class, $native);
    }
}
