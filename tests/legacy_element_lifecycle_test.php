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
require_once(__DIR__ . '/fixtures/customcertelement_legacythrows974/element.php');
require_once(__DIR__ . '/fixtures/native_v2_control_element.php');
// Restore base classes must load before the minimal_restore_task fixture subclass.
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/customcert/backup/moodle2/restore_customcert_activity_task.class.php');
require_once(__DIR__ . '/fixtures/minimal_restore_task.php');
require_once(__DIR__ . '/legacy_compatibility_diagnostic_test_trait.php');

use advanced_testcase;
use context_system;
use mod_customcert\element\copyable_element_interface;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\restorable_element_interface;
use mod_customcert\event\element_created;
use mod_customcert\event\element_deleted;
use mod_customcert\event\element_updated;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_renderer;
use mod_customcert\service\element_repository;
use mod_customcert\service\form_service;
use mod_customcert\service\html_renderer;
use mod_customcert\service\page_repository;
use mod_customcert\service\pdf_renderer;
use mod_customcert\service\persistence_helper;
use mod_customcert\service\template_repository;
use mod_customcert\service\validation_service;
use mod_customcert\tests\fixtures\minimal_restore_task;
use mod_customcert\tests\fixtures\native_v2_control_element;
use mod_customcert\element\element_interface;
use MoodleQuickForm;
use ReflectionMethod;
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
 * @covers \mod_customcert\service\pdf_renderer
 * @covers \mod_customcert\service\html_renderer
 */
final class legacy_element_lifecycle_test extends advanced_testcase {
    use \mod_customcert\tests\legacy_compatibility_diagnostic_test_trait;

    protected function setUp(): void {
        parent::setUp();
        // Each test starts with a clean per-component de-duplication state for the
        // general legacy compatibility diagnostic, independent of test execution order.
        $this->reset_legacy_compatibility_diagnostic_state();
    }

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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
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
        // Native v2 elements are never wrapped, so no legacy compatibility diagnostic
        // is emitted for them.
        $this->assertDebuggingNotCalled();
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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $legacy52 = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY52, 'HTML 52', [], 2)
        );
        $this->assertDebuggingCalled();

        $this->assertInstanceOf(legacy_element_adapter::class, $legacy45);
        $this->assertInstanceOf(legacy_element_adapter::class, $legacy52);
        $this->assertSame('legacy45:Helvetica', $renderer->render_html($legacy45));
        $this->assertSame('legacy52', $renderer->render_html($legacy52));
    }

    /**
     * PDF rendering through the adapter's strict v2 signature for both legacy fixtures,
     * called directly with no renderer supplied.
     *
     * Asserts real delegation to the wrapped historical render() method: that it is
     * actually invoked, and that the same $pdf/$preview/$user arguments the adapter
     * received are the ones forwarded to the inner legacy element. The genuine 4.5
     * fixture's render() only ever declares three parameters, so it always receives
     * exactly those three positional arguments. The released-5.2 fixture's render()
     * declares a fourth renderer parameter, so the adapter forwards whatever renderer
     * it received; here that is null because none was supplied. Renderer *forwarding*
     * itself, and exact historical call arity, are covered separately by
     * test_pdf_renderer_forwards_itself_to_released_52_legacy_element() and
     * test_pdf_renderer_preserves_exact_historical_45_call_arity() below.
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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $legacy52 = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY52, 'PDF 52', [], 2)
        );
        $this->assertDebuggingCalled();

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
        // Calling the adapter directly with no renderer supplied forwards none; renderer
        // forwarding itself is covered by test_pdf_renderer_forwards_itself_to_released_52_legacy_element().
        $this->assertNull($receivedrenderer52);
    }

    /**
     * The permanent released-5.2 fixture's render()/render_html() declarations must exactly
     * match the contract released Moodle 5.2 required, so this regression fails if the
     * fixture is ever weakened back to an untyped/return-typeless "5.2-ish" shape.
     */
    public function test_legacy52_fixture_declares_the_released_52_render_contract(): void {
        $renderref = new \ReflectionMethod(\customcertelement_legacy52\element::class, 'render');
        $renderparams = $renderref->getParameters();
        $this->assertCount(4, $renderparams);
        $this->assertSame('pdf', $renderparams[0]->getType()?->getName());
        $this->assertFalse($renderparams[0]->getType()?->allowsNull());
        $this->assertSame('bool', $renderparams[1]->getType()?->getName());
        $this->assertSame('stdClass', $renderparams[2]->getType()?->getName());
        $rendererparamtype = $renderparams[3]->getType();
        $this->assertNotNull($rendererparamtype);
        $this->assertTrue($rendererparamtype->allowsNull());
        $this->assertSame(element_renderer::class, $rendererparamtype->getName());
        $this->assertTrue($renderparams[3]->isDefaultValueAvailable());
        $this->assertNull($renderparams[3]->getDefaultValue());
        $this->assertTrue($renderref->hasReturnType());
        $this->assertSame('void', $renderref->getReturnType()?->getName());

        $htmlref = new \ReflectionMethod(\customcertelement_legacy52\element::class, 'render_html');
        $htmlparams = $htmlref->getParameters();
        $this->assertCount(1, $htmlparams);
        $htmlparamtype = $htmlparams[0]->getType();
        $this->assertNotNull($htmlparamtype);
        $this->assertTrue($htmlparamtype->allowsNull());
        $this->assertSame(element_renderer::class, $htmlparamtype->getName());
        $this->assertTrue($htmlparams[0]->isDefaultValueAvailable());
        $this->assertNull($htmlparams[0]->getDefaultValue());
        $this->assertTrue($htmlref->hasReturnType());
        $this->assertSame('string', $htmlref->getReturnType()?->getName());
    }

    /**
     * A released-5.2-compatible legacy element's render() declares the renderer parameter,
     * so pdf_renderer's actual rendering path must forward itself through the adapter to it.
     */
    public function test_pdf_renderer_forwards_itself_to_released_52_legacy_element(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/pdflib.php');

        $factory = $this->make_factory();
        [, $pageid] = $this->create_template_and_page();
        $legacy52 = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY52, 'PDF renderer 52')
        );
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $this->assertInstanceOf(legacy_element_adapter::class, $legacy52);

        $pdf = $this->getMockBuilder(\pdf::class)->disableOriginalConstructor()->getMock();
        $user = new stdClass();
        $renderer = new pdf_renderer();

        $renderer->render_pdf($legacy52, $pdf, true, $user);

        $inner = $legacy52->get_inner();
        $this->assertTrue($inner->rendercalled);
        [$receivedpdf, $receivedpreview, $receiveduser, $receivedrenderer] = $inner->lastrenderargs;
        $this->assertSame($pdf, $receivedpdf);
        $this->assertTrue($receivedpreview);
        $this->assertSame($user, $receiveduser);
        $this->assertSame($renderer, $receivedrenderer);
    }

    /**
     * A released-5.2-compatible legacy element's render_html() declares the renderer
     * parameter, so html_renderer's actual rendering path must forward itself through the
     * adapter to it.
     */
    public function test_html_renderer_forwards_itself_to_released_52_legacy_element(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();
        [, $pageid] = $this->create_template_and_page();
        $legacy52 = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY52, 'HTML renderer 52')
        );
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $renderer = new html_renderer();
        $html = $renderer->render_html($legacy52);

        $this->assertSame('legacy52', $html);
        $this->assertSame($renderer, $legacy52->get_inner()->lasthtmlrenderer);
    }

    /**
     * A genuine 4.5-era legacy element's render() only ever declares three parameters, so
     * pdf_renderer's actual rendering path must still call it with exactly the historical
     * three positional arguments, never a fourth renderer argument.
     */
    public function test_pdf_renderer_preserves_exact_historical_45_call_arity(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/pdflib.php');

        $factory = $this->make_factory();
        [, $pageid] = $this->create_template_and_page();
        $legacy45 = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'PDF arity 45')
        );
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $pdf = $this->getMockBuilder(\pdf::class)->disableOriginalConstructor()->getMock();
        $user = new stdClass();
        $renderer = new pdf_renderer();

        $renderer->render_pdf($legacy45, $pdf, false, $user);

        $inner = $legacy45->get_inner();
        $this->assertTrue($inner->rendercalled);
        $this->assertSame(3, $inner->lastrenderargcount, 'Genuine 4.5 render() must receive exactly 3 arguments.');
        [$receivedpdf, $receivedpreview, $receiveduser] = $inner->lastrenderargs;
        $this->assertSame($pdf, $receivedpdf);
        $this->assertFalse($receivedpreview);
        $this->assertSame($user, $receiveduser);
    }

    /**
     * A genuine 4.5-era legacy element's render_html() is parameterless, so html_renderer's
     * actual rendering path must still call it with exactly zero arguments.
     */
    public function test_html_renderer_preserves_exact_historical_45_call_arity(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();
        [, $pageid] = $this->create_template_and_page();
        $legacy45 = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'HTML arity 45')
        );
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $renderer = new html_renderer();
        $html = $renderer->render_html($legacy45);

        $this->assertSame('legacy45:Helvetica', $html);
        $this->assertSame(
            0,
            $legacy45->get_inner()->lasthtmlargcount,
            'Genuine 4.5 render_html() must receive exactly 0 arguments.'
        );
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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $this->assertCount(1, $loaded);
        $this->assertInstanceOf(legacy_element_adapter::class, $loaded[0]);
        // Explicit contract: adapted legacy elements are not copyable_element_interface.
        $this->assertNotInstanceOf(copyable_element_interface::class, $loaded[0]);

        $copied = $repository->copy_element($source, $targetpageid);
        // The same component was already warned about above in this request/test.
        $this->assertDebuggingNotCalled();
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
        // The factory emits the general legacy-compatibility diagnostic once for the
        // legacy component involved; the native control never triggers it.
        $this->assertDebuggingCalled();
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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

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
        // The factory emits the general legacy-compatibility diagnostic for the legacy
        // element only; the native control never triggers it.
        $this->assertDebuggingCalled();
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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
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
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $this->assertInstanceOf(legacy_element_adapter::class, $legacy);
        $this->assertInstanceOf(\customcertelement_legacy52\element::class, $legacy->get_inner());

        $native = $factory->create_from_record(
            $this->insert_element_record($pageid, self::TYPE_NATIVE, 'From record native', [], 2)
        );
        $this->assertInstanceOf(native_v2_control_element::class, $native);
    }

    /**
     * Declaration regression (#984): the three restored legacy lifecycle shims must keep
     * their released-5.2 untyped compatibility signatures on the base class.
     */
    public function test_restored_lifecycle_shims_declare_the_52_compatibility_surface(): void {
        $saveref = new ReflectionMethod(\mod_customcert\element::class, 'save_form_elements');
        $saveparams = $saveref->getParameters();
        $this->assertCount(1, $saveparams);
        $this->assertNull($saveparams[0]->getType());
        $this->assertFalse($saveref->hasReturnType());

        $copyref = new ReflectionMethod(\mod_customcert\element::class, 'copy_element');
        $copyparams = $copyref->getParameters();
        $this->assertCount(1, $copyparams);
        $this->assertNull($copyparams[0]->getType());
        $this->assertFalse($copyref->hasReturnType());

        $deleteref = new ReflectionMethod(\mod_customcert\element::class, 'delete');
        $this->assertCount(0, $deleteref->getParameters());
        $this->assertFalse($deleteref->hasReturnType());
    }

    /**
     * Restored save_form_elements() update path (#984): persists via the current
     * repository/persistence machinery, preserves #968 visual/raw-data fields, fires
     * element_updated exactly once, and cannot be redirected to another
     * id/pageid/element type by the submitted form data.
     */
    public function test_save_form_elements_update_preserves_identity_and_persists_changes(): void {
        global $DB;
        $this->resetAfterTest();

        [$templateid, $pageid] = $this->create_template_and_page();
        // A second page/element exists so an identity-redirect attack has somewhere to land.
        $prepo = new page_repository();
        $otherpageid = $prepo->create((object) [
            'templateid' => $templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 2,
        ]);

        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'Original name', [
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
            'value' => 'original',
        ]);
        $instance = $this->make_factory()->create_from_record($record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $legacy = $instance->get_inner();

        $sink = $this->redirectEvents();

        // Attempt an identity attack: try to redirect this element to a different
        // id/page/element type via the submitted form data.
        $formdata = (object) [
            'id' => $record->id + 999,
            'pageid' => $otherpageid,
            'element' => self::TYPE_LEGACY52,
            'name' => 'Updated name',
            'legacyvalue' => 'updated-value',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
            'refpoint' => 1,
            'alignment' => 'C',
        ];

        $result = $legacy->save_form_elements($formdata);

        // Two deprecations are expected: save_form_elements() itself, and save_unique_data()
        // via persistence_helper (the legacy45 fixture overrides it).
        $messages = $this->getDebuggingMessages();
        $this->resetDebugging();
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('save_form_elements() is deprecated', $messages[0]->message);
        $this->assertStringContainsString('save_unique_data() is deprecated', $messages[1]->message);

        $this->assertTrue($result);

        $updatedevents = array_values(array_filter(
            $sink->get_events(),
            fn ($e) => $e instanceof element_updated
        ));
        $this->assertCount(1, $updatedevents);
        $this->assertSame($record->id, $updatedevents[0]->objectid);

        // Only the original row exists: identity was not redirected, and no extra row
        // was created by the submitted id/pageid/element attack fields.
        $this->assertSame(1, $DB->count_records('customcert_elements'));
        $stored = $DB->get_record('customcert_elements', ['id' => $record->id], '*', MUST_EXIST);
        $this->assertSame($record->id, (int) $stored->id);
        $this->assertSame($pageid, (int) $stored->pageid);
        $this->assertSame(self::TYPE_LEGACY45, $stored->element);
        $this->assertFalse($DB->record_exists('customcert_elements', ['pageid' => $otherpageid]));

        // Name and #968 visual/raw-data fields were persisted correctly.
        $this->assertSame('Updated name', $stored->name);
        $this->assertSame('C', $stored->alignment);
        $this->assertSame(1, (int) $stored->refpoint);
        $decoded = json_decode($stored->data, true);
        $this->assertSame('updated-value', $decoded['value']);
        $this->assertSame('Courier', $decoded['font']);
        $this->assertSame(18, $decoded['fontsize']);
        $this->assertSame('#123456', $decoded['colour']);
        $this->assertSame(75, $decoded['width']);

        // Reload through the real factory/repository path (#968): the legacy scalar
        // compatibility view must still resolve correctly after the restored save.
        $reloaded = $this->make_repository()->load_by_page_id($pageid);
        $this->assertCount(1, $reloaded);
        $this->assertSame('updated-value', $reloaded[0]->get_inner()->get_data());
    }

    /**
     * Restored save_form_elements() update path (#984): matches the released-5.2
     * compatibility contract for omitted refpoint/alignment — they reset to their
     * released-5.2 defaults (null / element::ALIGN_LEFT) rather than preserving the
     * previously stored non-default values.
     */
    public function test_save_form_elements_update_resets_omitted_refpoint_and_alignment_to_52_defaults(): void {
        global $DB;
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'Has non-default layout', [], 1, [
            'refpoint' => 2,
            'alignment' => \mod_customcert\element::ALIGN_RIGHT,
        ]);
        $instance = $this->make_factory()->create_from_record($record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $legacy = $instance->get_inner();
        $this->assertSame(2, $legacy->get_refpoint());
        $this->assertSame(\mod_customcert\element::ALIGN_RIGHT, $legacy->get_alignment());

        // Submitted form data omits refpoint/alignment entirely.
        $formdata = (object) [
            'name' => 'Reset layout',
            'legacyvalue' => 'reset-value',
        ];

        $result = $legacy->save_form_elements($formdata);

        // Two deprecations are expected: save_form_elements() itself, and save_unique_data()
        // via persistence_helper (the legacy45 fixture overrides it).
        $messages = $this->getDebuggingMessages();
        $this->resetDebugging();
        $this->assertCount(2, $messages);
        $this->assertTrue($result);

        $stored = $DB->get_record('customcert_elements', ['id' => $record->id], '*', MUST_EXIST);
        $this->assertNull($stored->refpoint);
        $this->assertSame(\mod_customcert\element::ALIGN_LEFT, $stored->alignment);
    }

    /**
     * Restored save_form_elements() create path (#984): persists a new row via the current
     * repository/persistence machinery, returns the new integer id, keeps the instance id
     * coherent, and fires element_created exactly once.
     */
    public function test_save_form_elements_create_persists_new_element_and_returns_id(): void {
        global $DB;
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();

        // Element_repository::create() resolves the element type through the real default
        // factory to fire element_created, so this uses a real bundled type ('text') that
        // still extends the deprecated mod_customcert\element base and does not override
        // save_form_elements(). A brand-new, unsaved instance as historical callers
        // constructed one: only the element type is known up-front (see edit_element.php's
        // 'add' action).
        $unsaved = (object) ['element' => 'text', 'name' => 'Unsaved text element'];
        $instance = element_factory::build_with_defaults()->create_from_record($unsaved);
        $this->assertNotInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertSame(0, $instance->get_id());

        $sink = $this->redirectEvents();

        $formdata = (object) [
            'pageid' => $pageid,
            'name' => 'New text element',
            'text' => 'created-value',
            'font' => 'Arial',
            'fontsize' => 14,
            'colour' => '#abcdef',
            'width' => 60,
            'refpoint' => 0,
            'alignment' => 'L',
        ];

        $newid = $instance->save_form_elements($formdata);
        $this->assertDebuggingCalled(
            'save_form_elements() is deprecated since Moodle 5.2. Implement '
            . 'mod_customcert\\element\\persistable_element_interface::normalise_data() and '
            . 'use element_repository for persistence.',
            DEBUG_DEVELOPER
        );

        $this->assertIsInt($newid);
        $this->assertGreaterThan(0, $newid);
        // The instance's own id is kept coherent after creation.
        $this->assertSame($newid, $instance->get_id());

        $createdevents = array_values(array_filter(
            $sink->get_events(),
            fn ($e) => $e instanceof element_created
        ));
        $this->assertCount(1, $createdevents);
        $this->assertSame($newid, $createdevents[0]->objectid);

        $stored = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame($pageid, (int) $stored->pageid);
        $this->assertSame('text', $stored->element);
        $this->assertSame('New text element', $stored->name);
        $this->assertSame(1, (int) $stored->sequence);
        $this->assertSame('L', $stored->alignment);
        $this->assertGreaterThan(0, (int) $stored->timecreated);
        $this->assertSame((int) $stored->timecreated, (int) $stored->timemodified);

        // Issue #968: font/fontsize/colour/width visual metadata survives the restored shim.
        $decoded = json_decode($stored->data, true);
        $this->assertSame('created-value', $decoded['text']);
        $this->assertSame('Arial', $decoded['font']);
        $this->assertSame(14, $decoded['fontsize']);
        $this->assertSame('#abcdef', $decoded['colour']);
        $this->assertSame(60, $decoded['width']);
    }

    /**
     * Restored delete() shim (#984): delegates to element_repository::delete(), removing the
     * row and firing element_deleted exactly once.
     */
    public function test_delete_shim_removes_record_and_fires_event(): void {
        global $DB;
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'Delete via shim');
        $instance = $this->make_factory()->create_from_record($record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $legacy = $instance->get_inner();

        $sink = $this->redirectEvents();
        $result = $legacy->delete();
        $this->assertDebuggingCalled(
            'element::delete() is deprecated since Moodle 5.2. Use element_repository::delete() instead.',
            DEBUG_DEVELOPER
        );
        $this->assertTrue($result);

        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $record->id]));

        $deletedevents = array_values(array_filter(
            $sink->get_events(),
            fn ($e) => $e instanceof element_deleted
        ));
        $this->assertCount(1, $deletedevents);
        $this->assertSame($record->id, $deletedevents[0]->objectid);
    }

    /**
     * The adapter's own delete() must remain coherent with the restored base shim: both
     * delegate to the same element_repository::delete() path (#984).
     */
    public function test_adapter_delete_remains_coherent_with_base_shim(): void {
        global $DB;
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'Delete via adapter');
        $instance = $this->make_factory()->create_from_record($record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);

        $result = $instance->delete();
        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $record->id]));
    }

    /**
     * Restored released-5.2 API (#985): create_from_legacy_record() must declare exactly the
     * released contract: public, non-static, one stdClass parameter, nullable element_interface
     * return type.
     */
    public function test_create_from_legacy_record_declares_the_52_contract(): void {
        $ref = new ReflectionMethod(element_factory::class, 'create_from_legacy_record');
        $this->assertTrue($ref->isPublic());
        $this->assertFalse($ref->isStatic());

        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame(stdClass::class, $params[0]->getType()?->getName());
        $this->assertFalse($params[0]->getType()?->allowsNull());

        $returntype = $ref->getReturnType();
        $this->assertNotNull($returntype);
        $this->assertTrue($returntype->allowsNull());
        $this->assertSame(element_interface::class, $returntype->getName());
    }

    /**
     * #985: a successful call must not emit a deprecation diagnostic of its own. The general
     * legacy-compatibility diagnostic emitted when wrapping a legacy element (#956) is
     * unrelated to this restored method.
     */
    public function test_create_from_legacy_record_does_not_emit_its_own_deprecation(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_NATIVE, 'No deprecation');

        $this->make_factory()->create_from_legacy_record($record);

        $this->assertDebuggingNotCalled();
    }

    /**
     * #985: missing/empty element type must return null, never throw.
     */
    public function test_create_from_legacy_record_missing_or_empty_type_returns_null(): void {
        $factory = $this->make_factory();

        $this->assertNull($factory->create_from_legacy_record((object) []));
        $this->assertNull($factory->create_from_legacy_record((object) ['element' => '']));
    }

    /**
     * #985: an unregistered element type must return null; no arbitrary classname is constructed.
     */
    public function test_create_from_legacy_record_unknown_type_returns_null(): void {
        $factory = $this->make_factory();

        $this->assertNull($factory->create_from_legacy_record((object) ['element' => 'unknownxyz123']));
    }

    /**
     * #985: a missing or empty name defaults to the element plugin's pluginname string.
     */
    public function test_create_from_legacy_record_defaults_missing_or_empty_name(): void {
        $this->resetAfterTest();

        $factory = element_factory::build_with_defaults();
        $pluginname = get_string('pluginname', 'customcertelement_text');

        $missingname = $factory->create_from_legacy_record((object) ['element' => 'text']);
        $this->assertSame($pluginname, $missingname->get_name());

        $emptyname = $factory->create_from_legacy_record((object) ['element' => 'text', 'name' => '']);
        $this->assertSame($pluginname, $emptyname->get_name());
    }

    /**
     * #985: a genuine 4.5-era legacy element is constructed through the current adapter path.
     */
    public function test_create_from_legacy_record_routes_genuine_45_through_adapter(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY45, 'Legacy record 45');

        $instance = $this->make_factory()->create_from_legacy_record($record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertInstanceOf(\customcertelement_legacy45\element::class, $instance->get_inner());
        $this->assertSame('legacy45:Helvetica', $instance->render_html());
    }

    /**
     * #985: a released-5.2-compatible legacy element is constructed through the current
     * adapter path, preserving #981 renderer-argument compatibility.
     */
    public function test_create_from_legacy_record_routes_52_compatible_through_adapter(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_LEGACY52, 'Legacy record 52');

        $instance = $this->make_factory()->create_from_legacy_record($record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertInstanceOf(\customcertelement_legacy52\element::class, $instance->get_inner());
        $this->assertSame('legacy52', $instance->render_html());
    }

    /**
     * #985: a native v2 element remains direct/unwrapped.
     */
    public function test_create_from_legacy_record_returns_native_v2_directly(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_NATIVE, 'Native record');

        $instance = $this->make_factory()->create_from_legacy_record($record);
        $this->assertDebuggingNotCalled();

        $this->assertInstanceOf(native_v2_control_element::class, $instance);
        $this->assertNotInstanceOf(legacy_element_adapter::class, $instance);
    }

    /**
     * #985: a registered class that throws during construction returns null, with the same
     * diagnostic behaviour as create_from_record().
     */
    public function test_create_from_legacy_record_construction_failure_returns_null(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        $registry->register('legacythrows974', \customcertelement_legacythrows974\element::class);
        $factory = new element_factory($registry);

        $result = $factory->create_from_legacy_record((object) ['element' => 'legacythrows974', 'name' => 'Broken']);

        $this->assertNull($result);
        // The factory's own construction-failure diagnostic still fires; create_from_record()
        // only suppresses its own additional diagnostic under PHPUnit/Behat.
        $this->assertDebuggingCalledCount(1);
    }

    /**
     * #985: create_from_record() remains unchanged by the addition of create_from_legacy_record().
     */
    public function test_create_from_record_remains_unchanged_control(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_NATIVE, 'Control record');

        $instance = $this->make_factory()->create_from_record($record);
        $this->assertDebuggingNotCalled();

        $this->assertInstanceOf(native_v2_control_element::class, $instance);
    }
}
