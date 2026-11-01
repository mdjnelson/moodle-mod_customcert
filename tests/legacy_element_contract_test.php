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
 * Tests for historical (Moodle 4.5-5.1) third-party elements running on Moodle 5.2.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use context_course;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_layout;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\form_service;
use mod_customcert\service\page_repository;
use mod_customcert\service\persistence_helper;
use mod_customcert\service\template_repository;
use mod_customcert\service\validation_service;
use MoodleQuickForm;
use restore_customcert_activity_task;
use stdClass;

/**
 * Tests that unchanged historical elements load and work through the legacy adapter.
 *
 * @covers \mod_customcert\element
 * @covers \mod_customcert\element\legacy_element_adapter
 * @covers \mod_customcert\service\element_factory
 * @covers \mod_customcert\service\element_registry
 */
final class legacy_element_contract_test extends advanced_testcase {
    /** @var array<string, string|null> Cached isolated class-load failures keyed by fixture path. */
    private static array $loadresults = [];

    /**
     * Load a fixture class file only after proving in a subprocess that PHP can declare it.
     *
     * A signature incompatibility is a compile-time fatal error that would abort the whole PHPUnit run.
     *
     * @param string $path Absolute path to the fixture or plugin element.php.
     */
    private function require_historical_class(string $path): void {
        if (!array_key_exists($path, self::$loadresults)) {
            self::$loadresults[$path] = self::declare_class_in_subprocess($path);
        }
        if (self::$loadresults[$path] !== null) {
            $this->fail("Historical element class could not be loaded:\n" . self::$loadresults[$path]);
        }
        require_once($path);
    }

    /**
     * Declare a class file in an isolated PHP process, returning null on success or the output on failure.
     *
     * @param string $path
     * @return string|null
     */
    private static function declare_class_in_subprocess(string $path): ?string {
        $classesdir = dirname(__DIR__) . '/classes';
        $code = 'define("MOODLE_INTERNAL", true);'
            . 'spl_autoload_register(function ($class) {'
            . '  $prefix = "mod_customcert\\\\";'
            . '  if (strpos($class, $prefix) === 0) {'
            . '    $file = ' . var_export($classesdir, true)
            . ' . "/" . str_replace("\\\\", "/", substr($class, strlen($prefix))) . ".php";'
            . '    if (is_file($file)) { require_once $file; }'
            . '  }'
            . '});'
            . 'require_once ' . var_export($path, true) . ';'
            . 'echo "LOADED";';

        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=stderr', '-r', $code],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitcode = proc_close($process);

        if ($exitcode === 0 && trim($stdout) === 'LOADED') {
            return null;
        }
        return trim($stdout . "\n" . $stderr);
    }

    /**
     * Require the historical fixture element.
     */
    private function require_legacy45(): void {
        $this->require_historical_class(__DIR__ . '/fixtures/customcertelement_legacy45/element.php');
    }

    /**
     * Build a factory that has the historical fixture registered.
     *
     * @return element_factory
     */
    private function make_factory(): element_factory {
        $this->require_legacy45();
        $registry = new element_registry();
        $registry->register('legacy45', \customcertelement_legacy45\element::class);
        return new element_factory($registry);
    }

    /**
     * Build a minimal element record.
     *
     * @param string $element
     * @param array $overrides
     * @return stdClass
     */
    private function make_record(string $element = 'legacy45', array $overrides = []): stdClass {
        return (object) array_merge([
            'id' => 7,
            'pageid' => 3,
            'name' => 'Historical element',
            'element' => $element,
            'data' => json_encode(['legacyvalue' => 'hello']),
            'posx' => 10,
            'posy' => 20,
            'refpoint' => 1,
            'alignment' => element::ALIGN_LEFT,
        ], $overrides);
    }

    /**
     * Create a template and page, returning the page id.
     *
     * @return int
     */
    private function create_page(): int {
        $course = $this->getDataGenerator()->create_course();
        $templateid = (new template_repository())->create((object) [
            'name' => 'Template',
            'contextid' => context_course::instance($course->id)->id,
        ]);
        return (new page_repository())->create((object) [
            'templateid' => $templateid,
            'width' => 800,
            'height' => 600,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
        ]);
    }

    /**
     * The legacy base class must not impose the v2 rendering contract.
     */
    public function test_base_class_does_not_impose_v2_rendering_contract(): void {
        $base = new \ReflectionClass(element::class);

        $this->assertFalse($base->implementsInterface(renderable_element_interface::class));
        $this->assertFalse($base->hasMethod('render'));
        $this->assertFalse($base->hasMethod('render_html'));
    }

    /**
     * A genuine historical class must be declarable by PHP.
     */
    public function test_historical_element_class_loads(): void {
        $this->require_legacy45();

        $this->assertTrue(class_exists(\customcertelement_legacy45\element::class, false));
    }

    /**
     * The unchanged standalone daterange plugin must be declarable when it is installed.
     */
    public function test_unchanged_daterange_plugin_class_loads_when_installed(): void {
        $path = dirname(__DIR__) . '/element/daterange/classes/element.php';
        if (!is_file($path)) {
            $this->markTestSkipped('The standalone daterange plugin is not installed.');
        }

        $this->require_historical_class($path);

        $this->assertTrue(class_exists(\customcertelement_daterange\element::class, false));
    }

    /**
     * The registry accepts historical subclasses and still rejects unrelated classes.
     */
    public function test_registry_accepts_historical_subclass_and_rejects_unrelated_class(): void {
        $this->require_legacy45();
        $registry = new element_registry();

        $registry->register('legacy45', \customcertelement_legacy45\element::class);
        $this->assertSame(\customcertelement_legacy45\element::class, $registry->get('legacy45'));

        $this->expectException(\coding_exception::class);
        $registry->register('notanelement', stdClass::class);
    }

    /**
     * The factory wraps historical elements in the adapter and preserves identity.
     */
    public function test_factory_wraps_historical_element_in_adapter(): void {
        $element = $this->make_factory()->create('legacy45', $this->make_record());

        $this->assertInstanceOf(legacy_element_adapter::class, $element);
        $this->assertInstanceOf(\customcertelement_legacy45\element::class, $element->get_inner());
        $this->assertSame(7, $element->get_id());
        $this->assertSame(3, $element->get_pageid());
        $this->assertSame('legacy45', $element->get_type());
        $this->assertSame('Historical element', $element->get_name());
    }

    /**
     * Historical render($pdf, $preview, $user) receives exactly its three declared arguments.
     */
    public function test_adapter_renders_pdf_with_historical_three_argument_signature(): void {
        global $CFG, $USER;
        require_once($CFG->libdir . '/pdflib.php');

        $element = $this->make_factory()->create('legacy45', $this->make_record());
        $pdf = new \pdf();
        $pdf->AddPage();

        $element->render($pdf, true, $USER);

        $args = $element->get_inner()->renderargs;
        $this->assertCount(3, $args);
        $this->assertSame($pdf, $args[0]);
        $this->assertTrue($args[1]);
        $this->assertSame($USER, $args[2]);
    }

    /**
     * Historical render_html() is called without a renderer and null becomes an empty string.
     */
    public function test_adapter_renders_html_with_historical_signature_and_null_result(): void {
        $factory = $this->make_factory();

        $withdata = $factory->create('legacy45', $this->make_record());
        $this->assertStringContainsString('hello', $withdata->render_html());

        $withoutdata = $factory->create('legacy45', $this->make_record('legacy45', ['data' => '']));
        $this->assertSame('', $withoutdata->render_html());
    }

    /**
     * Form creation, preparation and validation work through the adapter.
     */
    public function test_form_creation_preparation_and_validation(): void {
        $this->resetAfterTest();
        $element = $this->make_factory()->create('legacy45', $this->make_record());

        $mform = new MoodleQuickForm('legacyform', 'post', '');
        (new form_service())->build_form($mform, $element);
        $this->assertTrue($mform->elementExists('legacyvalue'));

        (new form_service())->prepare_after_data($mform, $element);
        $this->assertSame('hello', $mform->getElement('legacyvalue')->getValue());
        $this->resetDebugging();

        $validator = new validation_service();
        $this->assertArrayHasKey('legacyvalue', $validator->validate($element, ['legacyvalue' => 'bad']));
        $this->assertSame([], $validator->validate($element, ['legacyvalue' => 'good']));
        $this->resetDebugging();
    }

    /**
     * Adding, editing and reopening a historical element persists its JSON payload.
     */
    public function test_persistence_lifecycle(): void {
        $this->resetAfterTest();
        $factory = $this->make_factory();
        $repository = new element_repository($factory);
        $pageid = $this->create_page();
        $layout = new element_layout(10, 20, 1, element::ALIGN_LEFT);

        $json = persistence_helper::to_json_data(
            $factory->create('legacy45', $this->make_record('legacy45', ['id' => 0, 'pageid' => $pageid])),
            (object) ['legacyvalue' => 'first']
        );
        $this->assertSame('first', json_decode($json)->legacyvalue);

        $id = $repository->create(
            $factory->create('legacy45', $this->make_record('legacy45', ['id' => 0, 'pageid' => $pageid, 'data' => $json])),
            $layout
        );

        $reopened = $repository->load_by_page_id($pageid);
        $this->assertCount(1, $reopened);
        $this->assertInstanceOf(legacy_element_adapter::class, $reopened[0]);
        $this->assertSame($id, $reopened[0]->get_id());
        $this->assertSame('first', json_decode($reopened[0]->get_data())->legacyvalue);

        $newjson = persistence_helper::to_json_data($reopened[0], (object) ['legacyvalue' => 'second']);
        $edited = $factory->create(
            'legacy45',
            $this->make_record('legacy45', ['id' => $id, 'pageid' => $pageid, 'data' => $newjson])
        );
        $repository->save($edited, $layout);

        $reopened = $repository->load_by_page_id($pageid);
        $this->assertSame('second', json_decode($reopened[0]->get_data())->legacyvalue);
        $this->assertStringContainsString('second', $reopened[0]->render_html());
        $this->resetDebugging();
    }

    /**
     * The historical after_restore() hook still runs through the adapter.
     */
    public function test_restore_hook_runs(): void {
        $this->resetAfterTest();
        $element = $this->make_factory()->create('legacy45', $this->make_record());
        $restore = $this->getMockBuilder(restore_customcert_activity_task::class)
            ->disableOriginalConstructor()
            ->getMock();

        $element->after_restore_from_backup($restore);

        $this->assertTrue($element->get_inner()->restored);
        $this->resetDebugging();
    }

    /**
     * Optional v2 hooks implemented by an adapted element keep working.
     */
    public function test_adapted_element_keeps_optional_v2_capabilities(): void {
        $this->resetAfterTest();
        $this->require_historical_class(__DIR__ . '/fixtures/customcertelement_legacyv2hooks/element.php');
        $registry = new element_registry();
        $registry->register('legacyv2hooks', \customcertelement_legacyv2hooks\element::class);
        $element = (new element_factory($registry))->create('legacyv2hooks', $this->make_record('legacyv2hooks'));

        $this->assertInstanceOf(legacy_element_adapter::class, $element);
        $inner = $element->get_inner();
        $this->assertInstanceOf(persistable_element_interface::class, $inner);
        $this->assertInstanceOf(preparable_form_interface::class, $inner);

        $json = json_decode(persistence_helper::to_json_data($element, (object) ['v2value' => 'x']), true);
        $this->assertSame('x', $json['v2value']);
        $this->assertSame(['a' => 1], $json['nested']);

        (new form_service())->prepare_after_data(new MoodleQuickForm('f', 'post', ''), $element);
        $this->assertTrue($inner->prepared);

        $this->assertArrayHasKey('v2value', (new validation_service())->validate($element, ['v2value' => 'bad']));
        $this->assertDebuggingNotCalled();
    }
}
