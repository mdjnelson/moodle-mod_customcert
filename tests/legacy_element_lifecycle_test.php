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
 * Lifecycle regression coverage for the restored Moodle 5.2 element lifecycle shims (#984)
 * and the Element System v2 factory/repository paths, now that the legacy element runtime
 * bridge has been removed for the Moodle 6.0-compatible release (#993).
 *
 * Uses dedicated realistic fixtures:
 * - customcertelement_legacy45\element: genuine Moodle 4.5-era element, exercised directly
 *   (not through the factory, since the legacy runtime bridge no longer wraps it)
 * - native_v2_control_element: native v2 control that must remain the factory's direct path
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
require_once(__DIR__ . '/fixtures/native_v2_control_element.php');

use advanced_testcase;
use context_system;
use mod_customcert\event\element_created;
use mod_customcert\event\element_deleted;
use mod_customcert\event\element_updated;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_repository;
use mod_customcert\tests\fixtures\native_v2_control_element;
use ReflectionMethod;
use stdClass;

/**
 * Lifecycle regression tests for the restored Moodle 5.2 shims and the v2 factory/repository.
 *
 * @covers \mod_customcert\element
 * @covers \mod_customcert\service\element_factory
 * @covers \mod_customcert\service\element_repository
 */
final class legacy_element_lifecycle_test extends advanced_testcase {
    /** Registry type key for the genuine 4.5-era fixture. */
    private const TYPE_LEGACY45 = 'legacy45';

    /** Registry type key for the native v2 control fixture. */
    private const TYPE_NATIVE = 'nativev2lifecycle';

    /**
     * Build a factory that knows the native v2 lifecycle fixture.
     *
     * @return element_factory
     */
    private function make_factory(): element_factory {
        $registry = new element_registry();
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
     * Native v2 control remains unwrapped when loaded through the repository path.
     */
    public function test_repository_loads_native_v2_control_unwrapped(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $this->insert_element_record($pageid, self::TYPE_NATIVE, 'Native v2 control');

        $loaded = $this->make_repository()->load_by_page_id($pageid);
        $this->assertDebuggingNotCalled();
        $this->assertCount(1, $loaded);
        $this->assertInstanceOf(native_v2_control_element::class, $loaded[0]);
        $this->assertSame('native-v2', $loaded[0]->render_html());
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
     *
     * Exercises the genuine 4.5-era fixture directly: the legacy runtime bridge no longer
     * wraps it, so the historical shim is called on the instance itself.
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
        $legacy = new \customcertelement_legacy45\element($record);

        $sink = $this->redirectEvents();

        // Attempt an identity attack: try to redirect this element to a different
        // id/page/element type via the submitted form data.
        $formdata = (object) [
            'id' => $record->id + 999,
            'pageid' => $otherpageid,
            'element' => 'somethingelse',
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

        // Reload directly (#968): the legacy scalar compatibility view must still resolve
        // correctly after the restored save.
        $reloaded = new \customcertelement_legacy45\element($stored);
        $this->assertSame('updated-value', $reloaded->get_data());
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
        $legacy = new \customcertelement_legacy45\element($record);
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
        $legacy = new \customcertelement_legacy45\element($record);

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
        $this->assertSame(\mod_customcert\element\element_interface::class, $returntype->getName());
    }

    /**
     * #985: a successful call must not emit a deprecation diagnostic of its own.
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
     * #985: a native v2 element remains direct/unwrapped.
     */
    public function test_create_from_legacy_record_returns_native_v2_directly(): void {
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_NATIVE, 'Native record');

        $instance = $this->make_factory()->create_from_legacy_record($record);
        $this->assertDebuggingNotCalled();

        $this->assertInstanceOf(native_v2_control_element::class, $instance);
    }

    /**
     * #985: a registered class that throws during construction returns null, with the same
     * diagnostic behaviour as create_from_record().
     */
    public function test_create_from_legacy_record_construction_failure_returns_null(): void {
        $this->resetAfterTest();

        require_once(__DIR__ . '/fixtures/customcertelement_legacythrows974/element.php');

        $registry = new element_registry();
        $registry->register('legacythrows974', \customcertelement_legacythrows974\element::class);
        $factory = new element_factory($registry);

        $result = $factory->create_from_legacy_record((object) ['element' => 'legacythrows974', 'name' => 'Broken']);

        $this->assertNull($result);
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
