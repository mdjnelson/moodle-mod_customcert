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
use context_system;
use mod_customcert\element\element_bootstrap;
use mod_customcert\element\unknown_element;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\tests\fixtures\fake_element_fixture;
use mod_customcert\tests\fixtures\legacy_and_native_plugin_provider;
use PHPUnit\Framework\Attributes\CoversMethod;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/customcertelement_legacy45/element.php');
require_once(__DIR__ . '/fixtures/fake_element_fixture.php');
require_once(__DIR__ . '/fixtures/legacy_and_native_plugin_provider.php');

/**
 * Rejected legacy elements must load as placeholders without changing stored data.
 * The fixture exercises rejection through plugin discovery.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(\mod_customcert\service\element_repository::class, 'load_by_page_id')]
final class legacy_type_repository_placeholder_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
        if (!class_exists('\\customcertelement_fakeplugin\\element', false)) {
            class_alias(fake_element_fixture::class, '\\customcertelement_fakeplugin\\element');
        }
    }

    public function test_rejected_legacy_type_loads_as_placeholder_preserving_payload(): void {
        global $DB;

        $this->setAdminUser();

        $template = (object) [
            'name' => 'Legacy type placeholder template',
            'contextid' => context_system::instance()->id,
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

        // Historical record for the genuine legacy-only element, with a payload that must
        // survive unchanged once rendered as a placeholder.
        $originalpayload = ['legacyvalue' => 'keepme', 'extra' => 42];
        $element = (object) [
            'pageid' => $page->id,
            'element' => 'legacy45',
            'name' => 'Historical legacy element',
            'posx' => 5,
            'posy' => 10,
            'refpoint' => 0,
            'alignment' => 'L',
            'data' => json_encode($originalpayload),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $element->id = (int)$DB->insert_record('customcert_elements', $element, true);

        // Exercise discovery with both legacy-only and native v2 plugins.
        $registry = new element_registry();
        $provider = new legacy_and_native_plugin_provider();

        $this->resetDebugging();
        element_bootstrap::register_defaults($registry, $provider);
        $this->assertDebuggingCalled(
            "Failed to register customcertelement 'legacy45': Coding error detected, it must be fixed by "
                . "a programmer: Cannot register element type 'legacy45': "
                . "'\\customcertelement_legacy45\\element' must implement "
                . 'mod_customcert\\element\\form_element_interface and '
                . 'mod_customcert\\element\\renderable_element_interface directly (Element System v2).',
            DEBUG_DEVELOPER
        );
        $this->assertFalse($registry->has('legacy45'), 'The legacy type must not be registered.');

        $repository = new element_repository(new element_factory($registry));

        $this->resetDebugging();
        $loaded = $repository->load_by_page_id($page->id);
        $this->assertDebuggingCalled(
            "Unknown or invalid element type 'legacy45', skipping.",
            DEBUG_DEVELOPER
        );

        $this->assertCount(1, $loaded);
        $placeholder = $loaded[0];
        $this->assertInstanceOf(unknown_element::class, $placeholder);

        // The stored payload must neither change nor be lost by the placeholder.
        $this->assertSame($originalpayload, $placeholder->get_payload());

        // The record in the database must remain untouched too.
        $row = $DB->get_record('customcert_elements', ['id' => $element->id], '*', MUST_EXIST);
        $this->assertSame(json_encode($originalpayload), $row->data);

        // Rendering must identify the unknown type without raising errors or touching PDF output.
        $html = $placeholder->render_html();
        $this->assertStringContainsString('legacy45', $html);
    }
}
