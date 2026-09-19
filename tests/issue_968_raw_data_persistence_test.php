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

require_once(__DIR__ . '/fixtures/legacy_get_data_element.php');
require_once(__DIR__ . '/fixtures/customcertelement_legacy968/element.php');
require_once(__DIR__ . '/fixtures/customcertelement_legacyjson968/element.php');
require_once(__DIR__ . '/fixtures/dummy_element_interface_element.php');
require_once(__DIR__ . '/fixtures/dummy_element_interface_with_id_element.php');

use advanced_testcase;
use context_course;
use customcertelement_legacy968\element as legacy968_element;
use customcertelement_legacyjson968\element as legacyjson968_element;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_layout;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\persistence_helper;
use mod_customcert\service\template_repository;
use mod_customcert\tests\fixtures\dummy_element_interface_element;
use mod_customcert\tests\fixtures\dummy_element_interface_with_id_element;
use mod_customcert\tests\fixtures\legacy_get_data_element;

/**
 * Tests proving that element_repository::save()/create() persist the raw JSON storage
 * representation of a legacy element, not the legacy scalar returned by get_data().
 */
final class issue_968_raw_data_persistence_test extends advanced_testcase {
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

        $registry = new element_registry();
        $registry->register('legacy968', legacy_get_data_element::class);
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
     * Build a JSON-object storage payload for the legacy fixture, mirroring what the
     * 4.5 to 5.2 upgrade migration produces for a legacy scalar save.
     *
     * @param string $value
     * @param array $extra Additional migrated metadata fields.
     * @return string
     */
    private function legacy_json_payload(string $value, array $extra = []): string {
        return json_encode(array_merge(['value' => $value], $extra));
    }

    /**
     * (a) Legacy save: repository save() must preserve the full JSON object in the DB,
     * while get_data() must still return the historical scalar compatibility value.
     *
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_legacy_save_preserves_json_object_storage(): void {
        global $DB;

        $storagejson = $this->legacy_json_payload('persisted52', [
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);

        $elementid = $this->insert_element('legacy968', $storagejson);
        $instance = $this->repo->load_by_page_id($this->pageid)[0];

        // Sanity: get_data() returns the legacy scalar compatibility view.
        $this->assertSame('persisted52', $instance->get_data());

        $layout = new element_layout(5, 6, 0, 'L');
        $this->repo->save($instance, $layout);

        $record = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $decoded = json_decode($record->data, true);

        $this->assertIsArray($decoded, 'DB data must remain a JSON object, not a bare scalar.');
        $this->assertSame('persisted52', $decoded['value']);
        $this->assertSame('Helvetica', $decoded['font']);
        $this->assertSame(12, $decoded['fontsize']);
        $this->assertSame('#000000', $decoded['colour']);
        $this->assertSame(50, $decoded['width']);

        // Reloading must still preserve storage data and the get_data() compatibility view.
        $reloaded = $this->repo->load_by_page_id($this->pageid)[0];
        $this->assertSame('persisted52', $reloaded->get_data());
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
     * @covers \mod_customcert\service\element_repository::create
     */
    public function test_legacy_create_preserves_json_object_storage(): void {
        global $DB;

        $storagejson = $this->legacy_json_payload('created52', [
            'font' => 'Times',
            'fontsize' => 14,
            'colour' => '#123456',
            'width' => 77,
        ]);

        // Build a not-yet-persisted record and wrap it via the factory, mirroring how
        // upgrade-migrated data feeds into a new element instance before create().
        //
        // Uses a realistic fixture placed under a customcertelement_legacy968 namespace
        // (rather than mod_customcert\tests\fixtures) so that the inherited get_type()
        // naturally derives 'legacy968' from the class's own top-level namespace segment,
        // as element_repository::create() calls $element->get_type() internally when
        // re-fetching the created record for the element_created event.
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
        $factory = new element_factory((function () {
            $registry = new element_registry();
            $registry->register('legacy968', legacy968_element::class);
            return $registry;
        })());
        $repo = new element_repository($factory);
        $instance = $factory->create('legacy968', $record);

        $this->assertSame('created52', $instance->get_data());

        $layout = new element_layout(5, 6, 0, 'L');
        $newid = $repo->create($instance, $layout);

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $decoded = json_decode($dbrecord->data, true);

        $this->assertIsArray($decoded, 'DB data after create() must be a valid JSON object.');
        $this->assertSame('created52', $decoded['value']);
        $this->assertSame('Times', $decoded['font']);
        $this->assertSame(14, $decoded['fontsize']);
        $this->assertSame('#123456', $decoded['colour']);
        $this->assertSame(77, $decoded['width']);
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

        $factory = new element_factory((function () {
            $registry = new element_registry();
            $registry->register('legacyjson968', legacyjson968_element::class);
            return $registry;
        })());
        $repo = new element_repository($factory);

        $transient = $factory->create('legacyjson968', (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'New JSON-string pipeline element',
            'data' => null,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacyjson968',
        ]);

        $formdata = (object) [
            'first' => 'A',
            'second' => 'B',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ];

        $normaliseddata = persistence_helper::to_json_data($transient, $formdata);
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

        $instance = $factory->create('legacyjson968', (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'New JSON-string pipeline element',
            'data' => $normaliseddata,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacyjson968',
        ]);
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

        $factory = new element_factory((function () {
            $registry = new element_registry();
            $registry->register('legacyjson968', legacyjson968_element::class);
            return $registry;
        })());
        $repo = new element_repository($factory);

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

        $formdata = (object) [
            'first' => 'new-first',
            'second' => 'new-second',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
        ];

        $normaliseddata = persistence_helper::to_json_data($existing, $formdata);
        $decoded = json_decode($normaliseddata, true);
        $this->assertArrayNotHasKey('value', $decoded);
        $this->assertArrayNotHasKey('stale', $decoded, 'Stale previous plugin-specific fields must not be resurrected.');
        $this->assertSame('new-first', $decoded['first']);
        $this->assertSame('new-second', $decoded['second']);
        $this->assertSame('Courier', $decoded['font']);
        $this->assertSame(18, $decoded['fontsize']);
        $this->assertSame('#123456', $decoded['colour']);
        $this->assertSame(75, $decoded['width']);

        $updated = $factory->create('legacyjson968', (object) [
            'id' => $elementid,
            'pageid' => $this->pageid,
            'name' => 'JSON-string element',
            'data' => $normaliseddata,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacyjson968',
        ]);
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
        $factory = new element_factory((function () {
            $registry = new element_registry();
            $registry->register('legacy968', legacy968_element::class);
            return $registry;
        })());

        $transient = $factory->create('legacy968', (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'Scalar-safety element',
            'data' => null,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ]);

        $formdata = (object) ['value' => '[1,2,3]'];
        $decoded = json_decode(persistence_helper::to_json_data($transient, $formdata), true);
        $this->assertSame('[1,2,3]', $decoded['value'], 'A JSON list string must not be treated as a structured object.');

        $formdata = (object) ['value' => '42'];
        $decoded = json_decode(persistence_helper::to_json_data($transient, $formdata), true);
        $this->assertSame('42', $decoded['value'], 'A JSON scalar-number string must not be treated as a structured object.');

        $formdata = (object) ['value' => 'true'];
        $decoded = json_decode(persistence_helper::to_json_data($transient, $formdata), true);
        $this->assertSame('true', $decoded['value'], 'A JSON boolean string must not be treated as a structured object.');

        $formdata = (object) ['value' => 'plain-string'];
        $decoded = json_decode(persistence_helper::to_json_data($transient, $formdata), true);
        $this->assertSame('plain-string', $decoded['value']);
    }

    /**
     * (d) Bundled element control: bundled/first-party structured-JSON persistence is unchanged.
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
     * (e) Compatibility view: get_data() historical scalar compatibility behaviour still
     * works correctly after the fix, even though the DB retains a JSON object.
     *
     * @covers \mod_customcert\element::get_data
     * @covers \mod_customcert\element::get_raw_data
     */
    public function test_get_data_compatibility_preserved_after_fix(): void {
        $storagejson = $this->legacy_json_payload('compatvalue', [
            'font' => 'Courier',
        ]);

        $this->insert_element('legacy968', $storagejson);
        $instance = $this->repo->load_by_page_id($this->pageid)[0];

        // The compatibility accessor must keep returning the legacy scalar.
        $this->assertSame('compatvalue', $instance->get_data());
        // The raw data accessor must return the untouched JSON storage representation.
        $decoded = json_decode($instance->get_raw_data(), true);
        $this->assertSame('compatvalue', $decoded['value']);
        $this->assertSame('Courier', $decoded['font']);
    }

    /**
     * (f) Real production pipeline: build representative form data, pass it through the
     * real persistence_helper::to_json_data(), reconstruct the element via the factory,
     * then create() it via the repository, and confirm the full JSON object survives a
     * direct DB read.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     * @covers \mod_customcert\service\element_repository::create
     */
    public function test_real_pipeline_create_preserves_full_json(): void {
        global $DB;

        $factory = new element_factory((function () {
            $registry = new element_registry();
            $registry->register('legacy968', legacy968_element::class);
            return $registry;
        })());
        $repo = new element_repository($factory);

        // A throwaway instance is needed only to invoke the (non-static) real
        // persistence_helper::to_json_data() pipeline, mirroring how the edit form
        // handler builds JSON from submitted form data before persistence.
        $blank = $factory->create('legacy968', (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'Blank',
            'data' => null,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ]);

        $formdata = (object) [
            'value' => 'realpipeline',
            'font' => 'Verdana',
            'fontsize' => 18,
            'colour' => '#00ff00',
            'width' => 88,
        ];

        $json = persistence_helper::to_json_data($blank, $formdata);

        $record = (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'Pipeline element',
            'data' => $json,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ];
        $instance = $factory->create('legacy968', $record);

        $layout = new element_layout(5, 6, 0, 'L');
        $newid = $repo->create($instance, $layout);

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $decoded = json_decode($dbrecord->data, true);

        // Compare as sets: JSON object key order is not semantically significant.
        $this->assertEquals([
            'value' => 'realpipeline',
            'font' => 'Verdana',
            'fontsize' => 18,
            'colour' => '#00ff00',
            'width' => 88,
        ], $decoded);
    }

    /**
     * (f2) Real pipeline edit: an existing upgraded legacy element (whose stored data
     * mirrors the 4.5->5.2 migration output) is edited with a new legacy value AND new
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

        $factory = new element_factory((function () {
            $registry = new element_registry();
            $registry->register('legacy968', legacy968_element::class);
            return $registry;
        })());
        $repo = new element_repository($factory);

        // Seed a record equivalent to a row that went through the 4.5->5.2 upgrade.
        $existingjson = $this->legacy_json_payload('before-edit', [
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);
        $elementid = $this->insert_element('legacy968', $existingjson);
        $existing = $factory->create('legacy968', $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST));

        // Submitted edit-form data: new legacy value AND new common visual values.
        $formdata = (object) [
            'value' => 'after-edit',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
        ];

        // Real production call.
        $normaliseddata = persistence_helper::to_json_data($existing, $formdata);

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
            'id' => $elementid,
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
        $layout = new element_layout(5, 6, 0, 'L');
        $repo->save($updated, $layout);

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
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
        $final = $factory->create('legacy968', $dbrecord);
        $this->assertSame('after-edit', $final->get_data());
    }

    /**
     * (f3) Missing-field behaviour: when a common visual field is absent from the
     * submitted form data (a synthetic scenario since the real legacy edit form always
     * renders these fields with defaults, see element_helper::render_form_element_*()),
     * the previously stored value must be preserved rather than dropped.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     */
    public function test_legacy_edit_preserves_missing_visual_fields(): void {
        global $DB;

        $factory = new element_factory((function () {
            $registry = new element_registry();
            $registry->register('legacy968', legacy968_element::class);
            return $registry;
        })());

        $existingjson = $this->legacy_json_payload('before-edit', [
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);
        $elementid = $this->insert_element('legacy968', $existingjson);
        $existing = $factory->create('legacy968', $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST));

        // Submitted form only carries a new value and a new colour; font, fontsize
        // and width are absent from the form data.
        $formdata = (object) [
            'value' => 'after-partial-edit',
            'colour' => '#abcdef',
        ];

        $normaliseddata = persistence_helper::to_json_data($existing, $formdata);
        $decoded = json_decode($normaliseddata, true);

        $this->assertSame('after-partial-edit', $decoded['value']);
        $this->assertSame('#abcdef', $decoded['colour']);
        // Missing fields preserve the previously stored values.
        $this->assertSame('Helvetica', $decoded['font']);
        $this->assertSame(12, $decoded['fontsize']);
        $this->assertSame(50, $decoded['width']);
    }

    /**
     * (f3b) Recognised compatibility-wrapper metadata not covered by the 2025122800
     * migration (height, alphachannel) must survive a real legacy edit, since
     * mod_customcert\element::is_generic_migration_wrapper() explicitly allows those
     * keys and get_data() can legitimately unwrap a wrapper containing them.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_legacy_edit_preserves_height_and_alphachannel_metadata(): void {
        global $DB;

        $factory = new element_factory((function () {
            $registry = new element_registry();
            $registry->register('legacy968', legacy968_element::class);
            return $registry;
        })());
        $repo = new element_repository($factory);

        $existingjson = $this->legacy_json_payload('before', [
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
            'height' => 45,
            'alphachannel' => 0.5,
        ]);
        $elementid = $this->insert_element('legacy968', $existingjson);
        $existing = $factory->create('legacy968', $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST));

        $formdata = (object) [
            'value' => 'after',
            'font' => 'Courier',
            'fontsize' => 18,
            'colour' => '#123456',
            'width' => 75,
        ];

        $normaliseddata = persistence_helper::to_json_data($existing, $formdata);
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
            'id' => $elementid,
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

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
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

        $final = $factory->create('legacy968', $dbrecord);
        $this->assertSame('after', $final->get_data());
    }

    /**
     * (f3c) Safety control: arbitrary structured (non-wrapper) legacy/plugin data must
     * NOT be blindly treated as a generic scalar compatibility wrapper. When
     * save_unique_data() itself returns structured data, the existing raw storage is
     * not merged in, so stale plugin-specific keys cannot resurrect.
     *
     * @covers \mod_customcert\service\persistence_helper::to_json_data
     */
    public function test_legacy_edit_does_not_merge_non_wrapper_structured_data(): void {
        global $DB;

        $factory = new element_factory((function () {
            $registry = new element_registry();
            $registry->register('legacy968', legacy968_element::class);
            return $registry;
        })());

        // Existing raw data contains a plugin-specific key ('extraplugindata') outside
        // the recognised compatibility-wrapper metadata, so it is NOT a generic
        // migration wrapper per is_generic_migration_wrapper().
        $existingjson = json_encode([
            'value' => 'before',
            'extraplugindata' => 'must-not-survive',
        ]);
        $elementid = $this->insert_element('legacy968', $existingjson);
        $existing = $factory->create('legacy968', $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST));
        $this->assertFalse(
            element::is_generic_migration_wrapper($existingjson),
            'Sanity: this payload must not be classified as a generic migration wrapper.'
        );

        $formdata = (object) [
            'value' => 'after',
            'font' => 'Courier',
        ];

        $normaliseddata = persistence_helper::to_json_data($existing, $formdata);
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
     * (f4) Real pipeline create with visual fields: a brand-new legacy element is
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

        $factory = new element_factory((function () {
            $registry = new element_registry();
            $registry->register('legacy968', legacy968_element::class);
            return $registry;
        })());
        $repo = new element_repository($factory);

        $transient = $factory->create('legacy968', (object) [
            'id' => 0,
            'pageid' => $this->pageid,
            'name' => 'New pipeline element with visuals',
            'data' => null,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => 'legacy968',
        ]);

        $formdata = (object) [
            'value' => 'created-with-visuals',
            'font' => 'Arial',
            'fontsize' => 16,
            'colour' => '#00ff00',
            'width' => 42,
        ];

        $normaliseddata = persistence_helper::to_json_data($transient, $formdata);

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
        $dbdecoded = json_decode($dbrecord->data, true);
        // Compare as sets: JSON object key order is not semantically significant.
        $this->assertEquals([
            'value' => 'created-with-visuals',
            'font' => 'Arial',
            'fontsize' => 16,
            'colour' => '#00ff00',
            'width' => 42,
        ], $dbdecoded);

        $reloaded = $factory->create('legacy968', $dbrecord);
        $this->assertSame('created-with-visuals', $reloaded->get_data());
    }

    /**
     * (g) BC control: a fixture implementing only the minimal element_interface (not
     * raw_data_element_interface) must still persist correctly via element_repository
     * through the get_data() fallback path.
     *
     * Note: element_repository::create() additionally needs the factory/registry to
     * reconstruct the element for the creation event (element->get_type() lookup), which
     * requires the class to be either native (form_element_interface +
     * renderable_element_interface) or legacy (extends mod_customcert\element) — a
     * pre-existing architectural constraint independent of this fix. save() has no such
     * requirement (it uses get_id() directly), so it is the correct vehicle for this
     * direct-element_interface control.
     *
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_direct_element_interface_only_fixture_persists_via_get_data_fallback(): void {
        global $DB;

        $instance = new dummy_element_interface_element($this->pageid, 'dummyinterface');
        $this->assertNotInstanceOf(
            \mod_customcert\element\raw_data_element_interface::class,
            $instance,
            'This fixture must implement only element_interface, not raw_data_element_interface.'
        );

        // Pre-insert a real row so save()'s update targets an existing id.
        $existingid = $this->insert_element('dummyinterface', 'placeholder');

        // The canonical dummy fixture's get_id() always returns 0, so a small dedicated
        // fixture exposing a real id is used here instead.
        $element = new dummy_element_interface_with_id_element($existingid, $this->pageid, 'dummyinterface');
        $this->assertNotInstanceOf(\mod_customcert\element\raw_data_element_interface::class, $element);

        $layout = new element_layout(5, 6, 0, 'L');
        $this->repo->save($element, $layout);

        $record = $DB->get_record('customcert_elements', ['id' => $existingid], '*', MUST_EXIST);
        $this->assertSame('Dummy data', $record->data);
    }
}
