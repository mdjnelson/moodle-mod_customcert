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

/**
 * Contract and minimal stub for loading/saving/copying elements.
 */
class element_repository {
    /**
     * Load elements for a given page id.
     *
     * @param int $pageid
     * @return element_interface[]
     */
    public function load_by_page_id(int $pageid): array {
        return [];
    }

    /**
     * Load elements for a given template id.
     *
     * @param int $templateid
     * @return element_interface[]
     */
    public function load_by_template_id(int $templateid): array {
        return [];
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
