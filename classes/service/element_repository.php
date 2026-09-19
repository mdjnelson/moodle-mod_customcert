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
 * Repository for loading, saving and copying element records.
 *
 * Coordinates database access for elements and delegates instance creation to
 * {@see element_factory}. It also provides helper methods to copy elements
 * between pages/templates while preserving ordering.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\element;
use mod_customcert\element\element_interface;
use mod_customcert\element\copyable_element_interface;
use mod_customcert\element\raw_data_element_interface;
use mod_customcert\element\unknown_element;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element_helper;
use mod_customcert\service\element_layout;
use mod_customcert\local\ordering;
use mod_customcert\event\element_created;
use mod_customcert\service\element_factory;
use mod_customcert\event\element_deleted;
use mod_customcert\event\element_updated;
use ReflectionMethod;
use stdClass;

/**
 * DB-backed repository for element instances.
 */
final class element_repository {
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
     * List raw element records for a given page with standard ordering.
     *
     * @param int $pageid
     * @param ordering|null $order Defaults to sequence ASC, id ASC
     * @return array<int, stdClass>
     */
    public function list_by_page(int $pageid, ?ordering $order = null): array {
        global $DB;

        $order = $order ?? new ordering([
            'sequence' => 'ASC',
            'id' => 'ASC',
        ]);

        return $DB->get_records('customcert_elements', ['pageid' => $pageid], $order->to_sql()) ?: [];
    }

    /**
     * Return the templateid that owns the given element, or null if the chain is broken.
     *
     * Traverses customcert_elements → customcert_pages to find the owning template.
     *
     * @param int $elementid
     * @return int|null
     */
    public function get_template_id_for_element(int $elementid): ?int {
        global $DB;

        $sql = 'SELECT p.templateid
                  FROM {customcert_elements} e
                  JOIN {customcert_pages} p ON p.id = e.pageid
                 WHERE e.id = :elementid';

        $templateid = $DB->get_field_sql($sql, ['elementid' => $elementid]);
        return $templateid !== false ? (int)$templateid : null;
    }

    /**
     * Return the template contextid that owns the given element, or null if the chain is broken.
     *
     * Traverses customcert_elements → customcert_pages → customcert_templates.
     *
     * @param int $elementid
     * @return int|null
     */
    public function get_template_context_id_for_element(int $elementid): ?int {
        global $DB;

        $sql = 'SELECT t.contextid
                  FROM {customcert_elements} e
                  JOIN {customcert_pages} p ON p.id = e.pageid
                  JOIN {customcert_templates} t ON t.id = p.templateid
                 WHERE e.id = :elementid';

        $contextid = $DB->get_field_sql($sql, ['elementid' => $elementid]);
        return $contextid !== false ? (int)$contextid : null;
    }

    /**
     * Load a single element record by id or throw if missing.
     *
     * @param int $id
     * @return stdClass
     * @throws \dml_missing_record_exception When the record doesn't exist.
     * @throws \dml_exception For database errors.
     */
    public function get_by_id_or_fail(int $id): stdClass {
        global $DB;

        return $DB->get_record('customcert_elements', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Load a single element record by id, verifying it belongs to the given template.
     *
     * Throws if the element does not exist or belongs to a different template.
     *
     * @param int $templateid
     * @param int $elementid
     * @return stdClass
     * @throws \dml_missing_record_exception When the element does not exist or belongs to a different template.
     * @throws \dml_exception For database errors.
     */
    public function get_for_template_or_fail(int $templateid, int $elementid): stdClass {
        global $DB;

        $sql = 'SELECT e.*
                  FROM {customcert_elements} e
                  JOIN {customcert_pages} p ON p.id = e.pageid
                 WHERE e.id = :elementid
                   AND p.templateid = :templateid';

        return $DB->get_record_sql($sql, ['elementid' => $elementid, 'templateid' => $templateid], MUST_EXIST);
    }

    /**
     * Load elements for a given page id.
     *
     * @param int $pageid
     * @return element_interface[]
     */
    public function load_by_page_id(int $pageid): array {
        $records = $this->list_by_page($pageid);
        $elements = [];
        $warnedtypes = [];
        foreach ($records as $record) {
            if (empty($record->element)) {
                continue;
            }
            try {
                $elements[] = $this->factory->create($record->element, $record);
            } catch (\Throwable $e) {
                // Skip unknown or broken element types but do not take down rendering.
                $type = (string)$record->element;
                if (!isset($warnedtypes[$type])) {
                    $warnedtypes[$type] = true;
                    if (!defined('BEHAT_SITE_RUNNING')) {
                        debugging("Unknown or invalid element type '{$type}', skipping.", DEBUG_DEVELOPER);
                    }
                }
                // Return an HTML placeholder element for preview/admin. It renders nothing in PDF.
                $elements[] = new unknown_element($record, $type);
                continue;
            }
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
            array_push($result, ...$this->load_by_page_id((int)$page->id));
        }
        return $result;
    }

    /**
     * Persist an element.
     *
     * @param element_interface $element
     * @param element_layout $layout Layout columns for the element.
     * @return void
     */
    public function save(element_interface $element, element_layout $layout): void {
        global $DB;

        $record = new stdClass();
        $record->id = $element->get_id();
        $record->pageid = $element->get_pageid();
        $record->name = $element->get_name();
        $record->posx = $layout->posx;
        $record->posy = $layout->posy;
        // Width is stored inside the JSON data; no DB column write.
        $record->refpoint = $layout->refpoint;
        $record->alignment = $layout->alignment;
        $record->timemodified = time();

        // Persist the raw, untouched storage representation of the data.
        // get_data() applies a legacy compatibility transformation (unwrapping scalar
        // values from a generic migration wrapper) that must never be written back to
        // the database, or migrated metadata (font, colour, width, etc.) would be lost.
        // Only elements implementing raw_data_element_interface expose that representation;
        // fall back to get_data() for elements that do not.
        $record->data = $element instanceof raw_data_element_interface
            ? $element->get_raw_data()
            : $element->get_data();

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
     * Copy an element to a new page.
     *
     * The $strict flag preserves two intentionally different historical behaviours for an
     * element type that cannot be resolved/constructed:
     * - Strict (used by copy_page()): the failure is not swallowed; the factory exception
     *   propagates to the caller, matching the pre-refactor behaviour of copy_page().
     * - Tolerant (used by copy_to_template()): the failure is swallowed, the inserted row is
     *   left in place and null is returned, matching the pre-refactor behaviour of
     *   copy_to_template().
     *
     * @param stdClass $sourceelement The raw element record to copy
     * @param int $topageid The ID of the page to copy it to
     * @param bool $strict Whether an unresolved element type should throw instead of being
     *                      tolerated. See method description for details.
     * @return element_interface|null The new element instance, or null if copy failed
     */
    public function copy_element(stdClass $sourceelement, int $topageid, bool $strict = false): ?element_interface {
        global $DB;

        $now = time();
        $newrecord = clone($sourceelement);
        unset($newrecord->id);
        $newrecord->pageid = $topageid;
        $newrecord->timecreated = $now;
        $newrecord->timemodified = $now;

        $newid = $DB->insert_record('customcert_elements', $newrecord);
        $newrecord->id = $newid;

        if ($strict) {
            // Let an unresolved/broken element type throw, matching copy_page()'s historical
            // behaviour. Any transaction started by the caller is responsible for rollback.
            $instance = $this->factory->create((string)$newrecord->element, $newrecord);
        } else {
            // Tolerate an unresolved element type, matching copy_to_template()'s historical
            // behaviour: leave the row as-is and report failure without raising an error.
            $instance = $this->factory->create_from_legacy_record($newrecord);
            if (!$instance) {
                return null;
            }
        }

        $inner = $instance;
        if ($instance instanceof legacy_element_adapter) {
            $inner = $instance->get_inner();
        }

        // Give the element a chance to handle any unique data copying.
        if ($inner instanceof copyable_element_interface) {
            if (!$inner->copy_from($sourceelement)) {
                $this->delete($instance);
                return null;
            }
        } else if (self::has_legacy_copy_override($inner)) {
            // Back-compat: only call the legacy hook when the concrete class actually overrides it.
            debugging(
                'copy_element() is deprecated since Moodle 5.2. '
                . 'Implement mod_customcert\\element\\copyable_element_interface::copy_from() instead.',
                DEBUG_DEVELOPER
            );
            $copyresult = $inner->copy_element($sourceelement);
            if ($copyresult === false) {
                $this->delete($instance);
                return null;
            }
        }

        return $instance;
    }

    /**
     * Copy all elements from one page to another, preserving sequence.
     *
     * @param int $frompageid
     * @param int $topageid
     * @param bool $transactional Whether to wrap in a transaction
     * @return int Number of elements copied
     */
    public function copy_page(int $frompageid, int $topageid, bool $transactional = true): int {
        global $DB;

        $count = 0;
        $elements = $DB->get_records('customcert_elements', ['pageid' => $frompageid], 'sequence ASC');
        if (empty($elements)) {
            return 0;
        }

        $transaction = null;
        if ($transactional) {
            $transaction = $DB->start_delegated_transaction();
        }

        foreach ($elements as $e) {
            if ($this->copy_element($e, $topageid, true)) {
                $count++;
            }
        }

        if ($transaction) {
            $transaction->allow_commit();
        }

        return $count;
    }

    /**
     * Update the position of an element and fire the updated event.
     *
     * @param int $id Element id.
     * @param int $posx New X position.
     * @param int $posy New Y position.
     * @param int $contextid Context id.
     * @return void
     */
    public function update_position(int $id, int $posx, int $posy, int $contextid): void {
        global $DB;

        $record = new stdClass();
        $record->id = $id;
        $record->posx = $posx;
        $record->posy = $posy;
        $record->timemodified = time();
        $DB->update_record('customcert_elements', $record);

        element_updated::create(['contextid' => $contextid, 'objectid' => $id])->trigger();
    }

    /**
     * Update the name of an element and fire the updated event.
     *
     * @param int $id Element id.
     * @param string $name New name.
     * @param int $contextid Context id.
     * @return void
     */
    public function update_name(int $id, string $name, int $contextid): void {
        global $DB;

        $record = new stdClass();
        $record->id = $id;
        $record->name = $name;
        $record->timemodified = time();
        $DB->update_record('customcert_elements', $record);

        element_updated::create(['contextid' => $contextid, 'objectid' => $id])->trigger();
    }

    /**
     * Delete an element record by id without firing the deleted event.
     *
     * Use this only when the element type cannot be resolved and event firing is not possible.
     *
     * @param int $id
     * @return bool True on success.
     */
    public function delete_by_id(int $id): bool {
        global $DB;
        return $DB->delete_records('customcert_elements', ['id' => $id]);
    }

    /**
     * Delete an element record and fire the deleted event.
     *
     * @param element_interface $element
     * @return bool True on success, false otherwise.
     */
    public function delete(element_interface $element): bool {
        global $DB;

        $result = $DB->delete_records('customcert_elements', ['id' => $element->get_id()]);

        if ($result) {
            element_deleted::create_from_element($element)->trigger();
        }

        return $result;
    }

    /**
     * Create a new element record and fire the created event.
     *
     * @param element_interface $element
     * @param element_layout $layout Layout columns for the element.
     * @return int Newly created element id
     */
    public function create(element_interface $element, element_layout $layout): int {
        global $DB;

        $record = new stdClass();
        $record->pageid = $element->get_pageid();
        $record->element = $element->get_type();
        $record->name = $element->get_name();
        $record->posx = $layout->posx;
        $record->posy = $layout->posy;
        // Width is stored inside the JSON data; no DB column write.
        $record->refpoint = $layout->refpoint;
        $record->alignment = $layout->alignment;
        // See save() for why get_raw_data() is used instead of get_data() here.
        $record->data = $element instanceof raw_data_element_interface
            ? $element->get_raw_data()
            : $element->get_data();
        $record->sequence = element_helper::get_element_sequence($record->pageid);
        $now = time();
        $record->timecreated = $now;
        $record->timemodified = $now;

        $record->id = (int)$DB->insert_record('customcert_elements', $record, true);

        // Fire created event for this element in the template context.
        $created = $this->factory->create($element->get_type(), $record);
        element_created::create_from_element($created)->trigger();

        return $record->id;
    }

    /**
     * Returns true only when the given element instance has a concrete override of copy_element()
     * that is not merely the no-op base implementation on mod_customcert\element.
     *
     * @param object $element Element instance to inspect.
     * @return bool
     */
    private static function has_legacy_copy_override(object $element): bool {
        if (!method_exists($element, 'copy_element')) {
            return false;
        }
        $ref = new ReflectionMethod($element, 'copy_element');
        return $ref->getDeclaringClass()->getName() !== element::class;
    }
}
