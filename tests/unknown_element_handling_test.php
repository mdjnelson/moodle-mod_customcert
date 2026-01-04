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

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\element\element_bootstrap;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;

/**
 * Ensure unknown element types do not break loading and are skipped with a developer warning.
 *
 * @category   test
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\service\element_repository::load_by_page_id
 */
final class unknown_element_handling_test extends advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_unknown_type_is_skipped_with_debugging(): void {
        global $DB;

        // Minimal template + page.
        $template = (object) [
            'name' => 'Unknown type template',
            'contextid' => \context_system::instance()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $template->id = (int)$DB->insert_record('customcert_templates', $template, true);

        $page = (object) [
            'templateid' => $template->id,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $page->id = (int)$DB->insert_record('customcert_pages', $page, true);

        // Insert an element with an unknown type.
        $element = (object) [
            'pageid' => $page->id,
            'element' => 'idontexist',
            'name' => 'Bad',
            'posx' => 0,
            'posy' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'data' => null,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $element->id = (int)$DB->insert_record('customcert_elements', $element, true);

        $registry = new element_registry();
        element_bootstrap::register_defaults($registry);
        $repository = new element_repository(new element_factory($registry));

        // Expect a developer debugging message once, but no exception.
        $this->assertDebuggingNotCalled();
        $loaded = $repository->load_by_page_id($page->id);
        $this->assertIsArray($loaded);
        $this->assertCount(0, $loaded, 'Unknown element should be skipped');
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);
    }
}
