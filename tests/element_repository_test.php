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
 * Unit tests for the element repository.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use customcertelement_text\element as text_element;

/**
 * Unit tests for the element repository.
 */
final class element_repository_test extends advanced_testcase {
    /**
     * Test that elements are loaded in sequence order.
     *
     * @covers \mod_customcert\service\element_repository::load_by_page_id
     */
    public function test_load_by_page_id_ordering(): void {
        global $DB;

        $this->resetAfterTest();

        $registry = new element_registry();
        $registry->register('text', text_element::class);
        $factory = new element_factory($registry);
        $repository = new element_repository($factory);

        $pageid = 100;

        // Insert elements out of order.
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'text',
            'name' => 'Second',
            'sequence' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'text',
            'name' => 'First',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'text',
            'name' => 'Third',
            'sequence' => 3,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $elements = $repository->load_by_page_id($pageid);

        $this->assertCount(3, $elements);
        $this->assertEquals('First', $elements[0]->get_name());
        $this->assertEquals('Second', $elements[1]->get_name());
        $this->assertEquals('Third', $elements[2]->get_name());
    }

    /**
     * Test saving an element.
     *
     * @covers \mod_customcert\service\element_repository::save
     */
    public function test_save(): void {
        global $DB;

        $this->resetAfterTest();

        $registry = new element_registry();
        $registry->register('text', text_element::class);
        $factory = new element_factory($registry);
        $repository = new element_repository($factory);

        // Create a real template and page so that repository::save() can resolve
        // the event context via page->template (MUST_EXIST lookups).
        $template = template::create('Test name', \context_system::instance()->id);
        $pageid = $template->add_page();
        $id = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'element' => 'text',
            'name' => 'Original Name',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'data' => 'Original Data',
        ]);

        $elements = $repository->load_by_page_id($pageid);
        $element = $elements[0];

        // Modify the element by updating the DB record and re-instantiating via the factory.

        // Let's create a record that represents the updated state.
        $updatedrecord = $DB->get_record('customcert_elements', ['id' => $id]);
        $updatedrecord->name = 'Updated Name';
        $updatedrecord->data = 'Updated Data';

        $updatedelement = $factory->create('text', $updatedrecord);

        $repository->save($updatedelement);

        $savedrecord = $DB->get_record('customcert_elements', ['id' => $id]);
        $this->assertEquals('Updated Name', $savedrecord->name);
        // Note: text element save_unique_data might format the data.
        $this->assertStringContainsString('Updated Data', $savedrecord->data);
    }

    /**
     * Test copying a page.
     *
     * @covers \mod_customcert\service\element_repository::copy_page
     */
    public function test_copy_page(): void {
        global $DB;

        $this->resetAfterTest();

        $registry = new element_registry();
        $registry->register('text', text_element::class);
        $factory = new element_factory($registry);
        $repository = new element_repository($factory);

        $frompageid = 100;
        $topageid = 200;

        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $frompageid,
            'element' => 'text',
            'name' => 'Element 1',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $frompageid,
            'element' => 'text',
            'name' => 'Element 2',
            'sequence' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $count = $repository->copy_page($frompageid, $topageid);

        $this->assertEquals(2, $count);
        $newelements = $DB->get_records('customcert_elements', ['pageid' => $topageid], 'sequence ASC');
        $this->assertCount(2, $newelements);
        $newelements = array_values($newelements);
        $this->assertEquals('Element 1', $newelements[0]->name);
        $this->assertEquals('Element 2', $newelements[1]->name);
        $this->assertEquals(1, $newelements[0]->sequence);
        $this->assertEquals(2, $newelements[1]->sequence);
    }
}
