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
 * Regression tests for the published Element System v2 contracts.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use customcertelement_text\element as text_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\layout_element_interface;
use mod_customcert\element\raw_data_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\stylable_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_renderer;
use mod_customcert\service\html_renderer;
use mod_customcert\service\pdf_renderer;
use mod_customcert\service\validation_service;
use mod_customcert\tests\fixtures\minimal_test_element;
use ReflectionMethod;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/minimal_test_element.php');

/**
 * Regression tests for the published Element System v2 contracts.
 *
 * @group mod_customcert
 */
final class element_contract_test extends advanced_testcase {
    /**
     * Build a minimal element record.
     *
     * @return stdClass
     */
    private function make_record(): stdClass {
        return (object) ['id' => 1, 'pageid' => 1, 'name' => 'Minimal', 'data' => ''];
    }

    /**
     * A bundled element keeps the interfaces inherited from the base class plus its own declarations,
     * but does not declare the optional validatable_element_interface.
     *
     * @covers \mod_customcert\element
     */
    public function test_bundled_element_retains_public_interfaces(): void {
        $implemented = class_implements(text_element::class);

        // Inherited from the mod_customcert\element base class.
        foreach (
            [
                form_element_interface::class,
                layout_element_interface::class,
                raw_data_element_interface::class,
                stylable_element_interface::class,
            ] as $interface
        ) {
            $this->assertArrayHasKey($interface, $implemented, "text element lost {$interface}");
        }

        // Also inherited from the 5.2 base class, and redeclared by the element itself.
        $this->assertArrayHasKey(renderable_element_interface::class, $implemented);

        // Custom form validation is optional and bundled elements have no element-specific rules.
        $this->assertArrayNotHasKey(validatable_element_interface::class, $implemented);
    }

    /**
     * No bundled element opts in to the optional validation interface, and standard colour, position and
     * width validation still runs for each of them through the real validation service without activating
     * the deprecated validate_form_elements() fallback.
     *
     * @covers \mod_customcert\service\validation_service::validate
     */
    public function test_bundled_elements_do_not_implement_validation_but_keep_standard_validation(): void {
        $this->resetAfterTest();
        set_config('showposxy', 1, 'customcert');

        $elements = [
            'bgimage',
            'border',
            'categoryname',
            'code',
            'coursefield',
            'coursename',
            'date',
            'digitalsignature',
            'expiry',
            'grade',
            'gradeitemname',
            'groupname',
            'image',
            'qrcode',
            'studentname',
            'teachername',
            'text',
            'userfield',
            'userpicture',
        ];

        $svc = new validation_service();
        foreach ($elements as $name) {
            $class = "customcertelement_{$name}\\element";
            $this->assertTrue(class_exists($class), "{$class} should exist");

            $implemented = class_implements($class);
            $this->assertArrayNotHasKey(validatable_element_interface::class, $implemented, $class);
            $this->assertArrayHasKey(form_element_interface::class, $implemented, $class);
            $this->assertArrayHasKey(renderable_element_interface::class, $implemented, $class);
            $this->assertFalse(method_exists($class, 'validate'), "{$class} should not declare validate()");

            $element = new $class($this->make_record());
            $errors = $svc->validate($element, [
                'name' => 'Bundled',
                'width' => -1,
                'posx' => -1,
                'posy' => -1,
                'colour' => 'nope',
            ]);
            $this->assertEqualsCanonicalizing(['width', 'posx', 'posy', 'colour'], array_keys($errors), $class);

            $this->assertSame([], $svc->validate($element, [
                'name' => 'Bundled',
                'width' => 0,
                'posx' => 1,
                'posy' => 1,
                'colour' => '#000000',
            ]), $class);
        }

        // The deprecated legacy fallback must not be activated for any bundled element.
        $this->assertDebuggingNotCalled();
    }

    /**
     * Moodle 5.2 base class implements renderable_element_interface (unlike 5.3) but not the
     * optional validatable_element_interface.
     *
     * @covers \mod_customcert\element
     */
    public function test_base_class_implements_renderable_but_not_validatable(): void {
        $implemented = class_implements(element::class);

        $this->assertArrayHasKey(renderable_element_interface::class, $implemented);
        $this->assertArrayHasKey(form_element_interface::class, $implemented);
        $this->assertArrayNotHasKey(validatable_element_interface::class, $implemented);
    }

    /**
     * A third-party native v2 element without the optional validation interface registers, builds,
     * renders and validates through the shared services.
     *
     * @covers \mod_customcert\service\element_registry::register
     * @covers \mod_customcert\service\element_factory::create
     * @covers \mod_customcert\service\validation_service::validate
     */
    public function test_minimal_native_element_without_validation_interface(): void {
        global $CFG, $USER;
        require_once($CFG->libdir . '/pdflib.php');

        $this->resetAfterTest();
        set_config('showposxy', 1, 'customcert');

        $registry = new element_registry();
        $registry->register('minimalthirdparty', minimal_test_element::class);
        $this->assertTrue($registry->has('minimalthirdparty'));

        $element = (new element_factory($registry))->create('minimalthirdparty', $this->make_record());

        // Returned unwrapped, with the required form and rendering interfaces and no validation interface.
        $this->assertInstanceOf(minimal_test_element::class, $element);
        $this->assertInstanceOf(element_interface::class, $element);
        $this->assertInstanceOf(form_element_interface::class, $element);
        $this->assertInstanceOf(renderable_element_interface::class, $element);
        $this->assertNotInstanceOf(validatable_element_interface::class, $element);

        // Rendering works through the real renderers.
        $this->assertSame('', (new html_renderer())->render_html($element));
        $pdf = new \pdf();
        $pdf->AddPage();
        (new pdf_renderer())->render_pdf($element, $pdf, true, $USER);

        // Common fields are validated; the element contributes no extra errors and no deprecation.
        $svc = new validation_service();
        $errors = $svc->validate($element, [
            'name' => 'Minimal',
            'width' => -1,
            'posx' => -1,
            'posy' => -1,
            'colour' => 'nope',
        ]);
        $this->assertEqualsCanonicalizing(['width', 'posx', 'posy', 'colour'], array_keys($errors));

        $this->assertSame([], $svc->validate($element, [
            'name' => 'Minimal',
            'width' => 10,
            'posx' => 1,
            'posy' => 1,
            'colour' => '#000000',
        ]));
        $this->assertDebuggingNotCalled();
    }

    /**
     * html_renderer::render_html() delegates to the element, passing itself as the renderer.
     *
     * @covers \mod_customcert\service\html_renderer::render_html
     */
    public function test_html_renderer_delegates_to_element_with_itself(): void {
        $renderer = new html_renderer();
        $element = $this->createMock(renderable_element_interface::class);
        $element->expects($this->once())
            ->method('render_html')
            ->with($this->identicalTo($renderer))
            ->willReturn('<p>delegated</p>');

        $this->assertSame('<p>delegated</p>', $renderer->render_html($element));
    }

    /**
     * The published element_renderer interface keeps its exact method signatures.
     *
     * @covers \mod_customcert\service\element_renderer
     */
    public function test_element_renderer_interface_signatures(): void {
        $expected = [
            'render_pdf' => [
                ['element', renderable_element_interface::class],
                ['pdf', \pdf::class],
                ['preview', 'bool'],
                ['user', stdClass::class],
            ],
            'render_html' => [
                ['element', renderable_element_interface::class],
            ],
            'render_content' => [
                ['element', stylable_element_interface::class],
                ['content', 'string'],
            ],
        ];
        $returns = ['render_pdf' => 'void', 'render_html' => 'string', 'render_content' => null];

        $this->assertEqualsCanonicalizing(
            array_keys($expected),
            array_map(static fn($m) => $m->getName(), (new \ReflectionClass(element_renderer::class))->getMethods())
        );

        foreach ($expected as $method => $params) {
            $ref = new ReflectionMethod(element_renderer::class, $method);
            $actual = array_map(
                static fn($p) => [$p->getName(), $p->getType()?->getName()],
                $ref->getParameters()
            );
            $this->assertSame($params, $actual, "Signature of element_renderer::{$method}() changed");
            $this->assertSame($returns[$method], $ref->getReturnType()?->getName());
        }
    }
}
