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
 * Regression tests for issue #999: structured third-party payloads must not be unwrapped
 * into a legacy scalar based on plugin identity.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_customcert\element
 */

declare(strict_types=1);

namespace mod_customcert;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/customcertelement_structuredv2/element.php');
require_once(__DIR__ . '/fixtures/customcertelement_structuredv2/exporter.php');
require_once(__DIR__ . '/fixtures/customcertelement_legacy968/element.php');
require_once(__DIR__ . '/fixtures/customcertelement_nativev2nopersist/element.php');
require_once(__DIR__ . '/fixtures/customcertelement_nativev2nopersist/exporter.php');

use advanced_testcase;
use context_course;
use core\clock;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\export\element as export_element;
use mod_customcert\export\template_appendix_manager_interface;
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_layout;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_repository;
use stdClass;

/**
 * Tests proving that structured-payload classification is based on the current
 * persistence contract, not plugin identity.
 */
final class issue_999_structured_third_party_payload_test extends advanced_testcase {
    /**
     * Build a minimal element DB-shaped record.
     *
     * @param string $type
     * @param string|null $data
     * @return stdClass
     */
    private function make_record(string $type, ?string $data): stdClass {
        return (object) [
            'id' => 1,
            'pageid' => 2,
            'name' => 'Element under test',
            'data' => $data,
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'element' => $type,
        ];
    }

    /**
     * Create a real page to import/persist elements against.
     *
     * @return int
     */
    private function create_page(): int {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);
        $trepo = new template_repository();
        $prepo = new page_repository();
        $templateid = $trepo->create((object) ['name' => 'T999', 'contextid' => $context->id]);
        return $prepo->create((object) [
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);
    }

    /**
     * A migrated third-party element's wrapper-shaped JSON must stay JSON.
     *
     * @covers \mod_customcert\element::get_data
     */
    public function test_migrated_third_party_structured_element_returns_json(): void {
        $data = json_encode([
            'value' => 'structured-third-party-value',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);
        $instance = new \customcertelement_structuredv2\element($this->make_record('structuredv2', $data));

        $this->assertInstanceOf(persistable_element_interface::class, $instance);
        $this->assertSame('structuredv2', $instance->get_type());

        $raw = $instance->get_data();

        $this->assertIsString($raw);
        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded);
        $this->assertSame('structured-third-party-value', $decoded['value']);
        $this->assertSame('Helvetica', $decoded['font']);
        $this->assertSame(12, $decoded['fontsize']);
        $this->assertSame('#000000', $decoded['colour']);
        $this->assertSame(50, $decoded['width']);
    }

    /**
     * Plugin-specific keys (not wrapper-shaped at all) also stay JSON.
     *
     * @covers \mod_customcert\element::get_data
     */
    public function test_migrated_third_party_element_with_custom_keys_returns_json(): void {
        $data = json_encode(['first' => 'A', 'second' => 'B']);
        $instance = new \customcertelement_structuredv2\element($this->make_record('structuredv2', $data));

        $raw = $instance->get_data();
        $this->assertIsString($raw);
        $this->assertSame(['first' => 'A', 'second' => 'B'], json_decode($raw, true));
    }

    /**
     * Bundled elements keep their full JSON payload, proving the distinction is the
     * persistence contract, not a bundled-name allowlist.
     *
     * @covers \mod_customcert\element::get_data
     */
    public function test_bundled_element_with_wrapper_shaped_payload_returns_json(): void {
        $data = json_encode([
            'value' => 'bundled-value',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);
        $instance = new \customcertelement_text\element($this->make_record('text', $data));

        $this->assertInstanceOf(persistable_element_interface::class, $instance);
        $raw = $instance->get_data();
        $this->assertIsString($raw);
        $this->assertSame(json_decode($data, true), json_decode($raw, true));
    }

    /**
     * Genuine legacy scalar consumers must keep unwrapping.
     *
     * @covers \mod_customcert\element::get_data
     * @covers \mod_customcert\element::get_raw_data
     */
    public function test_genuine_legacy_scalar_consumer_still_unwraps(): void {
        $data = json_encode([
            'value' => 'legacy-value',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);
        $instance = new \customcertelement_legacy968\element($this->make_record('legacy968', $data));

        $this->assertNotInstanceOf(persistable_element_interface::class, $instance);
        $this->assertSame('legacy-value', $instance->get_data());
        // The raw persistence representation must remain the full JSON object.
        $this->assertSame(json_decode($data, true), json_decode($instance->get_raw_data(), true));
    }

    /**
     * A non-persistable third-party element unwraps.
     *
     * @covers \mod_customcert\element::get_data
     * @covers \mod_customcert\element::get_raw_data
     */
    public function test_non_persistable_element_still_unwraps(): void {
        $registry = new element_registry();
        $registry->register('nativev2nopersist', \customcertelement_nativev2nopersist\element::class);
        $factory = new element_factory($registry);

        $data = json_encode([
            'value' => 'native-v2-value',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);
        $record = $this->make_record('nativev2nopersist', $data);
        $instance = $factory->create('nativev2nopersist', $record);

        $this->assertInstanceOf(\customcertelement_nativev2nopersist\element::class, $instance);
        $this->assertNotInstanceOf(persistable_element_interface::class, $instance);

        $this->assertSame('native-v2-value', $instance->get_data());
        // The raw persistence representation must remain the full JSON object.
        $this->assertSame(json_decode($data, true), json_decode($instance->get_raw_data(), true));
    }

    /**
     * A persistable third-party element's imported payload must stay structured.
     *
     * @covers \mod_customcert\element::get_data
     * @covers \mod_customcert\export\element::import
     */
    public function test_imported_structured_payload_is_not_unwrapped_for_persistable_element(): void {
        global $DB;

        $this->resetAfterTest(true);
        $pageid = $this->create_page();

        $clock = $this->createMock(clock::class);
        $clock->method('time')->willReturn(1000000);
        $logger = $this->createMock(template_import_logger_interface::class);
        $filemng = $this->createMock(template_appendix_manager_interface::class);
        $importer = new export_element($clock, $logger, $filemng);

        $importer->import($pageid, [
            'name' => 'Imported',
            'element' => 'structuredv2',
            'data' => [
                'value' => ['value' => 'imported-persistable-value'],
                'font' => ['value' => 'Helvetica'],
                'fontsize' => ['value' => 12],
                'colour' => ['value' => '#000000'],
                'width' => ['value' => 50],
            ],
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
        ]);

        $registry = new element_registry();
        $registry->register('structuredv2', \customcertelement_structuredv2\element::class);
        $factory = new element_factory($registry);

        $dbrecord = $DB->get_record('customcert_elements', ['pageid' => $pageid], '*', MUST_EXIST);
        $instance = $factory->create('structuredv2', $dbrecord);

        $raw = $instance->get_data();
        $this->assertIsString($raw);
        $this->assertSame('imported-persistable-value', json_decode($raw, true)['value']);
    }

    /**
     * Documents a known limitation on this branch (see PR description): a non-persistable
     * element's imported payload unwraps if the exporter names a field 'value'.
     *
     * @covers \mod_customcert\element::get_data
     * @covers \mod_customcert\export\element::import
     */
    public function test_known_limitation_non_persistable_element_import_collision_unwraps(): void {
        global $DB;

        $this->resetAfterTest(true);
        $pageid = $this->create_page();

        $clock = $this->createMock(clock::class);
        $clock->method('time')->willReturn(1000000);
        $logger = $this->createMock(template_import_logger_interface::class);
        $filemng = $this->createMock(template_appendix_manager_interface::class);
        $importer = new export_element($clock, $logger, $filemng);

        $importer->import($pageid, [
            'name' => 'Imported',
            'element' => 'nativev2nopersist',
            'data' => [
                'value' => ['value' => 'imported-value'],
                'font' => ['value' => 'Helvetica'],
                'fontsize' => ['value' => 12],
                'colour' => ['value' => '#000000'],
                'width' => ['value' => 50],
            ],
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
        ]);

        $registry = new element_registry();
        $registry->register('nativev2nopersist', \customcertelement_nativev2nopersist\element::class);
        $factory = new element_factory($registry);

        $dbrecord = $DB->get_record('customcert_elements', ['pageid' => $pageid], '*', MUST_EXIST);
        $instance = $factory->create('nativev2nopersist', $dbrecord);

        $this->assertSame('imported-value', $instance->get_data());
    }

    /**
     * Full pipeline: create()/reload round trip through element_repository keeps JSON intact.
     *
     * @covers \mod_customcert\service\element_repository::create
     */
    public function test_migrated_third_party_element_survives_persistence_round_trip(): void {
        global $DB;

        $this->resetAfterTest(true);

        $registry = new element_registry();
        $registry->register('structuredv2', \customcertelement_structuredv2\element::class);
        $factory = new element_factory($registry);
        $repo = new element_repository($factory);
        $pageid = $this->create_page();

        $data = json_encode([
            'value' => 'round-trip-value',
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 50,
        ]);
        $record = $this->make_record('structuredv2', $data);
        $record->pageid = $pageid;
        $instance = $factory->create('structuredv2', $record);

        $newid = $repo->create($instance, new element_layout(5, 6, 0, 'L'));

        $dbrecord = $DB->get_record('customcert_elements', ['id' => $newid], '*', MUST_EXIST);
        $reloaded = $factory->create_from_legacy_record($dbrecord);

        $raw = $reloaded->get_data();
        $this->assertIsString($raw);
        $decoded = json_decode($raw, true);
        $this->assertSame('round-trip-value', $decoded['value']);
        $this->assertSame('Helvetica', $decoded['font']);
    }
}
