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
 * Regression tests for issue #968: preserve JSON object storage when persisting legacy
 * element data, instead of persisting the legacy scalar compatibility view returned by
 * get_data().
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_customcert\element
 * @covers \mod_customcert\element\legacy_element_adapter
 * @covers \mod_customcert\service\element_repository
 */

declare(strict_types=1);

namespace mod_customcert;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/legacy_genuine_45_element.php');
require_once(__DIR__ . '/fixtures/native_v2_control_element.php');
require_once(__DIR__ . '/fixtures/dummy_element_interface_element.php');
require_once(__DIR__ . '/fixtures/dummy_element_interface_with_id_element.php');
require_once(__DIR__ . '/fixtures/customcertelement_legacy968/element.php');
require_once(__DIR__ . '/fixtures/customcertelement_legacyjson968/element.php');
require_once(__DIR__ . '/legacy_compatibility_diagnostic_test_trait.php');

use advanced_testcase;
use context_course;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_layout;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\persistence_helper;
use mod_customcert\service\template_repository;
use mod_customcert\tests\fixtures\dummy_element_interface_element;
use mod_customcert\tests\fixtures\dummy_element_interface_with_id_element;
use mod_customcert\tests\fixtures\legacy_genuine_45_element;
use mod_customcert\tests\fixtures\native_v2_control_element;

/**
 * Tests proving that element_repository::save()/create() persist the raw JSON storage
 * representation of a legacy element, not the legacy scalar returned by get_data().
 */
final class issue_968_raw_data_persistence_test extends advanced_testcase {
    use \mod_customcert\tests\legacy_compatibility_diagnostic_test_trait;

    /** @var element_repository */
    private element_repository $repo;

    /** @var template_repository */
    private template_repository $trepo;

    /** @var page_repository */
    private page_repository $prepo;

    /** @var int */
    private int $pageid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        // Each test starts with a clean per-component de-duplication state for the
        // general legacy compatibility diagnostic, independent of test execution order.
        $this->reset_legacy_compatibility_diagnostic_state();

        $registry = new element_registry();
        $registry->register('legacy968', legacy_genuine_45_element::class);
        $registry->register('nativev2968', native_v2_control_element::class);
        $factory = new element_factory($registry);
        $this->repo = new element_repository($factory);
        $this->trepo = new template_repository();
        $this->prepo = new page_repository();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);
        $templateid = $this->trepo->create((object) [
            'name' => 'T968',
            'contextid' => $context->id,
        ]);
        $this->pageid = $this->prepo->create((object) [
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);
    }

    /**
     * Insert a raw element record directly and return its id.
     *
     * @param string $type
     * @param string|null $data
     * @return int
     */
    private function insert_element(string $type, ?string $data): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('customcert_elements', (object) [
            'pageid' => $this->pageid,
            'name' => 'Element under test',
            'element' => $type,
            'data' => $data,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ], true);
    }

    /**
     * Build a JSON-object storage payload for the legacy fixture, mirroring what
     * persistence_helper::to_json_data() would produce for a legacy scalar save.
     *
     * @param string $value
     * @param array $extra Additional migrated metadata fields.
     * @return string
     */
    private function legacy_json_payload(string $value, array $extra = []): string {
        return json_encode(array_merge(['value' => $value], $extra));
    }

    /**
     * Build a registry/factory/repository triple registering only the realistically
     * namespaced legacy968 fixture (customcertelement_legacy968\element), whose type is
     * naturally derived by the inherited get_type() rather than a synthetic type key.
     *
     * @return array{0: element_factory, 1: element_repository}
     */
    private function build_legacy968_factory_and_repo(): array {
        $registry = new element_registry();
        $registry->register('legacy968', \customcertelement_legacy968\element::class);
        $factory = new element_factory($registry);
        return [$factory, new element_repository($factory)];
    }

    /**
     * Build a registry/factory/repository triple registering the realistically
     * namespaced legacyjson968 fixture (customcertelement_legacyjson968\element), whose
     * save_unique_data() returns a JSON object string instead of a scalar or PHP array.
     *
     * @return array{0: element_factory, 1: element_repository}
     */
    private function build_legacyjson968_factory_and_repo(): array {
        $registry = new element_registry();
        $registry->register('legacyjson968', \customcertelement_legacyjson968\element::class);
        $factory = new element_factory($registry);
        return [$factory, new element_repository($factory)];
    }

    /**
     * (a) Legacy save: repository save() must preserve the full JSON object in the DB,
     * while get_data() must still return the historical scalar compatibility value.
     *
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_legacy_save_preserves_json_object_storage(): void {
        global $DB;

        $storagejson = $this->legacy_json_payload('persisted45', [
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);

        $elementid = $this->insert_element('legacy968', $storagejson);
        $instance = $this->repo->load_by_page_id($this->pageid)[0];
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        // Sanity: get_data() returns the legacy scalar compatibility view.
        $this->assertSame('persisted45', $instance->get_data());

        $layout = new element_layout(5, 6, 0, 'L');
        $this->repo->save($instance, $layout);

        $record = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $decoded = json_decode($record->data, true);

        $this->assertIsArray($decoded, 'DB data must remain a JSON object, not a bare scalar.');
        $this->assertSame('persisted45', $decoded['value']);
        $this->assertSame('Helvetica', $decoded['font']);
        $this->assertSame(12, $decoded['fontsize']);
        $this->assertSame('#000000', $decoded['colour']);
        $this->assertSame(50, $decoded['width']);

        // Reloading must still preserve storage data and the get_data() compatibility view.
        $reloaded = $this->repo->load_by_page_id($this->pageid)[0];
        $this->assertSame('persisted45', $reloaded->get_data());
        $this->assertSame($decoded, json_decode($reloaded->get_raw_data(), true));
    }

    /**
     * (b) Metadata preservation: DB must retain the complete object, not just {"value": "x"}.
     *
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_legacy_save_preserves_all_metadata_fields(): void {
        global $DB;

        $storagejson = $this->legacy_json_payload('metatest', [
            'font' => 'Arial',
            'fontsize' => 20,
            'colour' => '#ff00ff',
            'width' => 123,
            'height' => 45,
            'alphachannel' => 90,
        ]);

        $elementid = $this->insert_element('legacy968', $storagejson);
        $instance = $this->repo->load_by_page_id($this->pageid)[0];
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $layout = new element_layout(5, 6, 0, 'L');
        $this->repo->save($instance, $layout);

        $record = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $decoded = json_decode($record->data, true);

        $this->assertSame([
            'value' => 'metatest',
            'font' => 'Arial',
            'fontsize' => 20,
            'colour' => '#ff00ff',
            'width' => 123,
            'height' => 45,
            'alphachannel' => 90,
        ], $decoded);
    }

    /**
     * (c) Legacy create: element_repository::create() must independently preserve the
     * full JSON object, not the scalar compatibility view.
     *
     * Uses the realistically-namespaced customcertelement_legacy968\element fixture so
     * that get_type() (inherited, not overridden) naturally returns 'legacy968', matching
     * how create() derives $record->element for the insert and for the created event.
     *
     * @covers \mod_customcert\service\element_repository::create
     */
    public function test_legacy_create_preserves_json_object_storage(): void {
        global $DB;

        $storagejson = $this->legacy_json_payload('created45', [
            'font' => 'Times',
            'fontsize' => 14,
            'colour' => '#123456',
            'width' => 77,
        ]);

        // Build a not-yet-persisted record and wrap it via the factory, mirroring how
        // to_json_data() output feeds into a new element instance before create().
        $record = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'New legacy element',
            'data' => $storagejson,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        [$factory, $repo] = $this->build_legacy968_factory_and_repo();
        $instance = $factory->create('legacy968', $record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $this->assertSame('created45', $instance->get_data());
        $this->assertSame('legacy968', $instance->get_type());

        $layout = new element_layout(5, 6, 0, 'L');
        $newid = $repo->create($instance, $layout);

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame('legacy968', $dbrecord->element);
        $decoded = json_decode($dbrecord->data, true);

        $this->assertIsArray($decoded, 'DB data after create() must be a valid JSON object.');
        $this->assertSame('created45', $decoded['value']);
        $this->assertSame('Times', $decoded['font']);
        $this->assertSame(14, $decoded['fontsize']);
        $this->assertSame('#123456', $decoded['colour']);
        $this->assertSame(77, $decoded['width']);
    }

    /**
     * (g) Real pipeline save: build submitted form data, run it through the real
     * persistence_helper::to_json_data(), reconstruct via the factory (mirroring
     * edit_element.php), then call element_repository::save() and verify the DB row.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_legacy_save_via_real_persistence_helper_pipeline(): void {
        global $DB;

        [$factory, $repo] = $this->build_legacy968_factory_and_repo();

        // Existing stored record before the edit (as would be loaded by edit_element.php).
        $existingjson = $this->legacy_json_payload('before-edit', [
            'font' => 'Helvetica',
        ]);
        $record = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'Pipeline element',
            'data' => $existingjson,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $existing = $factory->create('legacy968', $record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $newid = $repo->create($existing, new element_layout(5, 6, 0, 'L'));

        // Representative submitted form data (stdClass), as edit_element.php would build.
        $formdata = (object) [
            'legacyvalue' => 'submitted-pipeline-value',
        ];

        // Real production sequence: form data -> to_json_data() -> normalized JSON object.
        $normaliseddata = persistence_helper::to_json_data($existing, $formdata);
        // The method-specific save_unique_data() deprecation is emitted separately.
        $this->assertDebuggingCalled();

        $this->assertJson($normaliseddata);
        $decodedobject = json_decode($normaliseddata);
        $this->assertInstanceOf(\stdClass::class, $decodedobject, 'to_json_data() must produce a JSON object.');
        $this->assertSame('submitted-pipeline-value', $decodedobject->value);

        // Reconstruct the element instance from a record containing the normalized data,
        // mirroring how edit_element.php rebuilds the element before calling save().
        $updatedrecord = (object) [
            'id' => $newid,
            'pageid' => $this->pageid,
            'name' => 'Pipeline element',
            'data' => $normaliseddata,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $updated = $factory->create('legacy968', $updatedrecord);

        $repo->save($updated, new element_layout(5, 6, 0, 'L'));

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $decoded = json_decode($dbrecord->data, true);
        $this->assertIsArray($decoded, 'DB data must be a JSON object after the real pipeline save.');
        $this->assertSame('submitted-pipeline-value', $decoded['value']);

        // Reloading must preserve the historical get_data() scalar compatibility view.
        $reloaded = $factory->create_from_record($dbrecord);
        $this->assertSame('submitted-pipeline-value', $reloaded->get_data());
    }

    /**
     * (h1) Real pipeline edit: an existing upgraded legacy element (whose stored data
     * mirrors the 4.5->5.x migration output) is edited with a new legacy value AND new
     * common visual field values (font, fontsize, colour, width). The real
     * persistence_helper::to_json_data() must combine save_unique_data()'s scalar with
     * the submitted visual fields into one consolidated JSON object, mirroring the
     * historical element::save_form_elements() semantics, instead of discarding them.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_legacy_edit_combines_scalar_and_visual_fields_via_persistence_helper(): void {
        global $DB;

        [$factory, $repo] = $this->build_legacy968_factory_and_repo();

        // Seed a record equivalent to a row that went through the 4.5->5.x upgrade.
        $existingjson = $this->legacy_json_payload('before-edit', [
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);
        $record = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'Visual fields element',
            'data' => $existingjson,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $existing = $factory->create('legacy968', $record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $newid = $repo->create($existing, new element_layout(5, 6, 0, 'L'));
        $reloaded = $factory->create_from_record($DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST));

        // Submitted edit-form data: new legacy value AND new common visual values.
        $formdata = (object) [
            'legacyvalue' => 'after-edit',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
        ];

        // Real production call.
        $normaliseddata = persistence_helper::to_json_data($reloaded, $formdata);
        // The method-specific save_unique_data() deprecation is emitted separately.
        $this->assertDebuggingCalled();

        $this->assertJson($normaliseddata);
        $decoded = json_decode($normaliseddata, true);
        $this->assertIsArray($decoded, 'to_json_data() must produce a JSON object.');
        $this->assertSame('after-edit', $decoded['value']);
        $this->assertSame('Courier', $decoded['font']);
        $this->assertSame(18, $decoded['fontsize']);
        $this->assertSame('#123456', $decoded['colour']);
        $this->assertSame(75, $decoded['width']);

        // Persist via the real repository and confirm the full JSON object hits the DB.
        $updatedrecord = (object) [
            'id' => $newid,
            'pageid' => $this->pageid,
            'name' => 'Visual fields element',
            'data' => $normaliseddata,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $updated = $factory->create('legacy968', $updatedrecord);
        $repo->save($updated, new element_layout(5, 6, 0, 'L'));

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $dbdecoded = json_decode($dbrecord->data, true);
        // Compare as sets: JSON object key order is not semantically significant.
        $this->assertEquals([
            'value' => 'after-edit',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
        ], $dbdecoded);

        // The scalar compatibility view must still work after reload.
        $final = $factory->create_from_record($dbrecord);
        $this->assertSame('after-edit', $final->get_data());
    }

    /**
     * (h2) Missing-field behaviour: when a common visual field is absent from the
     * submitted form data (a synthetic scenario since the real legacy edit form always
     * renders these fields with defaults), the previously stored value must be preserved
     * rather than dropped.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     */
    public function test_legacy_edit_preserves_missing_visual_fields(): void {
        [$factory] = $this->build_legacy968_factory_and_repo();

        $existingjson = $this->legacy_json_payload('before-edit', [
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);
        $record = (object) [
            'id' => 1,
            'pageid' => $this->pageid,
            'name' => 'Partial edit element',
            'data' => $existingjson,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $existing = $factory->create('legacy968', $record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        // Submitted form only carries a new legacyvalue and a new colour; font, fontsize
        // and width are absent from the form data.
        $formdata = (object) [
            'legacyvalue' => 'after-partial-edit',
            'colour' => '#abcdef',
        ];

        $normaliseddata = persistence_helper::to_json_data($existing, $formdata);
        // The method-specific save_unique_data() deprecation is emitted separately.
        $this->assertDebuggingCalled();
        $decoded = json_decode($normaliseddata, true);

        $this->assertSame('after-partial-edit', $decoded['value']);
        $this->assertSame('#abcdef', $decoded['colour']);
        // Missing fields preserve the previously stored values.
        $this->assertSame('Helvetica', $decoded['font']);
        $this->assertSame(12, $decoded['fontsize']);
        $this->assertSame(50, $decoded['width']);
    }

    /**
     * (h4) Recognised compatibility-wrapper metadata not covered by the 2025122800
     * migration (height, alphachannel) must survive a real legacy edit, since
     * mod_customcert\element::is_generic_migration_wrapper() explicitly allows those
     * keys and get_data() can legitimately unwrap a wrapper containing them.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_legacy_edit_preserves_height_and_alphachannel_metadata(): void {
        global $DB;

        [$factory, $repo] = $this->build_legacy968_factory_and_repo();

        $existingjson = $this->legacy_json_payload('before', [
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
            'height' => 45,
            'alphachannel' => 0.5,
        ]);
        $record = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'Height/alphachannel element',
            'data' => $existingjson,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $existing = $factory->create('legacy968', $record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $newid = $repo->create($existing, new element_layout(5, 6, 0, 'L'));
        $reloaded = $factory->create_from_record($DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST));

        $formdata = (object) [
            'legacyvalue' => 'after',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
        ];

        $normaliseddata = persistence_helper::to_json_data($reloaded, $formdata);
        // The method-specific save_unique_data() deprecation is emitted separately.
        $this->assertDebuggingCalled();
        $decoded = json_decode($normaliseddata, true);

        $this->assertSame('after', $decoded['value']);
        $this->assertSame('Courier', $decoded['font']);
        $this->assertSame(18, $decoded['fontsize']);
        $this->assertSame('#123456', $decoded['colour']);
        $this->assertSame(75, $decoded['width']);
        // The height/alphachannel fields were not part of the submitted form and are not
        // covered by the 2025122800 migration, but must still survive since they are
        // recognised compatibility-wrapper metadata already present in storage.
        $this->assertSame(45, $decoded['height']);
        $this->assertSame(0.5, $decoded['alphachannel']);

        $updatedrecord = (object) [
            'id' => $newid,
            'pageid' => $this->pageid,
            'name' => 'Height/alphachannel element',
            'data' => $normaliseddata,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $updated = $factory->create('legacy968', $updatedrecord);
        $repo->save($updated, new element_layout(5, 6, 0, 'L'));

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $dbdecoded = json_decode($dbrecord->data, true);
        $this->assertEquals([
            'value' => 'after',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
            'height' => 45,
            'alphachannel' => 0.5,
        ], $dbdecoded);

        $final = $factory->create_from_record($dbrecord);
        $this->assertSame('after', $final->get_data());
    }

    /**
     * (h5) Safety control: arbitrary structured (non-wrapper) legacy/plugin data must
     * NOT be blindly treated as a generic scalar compatibility wrapper. When
     * save_unique_data() itself returns structured data, the existing raw storage is
     * not merged in, so stale plugin-specific keys cannot resurrect.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     */
    public function test_legacy_edit_does_not_merge_non_wrapper_structured_data(): void {
        [$factory] = $this->build_legacy968_factory_and_repo();

        // Existing raw data contains a plugin-specific key ('extraplugindata') outside
        // the recognised compatibility-wrapper metadata, so it is NOT a generic
        // migration wrapper per is_generic_migration_wrapper().
        $existingjson = json_encode([
            'value' => 'before',
            'extraplugindata' => 'must-not-survive',
        ]);
        $record = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'Non-wrapper element',
            'data' => $existingjson,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $existing = $factory->create('legacy968', $record);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();
        $this->assertFalse(
            element::is_generic_migration_wrapper($existingjson),
            'Sanity: this payload must not be classified as a generic migration wrapper.'
        );

        $formdata = (object) [
            'legacyvalue' => 'after',
            'font' => 'Courier',
        ];

        $normaliseddata = persistence_helper::to_json_data($existing, $formdata);
        // The method-specific save_unique_data() deprecation is emitted separately.
        $this->assertDebuggingCalled();
        $decoded = json_decode($normaliseddata, true);

        $this->assertSame('after', $decoded['value']);
        $this->assertSame('Courier', $decoded['font']);
        $this->assertArrayNotHasKey(
            'extraplugindata',
            $decoded,
            'Non-wrapper plugin-specific keys must not be blindly merged into the new payload.'
        );
    }

    /**
     * (h) Real pipeline create: build submitted form data, run it through the real
     * persistence_helper::to_json_data(), reconstruct via the factory, then call
     * element_repository::create() and verify the DB row. Independent of the save() test.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     * @covers \mod_customcert\service\element_repository::create
     */
    public function test_legacy_create_via_real_persistence_helper_pipeline(): void {
        global $DB;

        [$factory, $repo] = $this->build_legacy968_factory_and_repo();

        // A transient (not-yet-persisted, id=0) element used purely to invoke
        // save_unique_data() via to_json_data(), mirroring the "new element" form flow.
        $transientrecord = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'New pipeline element',
            'data' => null,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $transient = $factory->create('legacy968', $transientrecord);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $formdata = (object) [
            'legacyvalue' => 'created-pipeline-value',
        ];

        $normaliseddata = persistence_helper::to_json_data($transient, $formdata);
        // The method-specific save_unique_data() deprecation is emitted separately.
        $this->assertDebuggingCalled();

        $this->assertJson($normaliseddata);
        $decodedobject = json_decode($normaliseddata);
        $this->assertInstanceOf(\stdClass::class, $decodedobject, 'to_json_data() must produce a JSON object.');
        $this->assertSame('created-pipeline-value', $decodedobject->value);

        $newrecord = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'New pipeline element',
            'data' => $normaliseddata,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $instance = $factory->create('legacy968', $newrecord);

        $newid = $repo->create($instance, new element_layout(5, 6, 0, 'L'));

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame('legacy968', $dbrecord->element);
        $decoded = json_decode($dbrecord->data, true);
        $this->assertIsArray($decoded, 'DB data after create() must be a valid JSON object.');
        $this->assertSame('created-pipeline-value', $decoded['value']);
    }

    /**
     * (h3) Real pipeline create with visual fields: a brand-new legacy element is
     * submitted with common visual field values alongside the legacy scalar. The real
     * persistence_helper::to_json_data() must produce the complete JSON object (value +
     * font + fontsize + colour + width), element_repository::create() must preserve it,
     * and reloading must still expose the scalar via get_data().
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     * @covers \mod_customcert\service\element_repository::create
     */
    public function test_legacy_create_combines_scalar_and_visual_fields_via_persistence_helper(): void {
        global $DB;

        [$factory, $repo] = $this->build_legacy968_factory_and_repo();

        $transientrecord = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'New pipeline element with visuals',
            'data' => null,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $transient = $factory->create('legacy968', $transientrecord);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $formdata = (object) [
            'legacyvalue' => 'created-with-visuals',
            'font' => 'Arial',
            'fontsize' => 16,
            'colour' => '#00ff00',
            'width' => 42,
        ];

        $normaliseddata = persistence_helper::to_json_data($transient, $formdata);
        // The method-specific save_unique_data() deprecation is emitted separately.
        $this->assertDebuggingCalled();

        $this->assertJson($normaliseddata);
        $decoded = json_decode($normaliseddata, true);
        $this->assertIsArray($decoded, 'to_json_data() must produce a JSON object.');
        $this->assertSame('created-with-visuals', $decoded['value']);
        $this->assertSame('Arial', $decoded['font']);
        $this->assertSame(16, $decoded['fontsize']);
        $this->assertSame('#00ff00', $decoded['colour']);
        $this->assertSame(42, $decoded['width']);

        $newrecord = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'New pipeline element with visuals',
            'data' => $normaliseddata,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $instance = $factory->create('legacy968', $newrecord);

        $newid = $repo->create($instance, new element_layout(5, 6, 0, 'L'));

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame('legacy968', $dbrecord->element);
        $dbdecoded = json_decode($dbrecord->data, true);
        // Compare as sets: JSON object key order is not semantically significant.
        $this->assertEquals([
            'value' => 'created-with-visuals',
            'font' => 'Arial',
            'fontsize' => 16,
            'colour' => '#00ff00',
            'width' => 42,
        ], $dbdecoded);

        $reloaded = $factory->create_from_record($dbrecord);
        $this->assertSame('created-with-visuals', $reloaded->get_data());
    }

    /**
     * (j1) Historical save_unique_data() returning a JSON object string must be treated
     * as structured plugin data, not wrapped under "value" as a scalar. Exercises the
     * real create() pipeline: submitted form data -> persistence_helper::to_json_data()
     * -> factory reconstruction -> element_repository::create() -> direct DB read.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     * @covers \mod_customcert\service\element_repository::create
     */
    public function test_legacy_create_with_json_string_save_unique_data(): void {
        global $DB;

        [$factory, $repo] = $this->build_legacyjson968_factory_and_repo();

        $transientrecord = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'New JSON-string pipeline element',
            'data' => null,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacyjson968',
        ];
        $transient = $factory->create('legacyjson968', $transientrecord);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $formdata = (object) [
            'first' => 'A',
            'second' => 'B',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ];

        $normaliseddata = persistence_helper::to_json_data($transient, $formdata);
        // The method-specific save_unique_data() deprecation is emitted separately.
        $this->assertDebuggingCalled();
        $decoded = json_decode($normaliseddata, true);

        // Must remain structured: "first"/"second" as top-level keys, never a
        // {"value": "{\"first\":\"A\",\"second\":\"B\"}"} string-wrapped payload.
        $this->assertArrayNotHasKey('value', $decoded);
        $this->assertSame('A', $decoded['first']);
        $this->assertSame('B', $decoded['second']);
        $this->assertSame('Helvetica', $decoded['font']);
        $this->assertSame(12, $decoded['fontsize']);
        $this->assertSame('#000000', $decoded['colour']);
        $this->assertSame(50, $decoded['width']);

        $newrecord = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'New JSON-string pipeline element',
            'data' => $normaliseddata,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacyjson968',
        ];
        $instance = $factory->create('legacyjson968', $newrecord);
        $newid = $repo->create($instance, new element_layout(5, 6, 0, 'L'));

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame('legacyjson968', $dbrecord->element);
        $dbdecoded = json_decode($dbrecord->data, true);
        $this->assertArrayNotHasKey('value', $dbdecoded, 'DB payload must stay a JSON object, not a string wrapped under "value".');
        $this->assertEquals([
            'first' => 'A',
            'second' => 'B',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ], $dbdecoded);
    }

    /**
     * (j2) Editing an existing structured legacy element whose save_unique_data()
     * returns a fresh JSON object string: the new structured fields must replace the
     * old ones, submitted visuals must merge in, and stale arbitrary previous
     * plugin-specific fields must not be resurrected.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_legacy_edit_with_json_string_save_unique_data(): void {
        global $DB;

        [$factory, $repo] = $this->build_legacyjson968_factory_and_repo();

        // Existing storage has stale structured fields plus visuals from a previous save.
        $existingjson = json_encode([
            'first' => 'old-first',
            'second' => 'old-second',
            'stale' => 'must-not-survive',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);
        $elementid = $this->insert_element('legacyjson968', $existingjson);
        $existingrecord = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $existing = $factory->create('legacyjson968', $existingrecord);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        $formdata = (object) [
            'first' => 'new-first',
            'second' => 'new-second',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
        ];

        $normaliseddata = persistence_helper::to_json_data($existing, $formdata);
        // The method-specific save_unique_data() deprecation is emitted separately.
        $this->assertDebuggingCalled();
        $decoded = json_decode($normaliseddata, true);
        $this->assertArrayNotHasKey('value', $decoded);
        $this->assertArrayNotHasKey('stale', $decoded, 'Stale previous plugin-specific fields must not be resurrected.');
        $this->assertSame('new-first', $decoded['first']);
        $this->assertSame('new-second', $decoded['second']);
        $this->assertSame('Courier', $decoded['font']);
        $this->assertSame(18, $decoded['fontsize']);
        $this->assertSame('#123456', $decoded['colour']);
        $this->assertSame(75, $decoded['width']);

        $updatedrecord = (object) [
            'id' => $elementid,
            'pageid' => $this->pageid,
            'name' => 'JSON-string element',
            'data' => $normaliseddata,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacyjson968',
        ];
        $updated = $factory->create('legacyjson968', $updatedrecord);
        $repo->save($updated, new element_layout(5, 6, 0, 'L'));

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $dbdecoded = json_decode($dbrecord->data, true);
        $this->assertArrayNotHasKey('value', $dbdecoded);
        $this->assertArrayNotHasKey('stale', $dbdecoded);
        $this->assertEquals([
            'first' => 'new-first',
            'second' => 'new-second',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
        ], $dbdecoded);
    }

    /**
     * (j3) Safety control: plain scalar strings, JSON scalar strings, JSON numbers,
     * booleans, null and JSON lists must NOT be misclassified as JSON object structured
     * data; they must still use the historical scalar compatibility wrapper.
     *
     * @covers \mod_customcert\service\persistence_helper::to_object_json
     */
    public function test_legacy_scalar_and_non_object_json_values_still_use_wrapper(): void {
        [$factory] = $this->build_legacy968_factory_and_repo();

        $transientrecord = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'Scalar-safety element',
            'data' => null,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $transient = $factory->create('legacy968', $transientrecord);
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        // A plain string is scalar compatibility data, must be wrapped under "value".
        $formdata = (object) ['legacyvalue' => '[1,2,3]'];
        $decoded = json_decode(persistence_helper::to_json_data($transient, $formdata), true);
        // The method-specific save_unique_data() deprecation is emitted separately.
        $this->assertDebuggingCalled();
        $this->assertSame('[1,2,3]', $decoded['value'], 'A JSON list string must not be treated as a structured object.');

        $formdata = (object) ['legacyvalue' => '42'];
        $decoded = json_decode(persistence_helper::to_json_data($transient, $formdata), true);
        $this->assertDebuggingCalled();
        $this->assertSame('42', $decoded['value'], 'A JSON scalar-number string must not be treated as a structured object.');

        $formdata = (object) ['legacyvalue' => 'true'];
        $decoded = json_decode(persistence_helper::to_json_data($transient, $formdata), true);
        $this->assertDebuggingCalled();
        $this->assertSame('true', $decoded['value'], 'A JSON boolean string must not be treated as a structured object.');

        $formdata = (object) ['legacyvalue' => 'plain-string'];
        $decoded = json_decode(persistence_helper::to_json_data($transient, $formdata), true);
        $this->assertDebuggingCalled();
        $this->assertSame('plain-string', $decoded['value']);
    }

    /**
     * (d) Native-v2 control: current native Element System v2 persistence is unchanged.
     *
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_native_v2_save_persistence_unchanged(): void {
        global $DB;

        $data = json_encode(['value' => 'native-value']);
        $elementid = $this->insert_element('nativev2968', $data);
        $instance = $this->repo->load_by_page_id($this->pageid)[0];

        $this->assertInstanceOf(native_v2_control_element::class, $instance);
        // The raw data accessor must reflect the exact untouched storage representation.
        $this->assertSame($data, $instance->get_raw_data());

        $layout = new element_layout(5, 6, 0, 'L');
        $this->repo->save($instance, $layout);

        $record = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $this->assertSame(['value' => 'native-value'], json_decode($record->data, true));
    }

    /**
     * (e) Bundled element control: bundled/first-party structured-JSON persistence is unchanged.
     *
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_bundled_element_save_persistence_unchanged(): void {
        global $DB;

        $factory = element_factory::build_with_defaults();
        $repo = new element_repository($factory);

        $data = json_encode(['text' => 'Hello world']);
        $elementid = $this->insert_element('text', $data);

        $instance = $repo->load_by_page_id($this->pageid)[0];
        $this->assertInstanceOf(\customcertelement_text\element::class, $instance);

        $layout = new element_layout(5, 6, 0, 'L');
        $repo->save($instance, $layout);

        $record = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $decoded = json_decode($record->data, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Hello world', $decoded['text']);
    }

    /**
     * (f) Compatibility view: get_data() historical scalar compatibility behaviour still
     * works correctly after the fix, even though the DB retains a JSON object.
     *
     * @covers \mod_customcert\element::get_data
     * @covers \mod_customcert\element::get_raw_data
     */
    public function test_get_data_compatibility_preserved_after_fix(): void {
        $storagejson = $this->legacy_json_payload('compatvalue', [
            'font' => 'Courier',
        ]);

        $elementid = $this->insert_element('legacy968', $storagejson);
        $instance = $this->repo->load_by_page_id($this->pageid)[0];
        // The factory emits the general legacy-compatibility diagnostic when wrapping.
        $this->assertDebuggingCalled();

        // The compatibility accessor must keep returning the legacy scalar.
        $this->assertSame('compatvalue', $instance->get_data());
        // The raw data accessor must return the untouched JSON storage representation.
        $decoded = json_decode($instance->get_raw_data(), true);
        $this->assertSame('compatvalue', $decoded['value']);
        $this->assertSame('Courier', $decoded['font']);

        unset($elementid);
    }

    /**
     * (i) Direct v2 BC control: a fixture implementing only the PRE-#968
     * element_interface contract (not the new optional raw_data_element_interface) must
     * still persist correctly via element_repository::save() through the get_data()
     * fallback path.
     *
     * Note: element_repository::create() additionally needs the factory/registry to
     * reconstruct the element for the creation event (element->get_type() lookup), which
     * requires the class to be either native (form_element_interface +
     * renderable_element_interface) or legacy (extends mod_customcert\element) — a
     * pre-existing architectural constraint independent of this fix. save() has no such
     * requirement, so it is the correct vehicle for this direct-element_interface control.
     *
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_direct_element_interface_save_fallback_to_get_data(): void {
        global $DB;

        // Confirm the canonical dummy fixture remains a direct element_interface-only
        // implementation (does not implement the new optional raw_data_element_interface).
        $dummy = new dummy_element_interface_element($this->pageid, 'dummy968');
        $this->assertNotInstanceOf(
            \mod_customcert\element\raw_data_element_interface::class,
            $dummy,
            'This fixture must remain a direct element_interface implementation only.'
        );

        // Pre-insert a real row so save()'s update targets an existing id.
        $existingid = $this->insert_element('dummy968', 'placeholder');

        // A minimal direct element_interface implementation exposing that real id (the
        // canonical dummy fixture's get_id() always returns 0, so a small dedicated
        // fixture is used here instead, per the issue guidance to use "another minimal
        // direct-element_interface fixture if more appropriate").
        $element = new dummy_element_interface_with_id_element($existingid, $this->pageid, 'dummy968');
        $this->assertNotInstanceOf(\mod_customcert\element\raw_data_element_interface::class, $element);

        $layout = new element_layout(5, 6, 0, 'L');
        $this->repo->save($element, $layout);

        $updated = $DB->get_record('customcert_elements', ['id' => $existingid], '*', MUST_EXIST);
        $this->assertSame('Dummy data', $updated->data);
    }
}
