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
 * element_repository (scaffolding only; not wired yet).
 *
 * @package    mod_customcert
 * @copyright  Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\element\element_interface;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element_helper;
use mod_customcert\event\element_created;
use mod_customcert\event\element_updated;
use stdClass;

/**
 * Contract and minimal stub for loading/saving/copying elements.
 */
class element_repository {
    /** @var element_factory */
    private element_factory $factory;

    /**
     * Constructor.
     *
     * @param element_factory $factory
     */
    public function __construct(element_factory $factory) {
        $this->factory = $factory;
    }
    /**
     * Load elements for a given page id.
     *
     * @param int $pageid
     * @return element_interface[]
     */
    public function load_by_page_id(int $pageid): array {
        global $DB;

        $records = $DB->get_records('customcert_elements', ['pageid' => $pageid], 'sequence ASC');
        $elements = [];
        foreach ($records as $record) {
            if (empty($record->element)) {
                continue;
            }
            $elements[] = $this->factory->create($record->element, $record);
        }
        return $elements;
    }

    /**
     * Load elements for a given template id.
     *
     * @param int $templateid
     * @return element_interface[]
     */
    public function load_by_template_id(int $templateid): array {
        global $DB;

        $pages = $DB->get_records('customcert_pages', ['templateid' => $templateid], 'sequence ASC');
        $result = [];
        foreach ($pages as $page) {
            $result = array_merge($result, $this->load_by_page_id((int)$page->id));
        }
        return $result;
    }

    /**
     * Persist an element.
     *
     * @param element_interface $element
     * @return void
     */
    public function save(element_interface $element): void {
        global $DB;

        $record = new stdClass();
        $record->id = $element->get_id();
        $record->pageid = $element->get_pageid();
        $record->name = $element->get_name();
        $record->posx = $element->get_posx();
        $record->posy = $element->get_posy();
        // Width is stored inside the JSON data; no DB column write.
        $record->refpoint = $element->get_refpoint();
        $record->alignment = $element->get_alignment();
        $record->timemodified = time();

        // Persist data exactly as provided by the element implementation.
        // BC: legacy elements are wrapped via legacy_element_adapter, but their
        // get_data() remains compatible with DB storage expectations.
        $record->data = $element->get_data();

        $DB->update_record('customcert_elements', $record);

        // Fire updated event for this element in the template context.
        $page = $DB->get_record('customcert_pages', ['id' => $record->pageid], '*', MUST_EXIST);
        $template = $DB->get_record('customcert_templates', ['id' => $page->templateid], '*', MUST_EXIST);

        $data = [
            'contextid' => (int)$template->contextid,
            'objectid' => $record->id,
        ];
        element_updated::create($data)->trigger();
    }

    /**
     * Copy all elements from one page to another, preserving sequence.
     *
     * @param int $frompageid
     * @param int $topageid
     * @return int Number of elements copied
     */
    public function copy_page(int $frompageid, int $topageid): int {
        global $DB;

        $count = 0;
        $elements = $DB->get_records('customcert_elements', ['pageid' => $frompageid], 'sequence ASC');
        if (empty($elements)) {
            return 0;
        }

        $transaction = $DB->start_delegated_transaction();

        foreach ($elements as $e) {
            $newelement = clone($e);
            unset($newelement->id);
            $newelement->pageid = $topageid;
            $newelement->timecreated = time();
            $newelement->timemodified = time();

            $newid = $DB->insert_record('customcert_elements', $newelement);

            // Give the element a chance to handle any unique data copying.
            $instance = $this->factory->create($e->element, $DB->get_record('customcert_elements', ['id' => $newid]));
            $inner = $instance;
            if ($instance instanceof legacy_element_adapter) {
                $inner = $instance->get_inner();
            }

            // The legacy elements have a copy_element method.
            if (method_exists($inner, 'copy_element')) {
                $inner->copy_element($e);
            }

            $count++;
        }

        $transaction->allow_commit();

        return $count;
    }

    /**
     * Create a new element record and fire the created event.
     *
     * This mirrors the legacy behaviour of element::save_form_elements() for inserts.
     *
     * @param element_interface $element
     * @return int Newly created element id
     */
    public function create(element_interface $element): int {
        global $DB;

        $record = new stdClass();
        $record->pageid = $element->get_pageid();
        $record->element = $element->get_type();
        $record->name = $element->get_name();
        $record->posx = $element->get_posx();
        $record->posy = $element->get_posy();
        // Width is stored inside the JSON data; no DB column write.
        $record->refpoint = $element->get_refpoint();
        $record->alignment = $element->get_alignment();
        $record->data = $element->get_data();
        $record->sequence = element_helper::get_element_sequence($record->pageid);
        $record->timecreated = time();
        $record->timemodified = time();

        $record->id = (int)$DB->insert_record('customcert_elements', $record, true);

        // Fire created event for this element in the template context.
        $page = $DB->get_record('customcert_pages', ['id' => $record->pageid], '*', MUST_EXIST);
        $template = $DB->get_record('customcert_templates', ['id' => $page->templateid], '*', MUST_EXIST);

        $data = [
            'contextid' => (int)$template->contextid,
            'objectid' => $record->id,
        ];
        element_created::create($data)->trigger();

        return $record->id;
    }
}
