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
 * Unit tests for element copying logic.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/legacy_compatibility_diagnostic_test_trait.php');

use advanced_testcase;
use context_system;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\event\element_created;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_repository;
use mod_customcert\service\template_service;
use mod_customcert\tests\fixtures\copy_observing_element_fixture;
use mod_customcert\tests\fixtures\legacy_52_copyable_element_fixture;
use mod_customcert\tests\fixtures\legacy_copy_override_fixture;
use moodle_exception;

/**
 * Tests for consolidated element copy logic.
 */
final class element_copy_test extends advanced_testcase {
    use \mod_customcert\tests\legacy_compatibility_diagnostic_test_trait;

    /**
     * Set up the test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        copy_observing_element_fixture::reset();
        legacy_copy_override_fixture::reset();
        legacy_52_copyable_element_fixture::reset();
        // Each test starts with a clean per-component de-duplication state for the
        // general legacy compatibility diagnostic, independent of test execution order.
        $this->reset_legacy_compatibility_diagnostic_state();
    }

    /**
     * Build a factory that also knows about the copy_observing_element_fixture type.
     *
     * @return element_factory
     */
    private function factory_with_observing_fixture(): element_factory {
        $factory = element_factory::build_with_defaults();
        $factory->register('copyobserving', copy_observing_element_fixture::class);
        return $factory;
    }

    /**
     * Build a factory that also knows about the legacy_copy_override_fixture type.
     *
     * @return element_factory
     */
    private function factory_with_legacy_copy_override_fixture(): element_factory {
        $factory = element_factory::build_with_defaults();
        $factory->register('legacycopyoverride', legacy_copy_override_fixture::class);
        return $factory;
    }

    /**
     * Build a factory that also knows about the legacy_52_copyable_element_fixture type.
     *
     * @return element_factory
     */
    private function factory_with_legacy_52_copyable_fixture(): element_factory {
        $factory = element_factory::build_with_defaults();
        $factory->register('legacy52copyable', legacy_52_copyable_element_fixture::class);
        return $factory;
    }

    /**
     * Test that copy_element correctly duplicates an element.
     *
     * @covers \mod_customcert\service\element_repository::copy_element
     */
    public function test_copy_element(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Test element',
            'element' => 'text',
            'sequence' => 1,
            'timecreated' => time() - 100,
            'timemodified' => time() - 100,
        ]);

        $source = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);
        $sourceclone = clone($source);

        $targetpageid = $service->add_page($template);

        $factory = element_factory::build_with_defaults();
        $repository = new element_repository($factory);

        $newinstance = $repository->copy_element($source, $targetpageid);
        $this->assertNotNull($newinstance);

        $newrecord = $DB->get_record('customcert_elements', ['id' => $newinstance->get_id()], '*', MUST_EXIST);
        $this->assertEquals($targetpageid, $newrecord->pageid);
        $this->assertEquals('Test element', $newrecord->name);
        $this->assertNotEquals($source->id, $newrecord->id);
        $this->assertGreaterThanOrEqual($source->timecreated, $newrecord->timecreated);

        // The source record object passed in must not be mutated by the copy.
        $this->assertEquals($sourceclone, $source);
    }

    /**
     * Test that copy_element() invokes copy_from() on copyable elements, passes it the expected
     * source record, and that the result is reflected on the returned instance.
     *
     * @covers \mod_customcert\service\element_repository::copy_element
     */
    public function test_copy_element_invokes_copy_from_on_success(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);
        $targetpageid = $service->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Observing element',
            'element' => 'copyobserving',
            'sequence' => 1,
        ]);
        $source = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);

        $factory = $this->factory_with_observing_fixture();
        $repository = new element_repository($factory);

        copy_observing_element_fixture::$result = true;
        $newinstance = $repository->copy_element($source, $targetpageid);

        $this->assertNotNull($newinstance);
        $this->assertInstanceOf(copy_observing_element_fixture::class, $newinstance);
        $this->assertEquals(1, copy_observing_element_fixture::$calls);
        $this->assertEquals($source->id, copy_observing_element_fixture::$lastsource->id);
        $this->assertTrue($newinstance->copiedfrom);

        // The new row must still exist.
        $this->assertTrue($DB->record_exists('customcert_elements', ['id' => $newinstance->get_id()]));
    }

    /**
     * Test that when copy_from() returns false, the newly inserted row is cleaned up and
     * copy_element() reports failure.
     *
     * @covers \mod_customcert\service\element_repository::copy_element
     */
    public function test_copy_element_cleans_up_on_copy_from_failure(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);
        $targetpageid = $service->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Observing element',
            'element' => 'copyobserving',
            'sequence' => 1,
        ]);
        $source = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);

        $factory = $this->factory_with_observing_fixture();
        $repository = new element_repository($factory);

        copy_observing_element_fixture::$result = false;
        $countbefore = $DB->count_records('customcert_elements', ['pageid' => $targetpageid]);
        $result = $repository->copy_element($source, $targetpageid);

        $this->assertNull($result);
        $this->assertEquals(1, copy_observing_element_fixture::$calls);
        $countafter = $DB->count_records('customcert_elements', ['pageid' => $targetpageid]);
        $this->assertEquals($countbefore, $countafter);
    }

    /**
     * Native v2 copyable_element_interface takes precedence over legacy copy_element()
     * dispatch, and never triggers any legacy-copy diagnostic (#984).
     *
     * @covers \mod_customcert\service\element_repository::copy_element
     */
    public function test_copy_element_native_v2_copyable_takes_precedence_over_legacy_dispatch(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);
        $targetpageid = $service->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Observing element',
            'element' => 'copyobserving',
            'sequence' => 1,
        ]);
        $source = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);

        $factory = $this->factory_with_observing_fixture();
        $repository = new element_repository($factory);

        copy_observing_element_fixture::$result = true;
        $copied = $repository->copy_element($source, $targetpageid);

        // The fixture is never wrapped in legacy_element_adapter, so no diagnostic of any
        // kind (general compatibility notice or legacy-copy deprecation) is emitted.
        $this->assertDebuggingNotCalled();
        $this->assertNotNull($copied);
        $this->assertNotInstanceOf(legacy_element_adapter::class, $copied);
        $this->assertEquals(1, copy_observing_element_fixture::$calls);
    }

    /**
     * A released-5.2-compatible element that implements copyable_element_interface but not
     * renderable_element_interface is still wrapped by legacy_element_adapter under current
     * 5.3 (#981 removed renderable_element_interface from the legacy base). copy_from() on
     * the wrapped inner element must still take precedence over the deprecated legacy
     * copy_element() dispatch, and no legacy-copy diagnostic must be emitted (#984).
     *
     * @covers \mod_customcert\service\element_repository::copy_element
     */
    public function test_copy_element_adapter_wrapped_copyable_uses_copy_from(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);
        $targetpageid = $service->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Wrapped copyable element',
            'element' => 'legacy52copyable',
            'sequence' => 1,
        ]);
        $source = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);

        $factory = $this->factory_with_legacy_52_copyable_fixture();
        $repository = new element_repository($factory);

        // The factory wraps this element in legacy_element_adapter (it does not implement
        // renderable_element_interface), and emits the general legacy-compatibility
        // diagnostic while doing so; that is unrelated to the legacy-copy dispatch this
        // test is verifying.
        $loaded = $repository->load_by_page_id($pageid);
        $this->assertDebuggingCalled();
        $this->assertCount(1, $loaded);
        $this->assertInstanceOf(legacy_element_adapter::class, $loaded[0]);
        $this->assertInstanceOf(legacy_52_copyable_element_fixture::class, $loaded[0]->get_inner());

        legacy_52_copyable_element_fixture::$result = true;
        $copied = $repository->copy_element($source, $targetpageid);

        // Copy_from() precedence means no legacy copy_element() deprecation is emitted.
        $this->assertDebuggingNotCalled();

        $this->assertNotNull($copied);
        $this->assertInstanceOf(legacy_element_adapter::class, $copied);
        $this->assertInstanceOf(legacy_52_copyable_element_fixture::class, $copied->get_inner());
        $this->assertEquals(1, legacy_52_copyable_element_fixture::$calls);
        $this->assertEquals($source->id, legacy_52_copyable_element_fixture::$lastsource->id);
        $this->assertTrue($DB->record_exists('customcert_elements', ['id' => $copied->get_id()]));
    }

    /**
     * The same adapter-wrapped copyable population removes the copied row and reports
     * failure when copy_from() returns false, exactly like the native v2 failure path (#984).
     *
     * @covers \mod_customcert\service\element_repository::copy_element
     */
    public function test_copy_element_adapter_wrapped_copyable_cleans_up_on_copy_from_failure(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);
        $targetpageid = $service->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Wrapped copyable element',
            'element' => 'legacy52copyable',
            'sequence' => 1,
        ]);
        $source = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);

        $factory = $this->factory_with_legacy_52_copyable_fixture();
        $repository = new element_repository($factory);

        legacy_52_copyable_element_fixture::$result = false;
        $countbefore = $DB->count_records('customcert_elements', ['pageid' => $targetpageid]);
        $result = $repository->copy_element($source, $targetpageid);
        // Only the general legacy-compatibility diagnostic (for the adapter wrap) is
        // expected here, never the legacy-copy deprecation.
        $this->resetDebugging();

        $this->assertNull($result);
        $this->assertEquals(1, legacy_52_copyable_element_fixture::$calls);
        $countafter = $DB->count_records('customcert_elements', ['pageid' => $targetpageid]);
        $this->assertEquals($countbefore, $countafter);
    }

    /**
     * A genuine legacy element whose concrete class overrides the deprecated
     * element::copy_element() hook is dispatched to it, receiving the historical source
     * record, when the copy succeeds (#984).
     *
     * @covers \mod_customcert\service\element_repository::copy_element
     */
    public function test_copy_element_dispatches_to_legacy_copy_element_override(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);
        $targetpageid = $service->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Legacy copy override',
            'element' => 'legacycopyoverride',
            'sequence' => 1,
        ]);
        $source = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);

        $factory = $this->factory_with_legacy_copy_override_fixture();
        $repository = new element_repository($factory);

        legacy_copy_override_fixture::$result = true;
        $copied = $repository->copy_element($source, $targetpageid);

        $messages = $this->getDebuggingMessages();
        $this->resetDebugging();
        $found = false;
        foreach ($messages as $message) {
            if (str_contains($message->message, 'element::copy_element() is deprecated since Moodle 5.2')) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'The legacy copy_element() deprecation must be emitted.');

        $this->assertNotNull($copied);
        $this->assertInstanceOf(legacy_element_adapter::class, $copied);
        $this->assertEquals(1, legacy_copy_override_fixture::$calls);
        $this->assertEquals($source->id, legacy_copy_override_fixture::$lastsource->id);
        $this->assertTrue($DB->record_exists('customcert_elements', ['id' => $copied->get_id()]));
    }

    /**
     * A genuine legacy copy_element() override returning strict false causes the copied row
     * to be removed and the copy to be reported as failed (#984).
     *
     * @covers \mod_customcert\service\element_repository::copy_element
     */
    public function test_copy_element_legacy_override_returning_false_removes_copy(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);
        $targetpageid = $service->add_page($template);

        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Legacy copy override',
            'element' => 'legacycopyoverride',
            'sequence' => 1,
        ]);
        $source = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);

        $factory = $this->factory_with_legacy_copy_override_fixture();
        $repository = new element_repository($factory);

        legacy_copy_override_fixture::$result = false;
        $countbefore = $DB->count_records('customcert_elements', ['pageid' => $targetpageid]);
        $result = $repository->copy_element($source, $targetpageid);

        $messages = $this->getDebuggingMessages();
        $this->resetDebugging();
        $found = false;
        foreach ($messages as $message) {
            if (str_contains($message->message, 'element::copy_element() is deprecated since Moodle 5.2')) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'The legacy copy_element() deprecation must be emitted even on failure.');

        $this->assertNull($result);
        $this->assertEquals(1, legacy_copy_override_fixture::$calls);
        $countafter = $DB->count_records('customcert_elements', ['pageid' => $targetpageid]);
        $this->assertEquals($countbefore, $countafter);
    }

    /**
     * Test that copy_page does not increment its count for an element whose copy_from() fails.
     *
     * @covers \mod_customcert\service\element_repository::copy_page
     */
    public function test_copy_page_does_not_count_copy_from_failure(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $sourcepageid = $service->add_page($template);
        $targetpageid = $service->add_page($template);

        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $sourcepageid,
            'name' => 'Observing element',
            'element' => 'copyobserving',
            'sequence' => 1,
        ]);

        $factory = $this->factory_with_observing_fixture();
        $repository = new element_repository($factory);

        copy_observing_element_fixture::$result = false;
        $count = $repository->copy_page($sourcepageid, $targetpageid);
        $this->assertEquals(0, $count);
    }

    /**
     * Test that copy_to_template does not emit element_created for an element whose copy_from()
     * fails.
     *
     * @covers \mod_customcert\service\template_service::copy_to_template
     */
    public function test_copy_to_template_does_not_emit_event_on_copy_from_failure(): void {
        global $DB;

        $source = template::create('Source', context_system::instance()->id);
        $target = template::create('Target', context_system::instance()->id);
        $factory = $this->factory_with_observing_fixture();
        $service = new template_service(
            new \mod_customcert\service\template_repository(),
            new \mod_customcert\service\page_repository(),
            new element_repository($factory),
            $factory,
            new \mod_customcert\service\item_move_service($DB, new \mod_customcert\service\page_repository()),
        );
        $pageid = $service->add_page($source);

        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Observing element',
            'element' => 'copyobserving',
            'sequence' => 1,
        ]);

        copy_observing_element_fixture::$result = false;
        $sink = $this->redirectEvents();
        $service->copy_to_template($source, $target);

        $events = $sink->get_events();
        $elementcreated = array_filter($events, fn($e) => $e instanceof element_created);
        $this->assertEmpty($elementcreated);
    }

    /**
     * Test that copy_page uses consolidated logic and does not fire events.
     *
     * @covers \mod_customcert\service\element_repository::copy_page
     */
    public function test_copy_page_does_not_fire_events(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $sourcepageid = $service->add_page($template);
        $targetpageid = $service->add_page($template);

        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $sourcepageid,
            'name' => 'E1',
            'element' => 'text',
            'sequence' => 1,
        ]);

        $factory = element_factory::build_with_defaults();
        $repository = new element_repository($factory);

        $sink = $this->redirectEvents();
        $count = $repository->copy_page($sourcepageid, $targetpageid);
        $this->assertEquals(1, $count);

        $events = $sink->get_events();
        $elementcreated = array_filter($events, fn($e) => $e instanceof element_created);
        $this->assertEmpty($elementcreated);
    }

    /**
     * Test that copy_to_template uses consolidated logic and fires events.
     *
     * @covers \mod_customcert\service\template_service::copy_to_template
     */
    public function test_copy_to_template_fires_events(): void {
        global $DB;

        $source = template::create('Source', context_system::instance()->id);
        $target = template::create('Target', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($source);

        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'E1',
            'element' => 'text',
            'sequence' => 1,
        ]);

        $sink = $this->redirectEvents();
        $service->copy_to_template($source, $target);

        $events = $sink->get_events();
        $elementcreated = array_filter($events, fn($e) => $e instanceof element_created);
        $this->assertCount(1, $elementcreated);
    }

    /**
     * Characterization test: copy_page() historically let an unresolvable element type throw,
     * aborting the copy rather than silently skipping it. Verify this is preserved.
     *
     * @covers \mod_customcert\service\element_repository::copy_page
     */
    public function test_copy_page_throws_for_unregistered_element_type(): void {
        global $DB;

        $template = template::create('Source', context_system::instance()->id);
        $service = template_service::create();
        $sourcepageid = $service->add_page($template);
        $targetpageid = $service->add_page($template);

        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $sourcepageid,
            'name' => 'Unregistered',
            'element' => 'nosuchtype',
            'sequence' => 1,
        ]);

        $factory = element_factory::build_with_defaults();
        $repository = new element_repository($factory);

        $this->expectException(moodle_exception::class);
        $repository->copy_page($sourcepageid, $targetpageid);
    }

    /**
     * Characterization test: copy_to_template() historically tolerated an unresolvable element
     * type by leaving the inserted row in place and skipping without emitting element_created
     * or raising an error. Verify this is preserved.
     *
     * @covers \mod_customcert\service\template_service::copy_to_template
     */
    public function test_copy_to_template_tolerates_unregistered_element_type(): void {
        global $DB;

        $source = template::create('Source', context_system::instance()->id);
        $target = template::create('Target', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($source);

        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'Unregistered',
            'element' => 'nosuchtype',
            'sequence' => 1,
        ]);

        $sink = $this->redirectEvents();
        $service->copy_to_template($source, $target);

        $events = $sink->get_events();
        $elementcreated = array_filter($events, fn($e) => $e instanceof element_created);
        $this->assertEmpty($elementcreated);

        // The row was still inserted (orphaned) into the target page, matching the historical
        // tolerant behaviour of copy_to_template().
        $targetpages = $DB->get_records('customcert_pages', ['templateid' => $target->get_id()]);
        $targetpage = reset($targetpages);
        $this->assertTrue($DB->record_exists('customcert_elements', [
            'pageid' => $targetpage->id,
            'element' => 'nosuchtype',
        ]));
    }
}
