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
 * Unit tests for element repository events (create/update).
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\element\element_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use customcertelement_text\element as text_element;

/**
 * Tests for events emitted by the new repository code path.
 */
final class element_repository_events_test extends advanced_testcase {
    /**
     * Test set up.
     *
     * @return void
     */
    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Simple test element implementing the v2 element_interface for create().
     *
     * @param int $pageid the page id
     * @param string $type the element type
     * @return element_interface
     */
    private function make_dummy_element(int $pageid, string $type = 'text'): element_interface {
        return new class ($pageid, $type) implements element_interface {
            /** @var int Page ID */
            private int $pageid;

            /** @var string Element type */
            private string $type;

            /**
             * Constructor.
             *
             * @param int $pageid The page id.
             * @param string $type The element type.
             */
            public function __construct(int $pageid, string $type) {
                $this->pageid = $pageid;
                $this->type = $type;
            }

            /**
             * Get id.
             * @return int
             */
            public function get_id(): int {
                return 0;
            }

            /**
             * Get page id.
             * @return int
             */
            public function get_pageid(): int {
                return $this->pageid;
            }

            /**
             * Get name.
             * @return string
             */
            public function get_name(): string {
                return 'Dummy name';
            }

            /**
             * Get data.
             * @return mixed
             */
            public function get_data(): mixed {
                return 'Dummy data';
            }

            /**
             * Get font.
             * @return string|null
             */
            public function get_font(): ?string {
                return null;
            }

            /**
             * Get fontsize.
             * @return int|null
             */
            public function get_fontsize(): ?int {
                return null;
            }

            /**
             * Get colour.
             * @return string|null
             */
            public function get_colour(): ?string {
                return null;
            }

            /**
             * Get X pos.
             * @return int|null
             */
            public function get_posx(): ?int {
                return null;
            }

            /**
             * Get Y pos.
             * @return int|null
             */
            public function get_posy(): ?int {
                return null;
            }

            /**
             * Get width.
             * @return int|null
             */
            public function get_width(): ?int {
                return null;
            }

            /**
             * Get ref point.
             * @return int|null
             */
            public function get_refpoint(): ?int {
                return null;
            }

            /**
             * Get alignment.
             * @return string
             */
            public function get_alignment(): string {
                return 'L';
            }

            /**
             * Get type.
             * @return string
             */
            public function get_type(): string {
                return $this->type;
            }
        };
    }

    /**
     * Ensure element_created event is fired for repository create.
     *
     * @covers \mod_customcert\service\element_repository::create
     */
    public function test_create_fires_element_created_event(): void {
        // Create a template and a page to ensure a valid context for events.
        $template = template::create('Repo events', \context_system::instance()->id);
        $pageid = $template->add_page();

        $registry = new element_registry();
        $registry->register('text', text_element::class);
        $factory = new element_factory($registry);
        $repository = new element_repository($factory);

        $element = $this->make_dummy_element($pageid, 'text');

        $sink = $this->redirectEvents();
        $newid = $repository->create($element);
        $events = $sink->get_events();

        $this->assertGreaterThan(0, $newid);
        $this->assertCount(1, $events);

        $event = reset($events);
        $this->assertInstanceOf('\\mod_customcert\\event\\element_created', $event);
        $this->assertEquals($newid, $event->objectid);
        $this->assertEquals(\context_system::instance()->id, $event->contextid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Ensure element_updated event is fired for repository save.
     *
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_save_fires_element_updated_event(): void {
        global $DB;

        $template = template::create('Repo events', \context_system::instance()->id);
        $pageid = $template->add_page();

        $registry = new element_registry();
        $registry->register('text', text_element::class);
        $factory = new element_factory($registry);
        $repository = new element_repository($factory);

        // Seed an element record.
        $id = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'text',
            'name' => 'Before',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'data' => 'Seed data',
        ]);

        // Load via repository so we have an element_interface instance.
        $elements = $repository->load_by_page_id($pageid);
        $this->assertCount(1, $elements);
        $element = $elements[0];

        $sink = $this->redirectEvents();
        $repository->save($element);
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\\mod_customcert\\event\\element_updated', $event);
        $this->assertEquals($id, $event->objectid);
        $this->assertEquals(\context_system::instance()->id, $event->contextid);
        $this->assertDebuggingNotCalled();
    }
}
