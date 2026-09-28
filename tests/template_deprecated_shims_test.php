<?php
// This file is part of Moodle - http://moodle.org/
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
use context_course;
use context_system;
use dml_missing_record_exception;
use invalid_parameter_exception;
use mod_customcert\event\page_created;
use mod_customcert\event\page_deleted;
use mod_customcert\event\page_updated;
use mod_customcert\event\template_deleted;
use mod_customcert\event\template_updated;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;
use ReflectionClass;
use ReflectionNamedType;
use stdClass;

/**
 * Coverage for the nine deprecated mod_customcert\template compatibility shims (#976).
 *
 * These methods bridge Moodle 4.5 LTS callers through Moodle 5.3 using the released Moodle 5.2
 * declarations as the compatibility contract; each shim must delegate to the current service
 * layer and preserve its ownership/event semantics rather than reimplementing them.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_deprecated_shims_test extends advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Assert the most recent debugging call carries the deprecation shape used by all nine shims.
     *
     * @param string $replacement Fully-qualified replacement API mentioned in the diagnostic.
     */
    private function assert_deprecation_diagnostic(string $replacement): void {
        $messages = $this->getDebuggingMessages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('deprecated since Moodle 5.2', $messages[0]->message);
        $this->assertStringContainsString($replacement, $messages[0]->message);
        $this->assertStringContainsString('Moodle 6.0-compatible release', $messages[0]->message);
        $this->resetDebugging();
    }

    /**
     * Reflect a ReflectionNamedType (or null) into a compact string, e.g. '?int', 'bool', 'void'.
     *
     * @param ReflectionNamedType|null $type
     * @return string
     */
    private function type_to_string(?ReflectionNamedType $type): string {
        if ($type === null) {
            return '';
        }
        return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
    }

    /**
     * All nine restored methods must match the released Moodle 5.2 declarations exactly.
     *
     * @covers \mod_customcert\template::save
     * @covers \mod_customcert\template::add_page
     * @covers \mod_customcert\template::save_page
     * @covers \mod_customcert\template::delete
     * @covers \mod_customcert\template::delete_page
     * @covers \mod_customcert\template::delete_element
     * @covers \mod_customcert\template::generate_pdf
     * @covers \mod_customcert\template::copy_to_template
     * @covers \mod_customcert\template::move_item
     */
    public function test_shim_declarations_match_released_52_surface(): void {
        $expectations = [
            'save' => [
                'params' => [['name' => 'data', 'type' => 'stdClass']],
                'return' => 'void',
            ],
            'add_page' => [
                'params' => [['name' => 'triggertemplateupdatedevent', 'type' => 'bool', 'default' => true]],
                'return' => 'int',
            ],
            'save_page' => [
                'params' => [['name' => 'data', 'type' => 'stdClass']],
                'return' => 'void',
            ],
            'delete' => [
                'params' => [],
                'return' => 'bool',
            ],
            'delete_page' => [
                'params' => [
                    ['name' => 'pageid', 'type' => 'int'],
                    ['name' => 'triggertemplateupdatedevent', 'type' => 'bool', 'default' => true],
                ],
                'return' => 'void',
            ],
            'delete_element' => [
                'params' => [['name' => 'elementid', 'type' => 'int']],
                'return' => 'void',
            ],
            'generate_pdf' => [
                'params' => [
                    ['name' => 'preview', 'type' => 'bool', 'default' => false],
                    ['name' => 'userid', 'type' => '?int', 'default' => null],
                    ['name' => 'return', 'type' => 'bool', 'default' => false],
                ],
                // The released 5.2 surface declared no return type for generate_pdf().
                'return' => null,
            ],
            'copy_to_template' => [
                'params' => [['name' => 'copytotemplate', 'type' => template::class]],
                'return' => 'void',
            ],
            'move_item' => [
                'params' => [
                    ['name' => 'itemname', 'type' => 'string'],
                    ['name' => 'itemid', 'type' => 'int'],
                    ['name' => 'direction', 'type' => 'string'],
                ],
                'return' => 'void',
            ],
        ];

        $class = new ReflectionClass(template::class);

        foreach ($expectations as $methodname => $expected) {
            $this->assertTrue($class->hasMethod($methodname), "Missing restored method: {$methodname}");
            $method = $class->getMethod($methodname);

            $this->assertTrue($method->isPublic(), "{$methodname}() must be public");
            $this->assertFalse($method->isStatic(), "{$methodname}() must not be static");

            $params = $method->getParameters();
            $this->assertCount(count($expected['params']), $params, "{$methodname}() parameter count mismatch");

            foreach ($expected['params'] as $i => $expectedparam) {
                $param = $params[$i];
                $this->assertSame(
                    $expectedparam['name'],
                    $param->getName(),
                    "{$methodname}() parameter {$i} name mismatch"
                );
                $this->assertSame(
                    $expectedparam['type'],
                    $this->type_to_string($param->getType()),
                    "{$methodname}() parameter '{$param->getName()}' type mismatch"
                );

                if (array_key_exists('default', $expectedparam)) {
                    $this->assertTrue(
                        $param->isDefaultValueAvailable(),
                        "{$methodname}() parameter '{$param->getName()}' must have a default"
                    );
                    $this->assertSame(
                        $expectedparam['default'],
                        $param->getDefaultValue(),
                        "{$methodname}() parameter '{$param->getName()}' default mismatch"
                    );
                } else {
                    $this->assertFalse(
                        $param->isDefaultValueAvailable(),
                        "{$methodname}() parameter '{$param->getName()}' must not have a default"
                    );
                }
            }

            $returntype = $method->getReturnType();
            if ($expected['return'] === null) {
                $this->assertNull($returntype, "{$methodname}() must not declare a return type");
            } else {
                $this->assertSame(
                    $expected['return'],
                    $this->type_to_string($returntype),
                    "{$methodname}() return type mismatch"
                );
            }
        }
    }

    /**
     * save() should persist and rename via the service, firing template_updated for non-system contexts.
     *
     * @covers \mod_customcert\template::save
     */
    public function test_save_renames_template_via_service(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $template = template::create('Original', context_course::instance($course->id)->id);

        $sink = $this->redirectEvents();
        $template->save((object) ['name' => 'Renamed']);
        $events = $sink->get_events();

        $this->assertSame('Renamed', $template->get_name());
        $record = $DB->get_record('customcert_templates', ['id' => $template->get_id()], '*', MUST_EXIST);
        $this->assertSame('Renamed', $record->name);

        $this->assertCount(1, $events);
        $this->assertInstanceOf(template_updated::class, $events[0]);

        $this->assert_deprecation_diagnostic('template_service::update()');
    }

    /**
     * add_page() should return the new page id and preserve the default template_updated event flag.
     *
     * @covers \mod_customcert\template::add_page
     */
    public function test_add_page_returns_id_and_preserves_default_event(): void {
        global $DB;

        $template = template::create('Add page', context_system::instance()->id);

        $sink = $this->redirectEvents();
        $pageid = $template->add_page();
        $events = $sink->get_events();

        $this->assertIsInt($pageid);
        $page = $DB->get_record('customcert_pages', ['id' => $pageid], '*', MUST_EXIST);
        $this->assertSame($template->get_id(), (int) $page->templateid);
        $this->assertSame(1, (int) $page->sequence);

        $names = array_map(fn ($event) => get_class($event), $events);
        $this->assertContains(page_created::class, $names);
        $this->assertContains(template_updated::class, $names);

        $this->assert_deprecation_diagnostic('template_service::add_page()');
    }

    /**
     * add_page(false) should still create the page but suppress the template_updated event.
     *
     * @covers \mod_customcert\template::add_page
     */
    public function test_add_page_false_suppresses_template_updated_event(): void {
        global $DB;

        $template = template::create('Add page suppressed', context_system::instance()->id);

        $sink = $this->redirectEvents();
        $pageid = $template->add_page(false);
        $events = $sink->get_events();

        $this->assertTrue($DB->record_exists('customcert_pages', ['id' => $pageid]));

        $names = array_map(fn ($event) => get_class($event), $events);
        $this->assertContains(page_created::class, $names);
        $this->assertNotContains(template_updated::class, $names);

        $this->assert_deprecation_diagnostic('template_service::add_page()');
    }

    /**
     * save_page() should persist page layout changes without forcing a template_updated event,
     * matching the released 5.2 delegation to template_service::save_pages() with its default flag.
     *
     * @covers \mod_customcert\template::save_page
     */
    public function test_save_page_persists_layout_without_template_updated_event(): void {
        global $DB;

        $template = template::create('Save page', context_system::instance()->id);
        $pageid = template_service::create()->add_page($template);

        $data = (object) [
            'pagewidth_' . $pageid => 150,
            'pageheight_' . $pageid => 100,
            'pageleftmargin_' . $pageid => 5,
            'pagerightmargin_' . $pageid => 5,
        ];

        $sink = $this->redirectEvents();
        $template->save_page($data);
        $events = $sink->get_events();

        $page = $DB->get_record('customcert_pages', ['id' => $pageid], '*', MUST_EXIST);
        $this->assertSame(150, (int) $page->width);
        $this->assertSame(100, (int) $page->height);
        $this->assertSame(5, (int) $page->leftmargin);
        $this->assertSame(5, (int) $page->rightmargin);

        $names = array_map(fn ($event) => get_class($event), $events);
        $this->assertContains(page_updated::class, $names);
        $this->assertNotContains(template_updated::class, $names);

        $this->assert_deprecation_diagnostic('template_service::save_pages()');
    }

    /**
     * delete() should return true and cascade through the service, removing pages and elements.
     *
     * @covers \mod_customcert\template::delete
     */
    public function test_delete_returns_true_and_cascades(): void {
        global $DB;

        $template = template::create('Delete', context_system::instance()->id);
        $service = template_service::create();
        $pageid = $service->add_page($template);
        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'E',
            'element' => 'text',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $sink = $this->redirectEvents();
        $result = $template->delete();
        $events = $sink->get_events();

        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('customcert_templates', ['id' => $template->get_id()]));
        $this->assertFalse($DB->record_exists('customcert_pages', ['id' => $pageid]));
        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $elementid]));

        $names = array_map(fn ($event) => get_class($event), $events);
        $this->assertContains(page_deleted::class, $names);
        $this->assertContains(template_deleted::class, $names);

        $this->assert_deprecation_diagnostic('template_service::delete()');
    }

    /**
     * delete_page() must delete a page belonging to the template.
     *
     * @covers \mod_customcert\template::delete_page
     */
    public function test_delete_page_same_template_succeeds(): void {
        global $DB;

        $template = template::create('Delete page', context_system::instance()->id);
        $pageid = template_service::create()->add_page($template);

        $template->delete_page($pageid);

        $this->assertFalse($DB->record_exists('customcert_pages', ['id' => $pageid]));
        $this->assert_deprecation_diagnostic('template_service::delete_page()');
    }

    /**
     * delete_page() must reject a page belonging to a different template; the current ownership
     * check must not be bypassed by the shim.
     *
     * @covers \mod_customcert\template::delete_page
     */
    public function test_delete_page_rejects_foreign_page(): void {
        global $DB;

        $templatea = template::create('Delete page owner A', context_system::instance()->id);
        $templateb = template::create('Delete page owner B', context_system::instance()->id);
        $service = template_service::create();
        $pageb = $service->add_page($templateb);

        try {
            $templatea->delete_page($pageb);
            $this->fail('Expected invalid_parameter_exception for a cross-template delete_page() call.');
        } catch (invalid_parameter_exception $e) {
            $this->assertStringContainsString('does not belong to template', $e->getMessage());
        }

        $this->assertTrue($DB->record_exists('customcert_pages', ['id' => $pageb]));
        $this->assert_deprecation_diagnostic('template_service::delete_page()');
    }

    /**
     * delete_element() must delete an element belonging to the template.
     *
     * @covers \mod_customcert\template::delete_element
     */
    public function test_delete_element_same_template_succeeds(): void {
        global $DB;

        $template = template::create('Delete element', context_system::instance()->id);
        $pageid = template_service::create()->add_page($template);
        $elementid = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name' => 'E',
            'element' => 'text',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $template->delete_element($elementid);

        $this->assertFalse($DB->record_exists('customcert_elements', ['id' => $elementid]));
        $this->assert_deprecation_diagnostic('template_service::delete_element()');
    }

    /**
     * delete_element() must reject an element belonging to a different template; the current
     * ownership check must not be bypassed by the shim.
     *
     * @covers \mod_customcert\template::delete_element
     */
    public function test_delete_element_rejects_foreign_element(): void {
        global $DB;

        $templatea = template::create('Delete element owner A', context_system::instance()->id);
        $templateb = template::create('Delete element owner B', context_system::instance()->id);
        $service = template_service::create();
        $pageb = $service->add_page($templateb);
        $elementb = $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageb,
            'name' => 'E',
            'element' => 'text',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        try {
            $templatea->delete_element($elementb);
            $this->fail('Expected invalid_parameter_exception for a cross-template delete_element() call.');
        } catch (invalid_parameter_exception $e) {
            $this->assertStringContainsString('does not belong to template', $e->getMessage());
        }

        $this->assertTrue($DB->record_exists('customcert_elements', ['id' => $elementb]));
        $this->assert_deprecation_diagnostic('template_service::delete_element()');
    }

    /**
     * generate_pdf(..., $return = true) must return non-empty PDF bytes from the current PDF service.
     *
     * @covers \mod_customcert\template::generate_pdf
     */
    public function test_generate_pdf_returns_pdf_bytes(): void {
        global $DB, $USER;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = template::from_record((new template_repository())->get_by_id_or_fail((int) $customcert->templateid));

        $page = $DB->get_record('customcert_pages', ['templateid' => $template->get_id()], '*', MUST_EXIST);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $page->id,
            'element' => 'text',
            'name' => 'Sample',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'data' => '',
        ]);

        $pdfstring = $template->generate_pdf(true, (int) $USER->id, true);

        $this->assertIsString($pdfstring);
        $this->assertNotEmpty($pdfstring);
        $this->assertStringStartsWith('%PDF', $pdfstring);

        $this->assert_deprecation_diagnostic('pdf_generation_service::generate_pdf()');
    }

    /**
     * copy_to_template() must copy pages/elements from the source ($this) into the target,
     * leaving the source intact.
     *
     * @covers \mod_customcert\template::copy_to_template
     */
    public function test_copy_to_template_copies_pages_and_elements(): void {
        global $DB;

        $source = template::create('Source', context_system::instance()->id);
        $target = template::create('Target', context_system::instance()->id);
        $service = template_service::create();

        $sourcepageid = $service->add_page($source);
        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $sourcepageid,
            'name' => 'E',
            'element' => 'text',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $source->copy_to_template($target);

        // Source remains intact.
        $this->assertTrue($DB->record_exists('customcert_pages', ['id' => $sourcepageid]));
        $this->assertTrue($DB->record_exists('customcert_elements', ['pageid' => $sourcepageid]));

        $targetpages = $DB->get_records('customcert_pages', ['templateid' => $target->get_id()]);
        $this->assertCount(1, $targetpages);
        $targetpage = reset($targetpages);
        $this->assertTrue($DB->record_exists('customcert_elements', ['pageid' => $targetpage->id]));

        $this->assert_deprecation_diagnostic('template_service::copy_to_template()');
    }

    /**
     * move_item() must reorder pages belonging to the template.
     *
     * @covers \mod_customcert\template::move_item
     */
    public function test_move_item_reorders_pages(): void {
        global $DB;

        $template = template::create('Move item', context_system::instance()->id);
        $service = template_service::create();
        $first = $service->add_page($template);
        $second = $service->add_page($template);

        $template->move_item('page', $second, 'up');

        $pages = $DB->get_records('customcert_pages', ['templateid' => $template->get_id()], 'sequence ASC');
        $this->assertSame([$second, $first], array_keys($pages));

        $this->assert_deprecation_diagnostic('template_service::move_item()');
    }

    /**
     * move_item() must reject a page belonging to a different template; the current ownership
     * check must not be bypassed by the shim.
     *
     * @covers \mod_customcert\template::move_item
     */
    public function test_move_item_rejects_foreign_page(): void {
        $templatea = template::create('Move item owner A', context_system::instance()->id);
        $templateb = template::create('Move item owner B', context_system::instance()->id);
        $service = template_service::create();
        $pageb = $service->add_page($templateb);

        try {
            $templatea->move_item('page', $pageb, 'up');
            $this->fail('Expected dml_missing_record_exception for a cross-template move_item() call.');
        } catch (dml_missing_record_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->assert_deprecation_diagnostic('template_service::move_item()');
    }
}
