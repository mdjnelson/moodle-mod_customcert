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
 * Authorisation regression tests for the template class's require_manage() and
 * require_use_as_source() policies (CVE fix).
 *
 * Verifies that require_manage() is enforced for the destination template and
 * require_use_as_source() for the source template when loading one template's content
 * into another (see load_template.php), preventing a horizontal authorisation bypass
 * where a teacher in Course A could copy a private template from Course B, while still
 * allowing shared system-context templates to be used as a source.
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
use required_capability_exception;

/**
 * Authorisation regression tests for the template class's manage/use-as-source checks.
 *
 * @group mod_customcert
 * @coversDefaultClass \mod_customcert\template
 */
final class template_authorisation_test extends advanced_testcase {
    /**
     * Create a course with a customcert module and return the template object.
     *
     * @return array{course: \stdClass, template: template}
     */
    private function create_course_with_cert(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $templaterecord = $DB->get_record('customcert_templates', ['id' => $customcert->templateid], '*', MUST_EXIST);
        $template = template::from_record($templaterecord);

        return ['course' => $course, 'template' => $template];
    }

    /**
     * A teacher enrolled only in Course A can manage the destination template (tid)
     * but must be denied when require_use_as_source() is called on the source template
     * (ltid) from Course B, which they cannot access.
     *
     * This is the primary regression test for the horizontal authorisation bypass:
     * without the fix, only the destination template was checked, allowing the copy
     * to proceed with an unauthorized source template.
     *
     * @covers ::require_use_as_source
     */
    public function test_require_use_as_source_denies_unauthorized_course_template(): void {
        $this->resetAfterTest();

        // Course A: teacher is enrolled and has manage capability here.
        $a = $this->create_course_with_cert();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $a['course']->id, 'editingteacher');

        // Course B: a separate course/template the teacher has no access to.
        $b = $this->create_course_with_cert();

        $this->setUser($teacher);

        // The destination template (tid) check must pass — teacher manages Course A.
        $a['template']->require_manage();

        // The source template (ltid) check must throw — teacher has no access to Course B.
        $this->expectException(required_capability_exception::class);
        $b['template']->require_use_as_source();
    }

    /**
     * A teacher enrolled in both Course A and Course B can manage both templates,
     * so require_manage() on the destination and require_use_as_source() on the
     * source template must both succeed.
     *
     * This verifies the happy path: a legitimate user authorized for both courses
     * is not incorrectly blocked by the fix.
     *
     * @covers ::require_use_as_source
     */
    public function test_require_use_as_source_allows_manageable_course_template(): void {
        $this->resetAfterTest();

        // Course A: teacher is enrolled.
        $a = $this->create_course_with_cert();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $a['course']->id, 'editingteacher');

        // Course B: teacher is also enrolled here.
        $b = $this->create_course_with_cert();
        $this->getDataGenerator()->enrol_user($teacher->id, $b['course']->id, 'editingteacher');

        $this->setUser($teacher);

        // The destination check and the source-use check must both pass without throwing.
        $a['template']->require_manage();
        $b['template']->require_use_as_source();

        $this->assertTrue(true); // Reached without exception.
    }

    /**
     * A teacher who can manage the destination template but holds no capabilities at the
     * system context must still be able to use a system-context template as a source.
     *
     * System templates are centrally managed, reusable presets: the load-template form
     * lists them to any user regardless of system-level capability, so require_use_as_source()
     * must not require mod/customcert:manage at the system context for them, unlike other
     * course or activity context templates.
     *
     * @covers ::require_use_as_source
     */
    public function test_require_use_as_source_allows_system_template_without_system_capability(): void {
        $this->resetAfterTest();

        // Course A: teacher is enrolled and has manage capability here, but nothing at the
        // system context.
        $a = $this->create_course_with_cert();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $a['course']->id, 'editingteacher');

        // A shared, centrally-managed system-context template.
        $systemtemplate = template::create('System template', context_system::instance()->id);

        $this->setUser($teacher);

        // The destination check must pass — teacher manages Course A.
        $a['template']->require_manage();

        // The source-use check must pass even without mod/customcert:manage at the system
        // context, because the template is a shared system-context preset.
        $systemtemplate->require_use_as_source();

        $this->assertTrue(true); // Reached without exception.
    }
}
