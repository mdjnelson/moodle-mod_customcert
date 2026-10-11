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
 * Lifecycle regression coverage for Element System v2 factory/repository paths after the
 * legacy element runtime compatibility surface was removed (#994).
 *
 * Uses dedicated realistic fixtures:
 * - customcertelement_legacy45\element: genuine Moodle 4.5-era element retained for
 *   boundary fixtures where still relevant
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
 * Lifecycle regression tests for the v2 factory/repository after legacy runtime removal.
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
     * Native v2 repository save path: exercises an actual update through the supported v2
     * path (a changed name, plugin data and visual fields, plus a layout change), preserves
     * element identity (id, page, type), persists exactly the complete new JSON payload,
     * creates/modifies no other row, fires element_updated exactly once, and emits no
     * legacy diagnostic.
     */
    public function test_native_repository_save_preserves_identity_and_persists_changes(): void {
        global $DB;
        $this->resetAfterTest();

        [$templateid, $pageid] = $this->create_template_and_page();
        $prepo = new page_repository();
        $otherpageid = $prepo->create((object) [
            'templateid' => $templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 2,
        ]);

        $record = $this->insert_element_record($pageid, self::TYPE_NATIVE, 'Original name', [
            'value' => 'original',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);

        // Build the post-edit instance the same way the supported v2 edit path would:
        // a record with the same id/pageid/type, but a changed name and a changed JSON
        // payload (plugin data plus visual/layout fields).
        $updatedpayload = [
            'value' => 'updated',
            'font' => 'Arial',
            'fontsize' => 18,
            'colour' => '#ff0000',
            'width' => 75,
        ];
        $updatedrecord = (object) array_merge((array) $record, [
            'name' => 'Updated name',
            'data' => json_encode($updatedpayload),
        ]);
        $instance = $this->make_factory()->create(self::TYPE_NATIVE, $updatedrecord);

        $sink = $this->redirectEvents();
        $layout = new \mod_customcert\service\element_layout(15, 25, 1, 'C');
        $this->make_repository()->save($instance, $layout);
        $this->assertDebuggingNotCalled();

        $updatedevents = array_values(array_filter(
            $sink->get_events(),
            fn ($e) => $e instanceof element_updated
        ));
        $this->assertCount(1, $updatedevents);
        $this->assertSame($record->id, $updatedevents[0]->objectid);

        $this->assertSame(1, $DB->count_records('customcert_elements'));
        $stored = $DB->get_record('customcert_elements', ['id' => $record->id], '*', MUST_EXIST);

        // Identity: the original element, page and type remain correct.
        $this->assertSame($record->id, (int) $stored->id);
        $this->assertSame($pageid, (int) $stored->pageid);
        $this->assertSame(self::TYPE_NATIVE, $stored->element);
        $this->assertFalse($DB->record_exists('customcert_elements', ['pageid' => $otherpageid]));

        // The changed name, layout and complete JSON payload are stored.
        $this->assertSame('Updated name', $stored->name);
        $this->assertSame(15, (int) $stored->posx);
        $this->assertSame(25, (int) $stored->posy);
        $this->assertSame(1, (int) $stored->refpoint);
        $this->assertSame('C', $stored->alignment);
        $this->assertSame($updatedpayload, json_decode($stored->data, true));
    }

    /**
     * Native v2 repository create path: persists a new row and fires element_created once.
     *
     * Uses a real bundled native type so create()'s post-insert factory reload resolves
     * through get_type() (namespace-derived type keys).
     */
    public function test_native_repository_create_persists_new_element_and_returns_id(): void {
        global $DB;
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();

        $unsaved = (object) [
            'element' => 'text',
            'name' => 'New text element',
            'pageid' => $pageid,
            'data' => json_encode([
                'text' => 'created-value',
                'font' => 'Helvetica',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 50,
            ]),
        ];
        $factory = element_factory::build_with_defaults();
        $instance = $factory->create('text', $unsaved);
        $this->assertSame(0, $instance->get_id());

        $sink = $this->redirectEvents();
        $layout = new \mod_customcert\service\element_layout(null, null, 0, 'L');
        $newid = (new element_repository($factory))->create($instance, $layout);

        $this->assertDebuggingNotCalled();
        $this->assertIsInt($newid);
        $this->assertGreaterThan(0, $newid);

        $createdevents = array_values(array_filter(
            $sink->get_events(),
            fn ($e) => $e instanceof element_created
        ));
        $this->assertCount(1, $createdevents);
        $this->assertSame($newid, $createdevents[0]->objectid);

        $stored = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame('text', $stored->element);
        $this->assertSame('New text element', $stored->name);
        $this->assertSame('created-value', json_decode($stored->data, true)['text']);
    }

    /**
     * Repository deletion still fires element_deleted and removes the row.
     */
    public function test_repository_delete_removes_element_and_fires_event(): void {
        global $DB;
        $this->resetAfterTest();

        [, $pageid] = $this->create_template_and_page();
        $record = $this->insert_element_record($pageid, self::TYPE_NATIVE, 'To delete');
        $instance = $this->make_factory()->create(self::TYPE_NATIVE, $record);

        $sink = $this->redirectEvents();
        $this->make_repository()->delete($instance);
        $this->assertDebuggingNotCalled();

        $deletedevents = array_values(array_filter(
            $sink->get_events(),
            fn ($e) => $e instanceof element_deleted
        ));
        $this->assertCount(1, $deletedevents);
        $this->assertSame($record->id, $deletedevents[0]->objectid);
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
