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
 * Unit tests for the template repository.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\service\template_repository
 * @covers \mod_customcert\local\ordering
 * @covers \mod_customcert\local\paging
 */

namespace mod_customcert;

use context_course;
use invalid_parameter_exception;
use mod_customcert\local\paging;
use mod_customcert\service\template_repository;

/**
 * Tests for template_repository behaviour (ordering, paging, validation, duplication naming).
 */
final class template_repository_test extends \advanced_testcase {
    /** @var template_repository */
    private template_repository $repo;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->repo = new template_repository();
    }

    /**
     * Test ordering and paging.
     *
     * @covers ::list_by_context
     */
    public function test_list_by_context_orders_and_pages(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        // Create three templates with names and different timemodified to test tie-breakers.
        $now = time();
        $t1 = (object)['name' => 'Alpha', 'contextid' => $context->id, 'timecreated' => $now - 30, 'timemodified' => $now - 30];
        $t2 = (object)['name' => 'Bravo', 'contextid' => $context->id, 'timecreated' => $now - 20, 'timemodified' => $now - 10];
        $t3 = (object)['name' => 'Alpha', 'contextid' => $context->id, 'timecreated' => $now - 10, 'timemodified' => $now - 20];

        $id1 = $this->repo->create($t1);
        $id2 = $this->repo->create($t2);
        // Small sleep to ensure id ordering remains deterministic if needed.
        $id3 = $this->repo->create($t3);

        // Default ordering: name ASC, timemodified DESC, id ASC.
        $list = $this->repo->list_by_context($context->id);
        $this->assertCount(3, $list);
        $orderedids = array_map(static fn($r) => (int)$r->id, array_values($list));

        // Names: Alpha (two items, pick newer timemodified first), then Bravo.
        // t3 (Alpha, timemodified now-20, newer than t1 now-30) should come before t1, then t2 (Bravo).
        $this->assertSame([$id3, $id1, $id2], $orderedids);

        // Paging: limit 1, offset 1 should return only the middle one.
        $paged = $this->repo->list_by_context($context->id, null, new paging(1, 1));
        $this->assertCount(1, $paged);
        $this->assertSame($id1, (int)reset($paged)->id);

        // Limit=0 means no limit: return all.
        $nolimit = $this->repo->list_by_context($context->id, null, new paging(0, 0));
        $this->assertCount(3, $nolimit);
    }

    /**
     * Test duplicate validation and naming.
     *
     * @covers ::create
     * @covers ::update
     */
    public function test_create_update_duplicate_validation_and_naming(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        // Create with valid values.
        $id = $this->repo->create((object)['name' => 'Base', 'contextid' => $context->id]);
        $this->assertGreaterThan(0, $id);

        // Update with trimmed name succeeds; empty should throw.
        $this->repo->update($id, (object)['name' => '  Renamed  ']);
        $record = $this->repo->get_by_id_or_fail($id);
        $this->assertSame('Renamed', $record->name);

        $this->expectException(invalid_parameter_exception::class);
        $this->repo->update($id, (object)['name' => '   ']);
    }

    /**
     * Test duplicate naming policies.
     *
     * @covers ::duplicate
     */
    public function test_duplicate_naming_policy_and_empty_source_name(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        // Case 1: duplicate with explicit name (trim + validate).
        $id = $this->repo->create((object)['name' => 'Alpha', 'contextid' => $context->id]);
        $copyid = $this->repo->duplicate($id, '  Custom Copy  ');
        $copy = $this->repo->get_by_id_or_fail($copyid);
        $this->assertSame('Custom Copy', $copy->name);

        // Explicit empty name should throw.
        $this->expectException(invalid_parameter_exception::class);
        $this->repo->duplicate($id, '   ');

        // Case 2: duplicate without new name -> "{source} (copy)".
        $copyid2 = $this->repo->duplicate($id, null);
        $copy2 = $this->repo->get_by_id_or_fail($copyid2);
        $this->assertSame('Alpha (copy)', $copy2->name);

        // Case 3: legacy/empty source name -> fallback to "Template (copy)".
        // Insert a legacy/bad row directly to simulate older data.
        $legacy = (object)[
            'name' => null,
            'contextid' => $context->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $legacyid = (int)$DB->insert_record('customcert_templates', $legacy, true);
        $copyid3 = $this->repo->duplicate($legacyid, null);
        $copy3 = $this->repo->get_by_id_or_fail($copyid3);
        $this->assertSame('Template (copy)', $copy3->name);
    }
}
