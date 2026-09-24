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
 * Tests for the general legacy element compatibility deprecation diagnostic (#956).
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/customcertelement_legacy45/element.php');
require_once(__DIR__ . '/fixtures/customcertelement_legacy52/element.php');
require_once(__DIR__ . '/fixtures/native_v2_control_element.php');
require_once(__DIR__ . '/legacy_compatibility_diagnostic_test_trait.php');

use advanced_testcase;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\html_renderer;
use mod_customcert\tests\fixtures\native_v2_control_element;
use stdClass;

/**
 * Tests for the general legacy element compatibility deprecation diagnostic.
 *
 * @covers \mod_customcert\element\legacy_compatibility_diagnostic
 * @covers \mod_customcert\service\element_factory
 */
final class legacy_element_deprecation_diagnostic_test extends advanced_testcase {
    use \mod_customcert\tests\legacy_compatibility_diagnostic_test_trait;

    protected function setUp(): void {
        parent::setUp();
        // Each test starts with a clean per-component de-duplication state, independent
        // of test execution order (PHP statics otherwise survive across test methods).
        $this->reset_legacy_compatibility_diagnostic_state();
    }

    /**
     * Build a minimal element DB-shaped record.
     *
     * @param array $overrides
     * @return stdClass
     */
    private function make_record(array $overrides = []): stdClass {
        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Legacy element',
            'data' => json_encode([
                'font' => 'Helvetica',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 50,
                'value' => 'hello',
            ]),
            'posx' => 5,
            'posy' => 6,
            'refpoint' => 1,
            'alignment' => 'L',
            'element' => 'legacy45',
        ];
        foreach ($overrides as $k => $v) {
            $record->$k = $v;
        }
        return $record;
    }

    /**
     * Build a factory registering the real #958 legacy45/legacy52 fixtures plus the
     * native v2 control.
     *
     * @return element_factory
     */
    private function make_factory(): element_factory {
        $registry = new element_registry();
        $registry->register('legacy45', \customcertelement_legacy45\element::class);
        $registry->register('legacy52', \customcertelement_legacy52\element::class);
        $registry->register('nativev2diag', native_v2_control_element::class);
        return new element_factory($registry);
    }

    /**
     * Using a realistic legacy fixture, wrapping it via the factory must emit the
     * general legacy compatibility diagnostic identifying its component, naming the
     * deprecated legacy API, pointing to Element System v2, and stating that runtime
     * compatibility is removed in the Moodle 6.0-compatible release.
     */
    public function test_legacy_element_emits_general_compatibility_diagnostic(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();
        $instance = $factory->create('legacy45', $this->make_record(['element' => 'legacy45']));

        $this->assertInstanceOf(legacy_element_adapter::class, $instance);
        $debugging = $this->getDebuggingMessages();
        $this->assertCount(1, $debugging, 'Exactly one general legacy-compatibility diagnostic must be emitted.');
        $this->resetDebugging();
        $message = $debugging[0]->message;

        // The warning must identify the affected component/type.
        $this->assertStringContainsString('customcertelement_legacy45', $message);
        // The warning must name the deprecated legacy API.
        $this->assertStringContainsString('legacy element API', $message);
        $this->assertStringContainsString('deprecated', $message);
        // The warning must point to Element System v2.
        $this->assertStringContainsString('Element System v2', $message);
        // The warning must say runtime compatibility is removed in the Moodle 6.0
        // compatible release.
        $this->assertStringContainsString('Moodle 6.0-compatible release', $message);
        $this->assertSame(DEBUG_DEVELOPER, $debugging[0]->level);
    }

    /**
     * Repeated use/construction of the SAME legacy type within one request/test must
     * not emit the general compatibility warning more than once.
     */
    public function test_general_compatibility_warning_is_deduplicated_per_component(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();

        $factory->create('legacy45', $this->make_record(['element' => 'legacy45']));
        $this->assertDebuggingCalled();

        // Constructing the same type again in the same request must not warn again.
        for ($i = 0; $i < 5; $i++) {
            $factory->create('legacy45', $this->make_record(['element' => 'legacy45']));
        }
        $this->assertDebuggingNotCalled();
    }

    /**
     * Different legacy components/types must each be identifiable independently while
     * repeats of each type are still deduplicated.
     */
    public function test_multiple_legacy_components_are_each_identified_and_deduplicated(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();

        // First use of legacy45: warns, identifying legacy45.
        $factory->create('legacy45', $this->make_record(['element' => 'legacy45']));
        $messages45 = $this->getDebuggingMessages();
        $this->assertCount(1, $messages45);
        $this->resetDebugging();
        $this->assertStringContainsString('customcertelement_legacy45', $messages45[0]->message);

        // First use of legacy52: warns independently, identifying legacy52.
        $factory->create('legacy52', $this->make_record(['element' => 'legacy52']));
        $messages52 = $this->getDebuggingMessages();
        $this->assertCount(1, $messages52);
        $this->resetDebugging();
        $this->assertStringContainsString('customcertelement_legacy52', $messages52[0]->message);

        // Repeating legacy45 again must not re-warn, even though legacy52 has since
        // been warned about.
        $factory->create('legacy45', $this->make_record(['element' => 'legacy45']));
        $this->assertDebuggingNotCalled();

        // Repeating legacy52 again must not re-warn either.
        $factory->create('legacy52', $this->make_record(['element' => 'legacy52']));
        $this->assertDebuggingNotCalled();
    }

    /**
     * Native Element System v2 elements must never emit the legacy compatibility
     * diagnostic, since they are never wrapped by the legacy adapter.
     */
    public function test_native_v2_element_never_emits_legacy_diagnostic(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();
        $instance = $factory->create('nativev2diag', $this->make_record(['element' => 'nativev2diag']));

        $this->assertNotInstanceOf(legacy_element_adapter::class, $instance);
        $this->assertInstanceOf(native_v2_control_element::class, $instance);
        $this->assertDebuggingNotCalled();
    }

    /**
     * The diagnostic must be strictly non-intrusive: HTML rendering output/delegation
     * for a legacy element is unchanged, whether or not the diagnostic has already
     * fired for that component in this request.
     */
    public function test_html_rendering_output_is_unchanged_by_the_diagnostic(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();
        $renderer = new html_renderer();

        // First render: diagnostic fires, but output must be correct regardless.
        $instance = $factory->create('legacy45', $this->make_record(['element' => 'legacy45']));
        $this->assertDebuggingCalled();
        $html = $renderer->render_html($instance);
        $this->assertSame('legacy45:Helvetica', $html);

        // Second render of the same component: diagnostic is deduplicated (silent),
        // but the rendered HTML output must be identical.
        $instance2 = $factory->create('legacy45', $this->make_record(['element' => 'legacy45']));
        $this->assertDebuggingNotCalled();
        $html2 = $renderer->render_html($instance2);
        $this->assertSame('legacy45:Helvetica', $html2);
    }

    /**
     * The diagnostic must be strictly non-intrusive: PDF rendering delegation for a
     * legacy element is unchanged by the diagnostic having fired, and remains identical
     * once the diagnostic has been deduplicated (silent) for a repeated instance of the
     * same component.
     */
    public function test_pdf_rendering_delegation_is_unchanged_by_the_diagnostic(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/pdflib.php');

        $factory = $this->make_factory();
        $pdf = $this->getMockBuilder(\pdf::class)->disableOriginalConstructor()->getMock();
        $user = new stdClass();

        // First instance: the general diagnostic fires. Delegation must still receive
        // the exact $pdf/$preview/$user arguments unchanged.
        $instance = $factory->create('legacy45', $this->make_record(['element' => 'legacy45']));
        $this->assertDebuggingCalled();

        $instance->render($pdf, false, $user);
        $this->assertTrue($instance->get_inner()->rendercalled);
        [$forwardedpdf, $forwardedpreview, $forwardeduser] = $instance->get_inner()->lastrenderargs;
        $this->assertSame($pdf, $forwardedpdf);
        $this->assertFalse($forwardedpreview);
        $this->assertSame($user, $forwardeduser);

        // Second instance of the SAME component: the diagnostic is deduplicated
        // (silent), but PDF delegation must behave identically.
        $instance2 = $factory->create('legacy45', $this->make_record(['element' => 'legacy45']));
        $this->assertDebuggingNotCalled();

        $instance2->render($pdf, true, $user);
        $this->assertTrue($instance2->get_inner()->rendercalled);
        [$forwardedpdf2, $forwardedpreview2, $forwardeduser2] = $instance2->get_inner()->lastrenderargs;
        $this->assertSame($pdf, $forwardedpdf2);
        $this->assertTrue($forwardedpreview2);
        $this->assertSame($user, $forwardeduser2);
    }
}
