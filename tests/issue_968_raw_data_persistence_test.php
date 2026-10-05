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
 * Regression tests for issue #968: preserve JSON object storage when persisting element
 * data, instead of persisting the legacy scalar compatibility view returned by get_data().
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\service\element_repository
 */

declare(strict_types=1);

namespace mod_customcert;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/native_v2_control_element.php');
require_once(__DIR__ . '/fixtures/dummy_element_interface_element.php');
require_once(__DIR__ . '/fixtures/dummy_element_interface_with_id_element.php');

use advanced_testcase;
use context_course;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_layout;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_repository;
use mod_customcert\tests\fixtures\dummy_element_interface_element;
use mod_customcert\tests\fixtures\dummy_element_interface_with_id_element;
use mod_customcert\tests\fixtures\native_v2_control_element;

/**
 * Tests proving that element_repository::save()/create() persist the raw JSON storage
 * representation of an element, not the legacy scalar returned by get_data().
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
     * (i) Direct v2 BC control: a fixture implementing only the PRE-#968
     * element_interface contract (not the new optional raw_data_element_interface) must
     * still persist correctly via element_repository::save() through the get_data()
     * fallback path.
     *
     * Note: element_repository::create() additionally needs the factory/registry to
     * reconstruct the element for the creation event (element->get_type() lookup), which
     * requires the class to implement renderable_element_interface directly — a
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
