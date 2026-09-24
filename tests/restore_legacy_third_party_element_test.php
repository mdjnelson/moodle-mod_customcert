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
 * Restore coverage for an installed, supported genuine Moodle 4.5-era third-party element (#959).
 *
 * customcertelement_legacy45 is not a real installed subplugin, so restore_controller (which
 * instantiates the real task/step classes itself, with no seam to substitute a registry) would
 * always treat it as missing (see restore_missing_third_party_element_test.php). Two test
 * doubles substitute only the base restore_structure_step plumbing that is unrelated to element
 * migration -- date offsetting/id mapping (testable_restore_customcert_element_step) and
 * element factory resolution (minimal_restore_task's injectable factory) -- so the production
 * methods under test, process_customcert_element() and after_restore(), run unmodified.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use context_system;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\restorable_element_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\tests\fixtures\minimal_restore_task;
use mod_customcert\tests\fixtures\testable_restore_customcert_element_step;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/customcertelement_legacy45/element.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/customcert/backup/moodle2/restore_customcert_activity_task.class.php');
require_once(__DIR__ . '/fixtures/minimal_restore_task.php');
require_once(__DIR__ . '/fixtures/testable_restore_customcert_element_step.php');
require_once(__DIR__ . '/legacy_compatibility_diagnostic_test_trait.php');

/**
 * Proves a real restore migrates and dispatches a legacy third-party element correctly.
 *
 * @covers \restore_customcert_activity_structure_step::process_customcert_element
 * @covers \restore_customcert_activity_task::after_restore
 * @covers \mod_customcert\element\legacy_element_adapter::after_restore_from_backup
 * @covers \mod_customcert\service\element_factory::create_from_record
 */
final class restore_legacy_third_party_element_test extends advanced_testcase {
    use \mod_customcert\tests\legacy_compatibility_diagnostic_test_trait;

    /** Registry type key matching the fixture's namespace-derived element type. */
    private const TYPE_LEGACY45 = 'legacy45';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Deduplicated per component for the PHP process lifetime, independent of test order.
        $this->reset_legacy_compatibility_diagnostic_state();
    }

    /**
     * Create a minimal template, page and customcert instance to restore an element into.
     *
     * @return array{0: int, 1: int} [pageid, customcertid]
     */
    private function create_template_page_and_customcert(): array {
        global $DB;

        $template = (object) [
            'name' => 'Legacy45 restore template',
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

        $customcert = (object) [
            'course' => 0,
            'name' => 'Legacy45 restore activity',
            'templateid' => $template->id,
            'intro' => '',
            'introformat' => 0,
            'requiredtime' => 0,
            'emailstudents' => 0,
            'emailteachers' => 0,
            'emailothers' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $customcert->id = (int)$DB->insert_record('customcert', $customcert, true);

        return [$page->id, $customcert->id];
    }

    /**
     * Build a factory that knows only the genuine 4.5-era legacy fixture.
     *
     * @return element_factory
     */
    private function make_factory_with_legacy45(): element_factory {
        $registry = new element_registry();
        $registry->register(self::TYPE_LEGACY45, \customcertelement_legacy45\element::class);
        return new element_factory($registry);
    }

    public function test_real_restore_migrates_and_dispatches_legacy_hook(): void {
        global $DB;

        [$pageid, $customcertid] = $this->create_template_page_and_customcert();

        $task = new minimal_restore_task('rid-legacy45-' . uniqid(), 0, $customcertid);
        $task->set_element_factory($this->make_factory_with_legacy45());

        // Mirrors the genuine MOODLE_404_STABLE row shape. 999/555 stand in for old backup-file
        // ids; only the seeded *new* pageid is used.
        $step = new testable_restore_customcert_element_step('customcert_structure', 'customcert.xml', $task);
        $step->seed_page_mapping(999, $pageid);

        $now = time();
        $backuprow = (object) [
            'id' => 555,
            'pageid' => 999,
            'name' => 'Legacy45 BACKUP',
            'element' => self::TYPE_LEGACY45,
            'data' => 'legacyvalue',
            'font' => 'Arial',
            'fontsize' => 12,
            'colour' => '#112233',
            'posx' => 5,
            'posy' => 8,
            'width' => 10,
            'refpoint' => 0,
            'sequence' => 1,
            'alignment' => 'L',
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $this->resetDebugging();
        // The real, unmodified production method: migrates the legacy shape and inserts it.
        $step->invoke_process_customcert_element($backuprow);
        $this->assertDebuggingNotCalled();

        $row = $DB->get_record('customcert_elements', ['pageid' => $pageid, 'name' => 'Legacy45 BACKUP'], '*', MUST_EXIST);
        $this->assertSame(self::TYPE_LEGACY45, $row->element);
        $decoded = json_decode((string)$row->data, true);
        $this->assertSame('legacyvalue', $decoded['value']);
        $this->assertSame(10, (int)$decoded['width']);
        $this->assertSame('Arial', $decoded['font']);
        $this->assertSame('#112233', $decoded['colour']);

        // The factory always constructs a fresh instance internally, so this is the only way
        // to get a direct reference to the exact instance the dispatch loop used.
        \customcertelement_legacy45\element::$lastconstructed = null;
        $task->after_restore();

        // Two distinct #956 diagnostics, in order: the general compatibility notice from
        // wrapping the element, then the deprecated-hook notice from the adapter delegating
        // to it. Neither is suppressed.
        $this->assertDebuggingCalledCount(
            2,
            [
                'Legacy custom certificate element customcertelement_legacy45 is using the deprecated '
                    . 'mod_customcert legacy element API. Migrate the plugin to Element System v2 interfaces. '
                    . 'Legacy runtime compatibility will be removed in the Moodle 6.0-compatible release.',
                'The after_restore() method in customcertelement_legacy45\\element is deprecated. '
                    . 'Implement restorable_element_interface and use after_restore_from_backup() instead.',
            ]
        );

        // Direct proof the historical hook actually ran, on the actual dispatched instance,
        // and received this exact restore task -- not merely inferred from the notice above.
        $dispatched = \customcertelement_legacy45\element::$lastconstructed;
        $this->assertInstanceOf(\customcertelement_legacy45\element::class, $dispatched);
        $this->assertTrue($dispatched->restorecalled);
        $this->assertSame($task, $dispatched->lastrestore);
        // Release the reference so this test's restore task isn't retained by the static
        // property beyond this point.
        \customcertelement_legacy45\element::$lastconstructed = null;

        // The notice above already deduplicated this component, so recreating the instance
        // to check its type must not emit it again.
        $instance = $this->make_factory_with_legacy45()->create_from_record(
            $DB->get_record('customcert_elements', ['id' => $row->id], '*', MUST_EXIST)
        );
        $this->assertDebuggingNotCalled();
        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertInstanceOf(restorable_element_interface::class, $instance);

        // Historical untyped render_html() signature, reached through the adapter.
        $html = $instance->render_html();
        $this->assertStringContainsString('legacy45:', $html);
    }
}
