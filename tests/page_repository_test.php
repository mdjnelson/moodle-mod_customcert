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
 * Unit tests for the page repository.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\service\page_repository
 */

namespace mod_customcert;

use mod_customcert\service\page_repository;
use mod_customcert\service\template_repository;
use advanced_testcase;
use context_course;

/**
 * Tests for page_repository behaviour (ordering, resequencing, bulk create).
 */
final class page_repository_test extends advanced_testcase {
    /**
     * Repository under test.
     *
     * @var \mod_customcert\service\page_repository
     */
    private page_repository $repo;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->repo = new page_repository();
    }

    /**
     * Ensures default listing order and append behaviour when sequence is omitted.
     *
     * @covers ::create
     * @covers ::list_by_template
     */
    public function test_list_default_ordering_and_append_sequence(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        // Create a template to attach pages to, using the template repository directly.
        $trepo = new template_repository();
        $templateid = $trepo->create((object)[
            'name' => 'T',
            'contextid' => $context->id,
        ]);

        // Create three pages, two with explicit sequence and one appended.
        $p1 = $this->repo->create((object)[
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 2,
        ]);
        $p2 = $this->repo->create((object)[
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            // No sequence -> appended.
        ]);
        $p3 = $this->repo->create((object)[
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);

        $pages = array_values($this->repo->list_by_template($templateid));
        // Default ordering sequence ASC, id ASC, so sequences should be [1,2,3].
        $seqs = array_map(static fn($r) => (int)$r->sequence, $pages);
        $this->assertSame([1, 2, 3], $seqs);
    }

    /**
     * Ensures resequence() compacts sequences to a contiguous 1..N range.
     *
     * @covers ::resequence
     */
    public function test_resequence_compacts_gaps(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        $trepo = new template_repository();
        $templateid = $trepo->create((object)[
            'name' => 'T2',
            'contextid' => $context->id,
        ]);

        // Create out-of-order/gapped sequences.
        $this->repo->create((object)[
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 5,
        ]);
        $this->repo->create((object)[
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 2,
        ]);
        $this->repo->create((object)[
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            // Append.
        ]);

        // Resequence to 1..N.
        $this->repo->resequence($templateid);
        $seqs = array_map(static fn($r) => (int)$r->sequence, array_values($this->repo->list_by_template($templateid)));
        $this->assertSame([1, 2, 3], $seqs);
    }

    /**
     * Ensures bulk_create() respects provided sequences and template boundaries.
     *
     * @covers ::bulk_create
     */
    public function test_bulk_create_respects_template_and_sequences(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        $trepo = new template_repository();
        $templateid = $trepo->create((object)[
            'name' => 'T3',
            'contextid' => $context->id,
        ]);

        $this->repo->bulk_create($templateid, [
            (object)[
                'width' => 800,
                'height' => 600,
                'leftmargin' => 0,
                'rightmargin' => 0,
                'sequence' => 10,
            ],
            (object)[
                'width' => 800,
                'height' => 600,
                'leftmargin' => 0,
                'rightmargin' => 0,
                // Append.
            ],
        ]);

        $pages = array_values($this->repo->list_by_template($templateid));
        $this->assertCount(2, $pages);
        $this->assertSame(10, (int)$pages[0]->sequence);
        $this->assertSame(11, (int)$pages[1]->sequence);

        // Compact afterwards.
        $this->repo->resequence($templateid);
        $seqs = array_map(static fn($r) => (int)$r->sequence, array_values($this->repo->list_by_template($templateid)));
        $this->assertSame([1, 2], $seqs);
    }

    /**
     * Ensures get_for_template_or_fail returns the page when it belongs to the template.
     *
     * @covers ::get_for_template_or_fail
     */
    public function test_get_for_template_or_fail_returns_page_for_correct_template(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);
        $trepo = new template_repository();
        $templateid = $trepo->create((object)[
            'name' => 'T',
            'contextid' => $context->id,
        ]);
        $pageid = $this->repo->create((object)[
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);
        $record = $this->repo->get_for_template_or_fail($templateid, $pageid);
        $this->assertSame($pageid, (int) $record->id);
    }

    /**
     * Ensures get_for_template_or_fail throws when the page belongs to a different template.
     *
     * @covers ::get_for_template_or_fail
     */
    public function test_get_for_template_or_fail_throws_for_mismatched_template(): void {
        $generator = $this->getDataGenerator();

        // Two separate courses model the cross-course tamper scenario.
        $coursea = $generator->create_course();
        $contexta = context_course::instance($coursea->id);
        $courseb = $generator->create_course();
        $contextb = context_course::instance($courseb->id);

        $trepo = new template_repository();
        $templateid = $trepo->create((object)[
            'name' => 'T',
            'contextid' => $contexta->id,
        ]);
        $othertemplateid = $trepo->create((object)[
            'name' => 'Other',
            'contextid' => $contextb->id,
        ]);
        $pageid = $this->repo->create((object)[
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);

        $this->expectException(\dml_missing_record_exception::class);
        $this->repo->get_for_template_or_fail($othertemplateid, $pageid);
    }

    /**
     * Ensures delete() removes the page record from the database.
     *
     * @covers ::delete
     */
    public function test_delete_removes_page_record(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);
        $trepo = new template_repository();
        $templateid = $trepo->create((object)[
            'name' => 'T',
            'contextid' => $context->id,
        ]);
        $pageid = $this->repo->create((object)[
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);
        $this->assertTrue($DB->record_exists('customcert_pages', ['id' => $pageid]));
        $this->repo->delete($pageid);
        $this->assertFalse($DB->record_exists('customcert_pages', ['id' => $pageid]));
    }
}
