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
        // Stub.
    }

    /**
     * Copy all elements from one page to another, preserving sequence.
     *
     * @param int $frompageid
     * @param int $topageid
     * @return int Number of elements copied
     */
    public function copy_page(int $frompageid, int $topageid): int {
        return 0;
    }
}
