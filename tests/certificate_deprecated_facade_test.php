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
use context_module;
use context_system;
use context_user;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\certificate_repository;
use mod_customcert\service\issue_repository;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionNamedType;
use stdClass;

/**
 * Coverage for the deprecated mod_customcert\certificate compatibility facade (#975).
 *
 * These 14 methods and six constants bridge Moodle 4.5 LTS callers through Moodle 5.3 using the
 * released Moodle 5.2 declarations as the compatibility contract; each must delegate to the
 * current service/repository layer rather than reimplementing its behaviour, and the historical
 * $groupmode argument must not be trusted as an authorization source.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class certificate_deprecated_facade_test extends advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Assert the most recent debugging call carries the deprecation shape used by all 14 methods.
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
     * class_exists() and all six qualified constants resolve with their released-5.2 values and
     * declared types.
     *
     * @covers \mod_customcert\certificate
     */
    public function test_class_and_constants_resolve(): void {
        $this->assertTrue(class_exists(certificate::class));

        $expected = [
            'DELIVERY_OPTION_INLINE' => ['value' => 'I', 'type' => 'string'],
            'DELIVERY_OPTION_DOWNLOAD' => ['value' => 'D', 'type' => 'string'],
            'PROTECTION_PRINT' => ['value' => 'print', 'type' => 'string'],
            'PROTECTION_MODIFY' => ['value' => 'modify', 'type' => 'string'],
            'PROTECTION_COPY' => ['value' => 'copy', 'type' => 'string'],
            'CUSTOMCERT_PER_PAGE' => ['value' => 50, 'type' => 'int'],
        ];

        $class = new ReflectionClass(certificate::class);

        foreach ($expected as $name => $spec) {
            $this->assertTrue($class->hasConstant($name), "Missing restored constant: {$name}");
            $this->assertSame($spec['value'], $class->getConstant($name), "{$name} value mismatch");

            $rc = new ReflectionClassConstant(certificate::class, $name);
            $this->assertTrue($rc->hasType(), "{$name} must be a typed class constant");
            $this->assertSame($spec['type'], $rc->getType()->getName(), "{$name} declared type mismatch");
        }
    }

    /**
     * All 14 restored static methods must match the released Moodle 5.2 declarations exactly,
     * including the historical (non-normalised) $groupmode argument positions.
     *
     * @covers \mod_customcert\certificate::set_protection
     * @covers \mod_customcert\certificate::upload_files
     * @covers \mod_customcert\certificate::get_fonts
     * @covers \mod_customcert\certificate::get_font_sizes
     * @covers \mod_customcert\certificate::get_course_time
     * @covers \mod_customcert\certificate::download_all_issues_for_instance
     * @covers \mod_customcert\certificate::download_all_for_site
     * @covers \mod_customcert\certificate::get_issues
     * @covers \mod_customcert\certificate::get_number_of_issues
     * @covers \mod_customcert\certificate::get_conditional_issues_sql
     * @covers \mod_customcert\certificate::get_number_of_certificates_for_user
     * @covers \mod_customcert\certificate::get_certificates_for_user
     * @covers \mod_customcert\certificate::issue_certificate
     * @covers \mod_customcert\certificate::generate_code
     */
    public function test_declarations_match_released_52_surface(): void {
        $expectations = [
            'set_protection' => [
                'params' => [['name' => 'data', 'type' => stdClass::class]],
                'return' => 'string',
            ],
            'upload_files' => [
                'params' => [
                    ['name' => 'draftitemid', 'type' => 'int'],
                    ['name' => 'contextid', 'type' => 'int'],
                    ['name' => 'filearea', 'type' => 'string', 'default' => 'image'],
                ],
                'return' => 'void',
            ],
            'get_fonts' => ['params' => [], 'return' => 'array'],
            'get_font_sizes' => ['params' => [], 'return' => 'array'],
            'get_course_time' => [
                'params' => [
                    ['name' => 'courseid', 'type' => 'int'],
                    ['name' => 'userid', 'type' => 'int', 'default' => 0],
                ],
                'return' => 'int',
            ],
            'download_all_issues_for_instance' => [
                'params' => [
                    ['name' => 'template', 'type' => template::class],
                    ['name' => 'issues', 'type' => 'array'],
                ],
                'return' => 'void',
            ],
            'download_all_for_site' => ['params' => [], 'return' => 'void'],
            'get_issues' => [
                'params' => [
                    ['name' => 'customcertid', 'type' => 'int'],
                    ['name' => 'groupmode', 'type' => 'int'],
                    ['name' => 'cm', 'type' => stdClass::class],
                    ['name' => 'limitfrom', 'type' => 'int'],
                    ['name' => 'limitnum', 'type' => 'int'],
                    ['name' => 'sort', 'type' => 'string', 'default' => ''],
                ],
                'return' => 'array',
            ],
            'get_number_of_issues' => [
                'params' => [
                    ['name' => 'customcertid', 'type' => 'int'],
                    ['name' => 'cm', 'type' => stdClass::class],
                    ['name' => 'groupmode', 'type' => 'int'],
                ],
                'return' => 'int',
            ],
            'get_conditional_issues_sql' => [
                'params' => [
                    ['name' => 'cm', 'type' => stdClass::class],
                    ['name' => 'groupmode', 'type' => 'int'],
                ],
                'return' => 'array',
            ],
            'get_number_of_certificates_for_user' => [
                'params' => [['name' => 'userid', 'type' => 'int']],
                'return' => 'int',
            ],
            'get_certificates_for_user' => [
                'params' => [
                    ['name' => 'userid', 'type' => 'int'],
                    ['name' => 'limitfrom', 'type' => 'int'],
                    ['name' => 'limitnum', 'type' => 'int'],
                    ['name' => 'sort', 'type' => 'string', 'default' => ''],
                ],
                'return' => 'array',
            ],
            'issue_certificate' => [
                'params' => [
                    ['name' => 'certificateid', 'type' => 'int'],
                    ['name' => 'userid', 'type' => 'int'],
                ],
                'return' => 'int',
            ],
            'generate_code' => ['params' => [], 'return' => 'string'],
        ];

        $class = new ReflectionClass(certificate::class);

        foreach ($expectations as $methodname => $expected) {
            $this->assertTrue($class->hasMethod($methodname), "Missing restored method: {$methodname}");
            $method = $class->getMethod($methodname);

            $this->assertTrue($method->isPublic(), "{$methodname}() must be public");
            $this->assertTrue($method->isStatic(), "{$methodname}() must be static");

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

            $this->assertSame(
                $expected['return'],
                $this->type_to_string($method->getReturnType()),
                "{$methodname}() return type mismatch"
            );
        }
    }

    /**
     * set_protection() matches form_service for representative protection combinations.
     *
     * @covers \mod_customcert\certificate::set_protection
     */
    public function test_set_protection_delegates(): void {
        $combos = [
            (object) [],
            (object) ['protection_print' => 1],
            (object) ['protection_modify' => 1],
            (object) ['protection_copy' => 1],
            (object) ['protection_print' => 1, 'protection_modify' => 1, 'protection_copy' => 1],
        ];

        foreach ($combos as $data) {
            $expected = \mod_customcert\service\form_service::set_protection($data);
            $actual = certificate::set_protection($data);
            $this->assertSame($expected, $actual);
            $this->assert_deprecation_diagnostic('form_service::set_protection()');
        }
    }

    /**
     * upload_files() retains the released 'image' default and forwards an explicit filearea.
     *
     * @covers \mod_customcert\certificate::upload_files
     */
    public function test_upload_files_default_and_explicit_filearea(): void {
        $this->setAdminUser();
        global $USER;
        $usercontext = context_user::instance($USER->id);
        $syscontext = context_system::instance();
        $fs = get_file_storage();

        // Default filearea forwards to 'image'.
        $draftitemid = file_get_unused_draft_itemid();
        $fs->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'default.png',
        ], 'contents');

        certificate::upload_files($draftitemid, $syscontext->id);
        $this->assert_deprecation_diagnostic('form_service::upload_files()');

        $files = $fs->get_area_files($syscontext->id, 'mod_customcert', 'image', 0, 'filename', false);
        $this->assertCount(1, $files);
        $this->assertSame('default.png', reset($files)->get_filename());

        // Explicit filearea is forwarded unchanged.
        $draftitemid = file_get_unused_draft_itemid();
        $fs->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'signature.png',
        ], 'contents');

        certificate::upload_files($draftitemid, $syscontext->id, 'signature');
        $this->assert_deprecation_diagnostic('form_service::upload_files()');

        $files = $fs->get_area_files($syscontext->id, 'mod_customcert', 'signature', 0, 'filename', false);
        $this->assertCount(1, $files);
        $this->assertSame('signature.png', reset($files)->get_filename());
    }

    /**
     * get_fonts()/get_font_sizes() return exactly what element_helper returns.
     *
     * @covers \mod_customcert\certificate::get_fonts
     * @covers \mod_customcert\certificate::get_font_sizes
     */
    public function test_font_helpers_delegate(): void {
        $this->assertSame(element_helper::get_fonts(), certificate::get_fonts());
        $this->assert_deprecation_diagnostic('element_helper::get_fonts()');

        $sizes = certificate::get_font_sizes();
        $this->assertSame(element_helper::get_font_sizes(), $sizes);
        $this->assert_deprecation_diagnostic('element_helper::get_font_sizes()');

        $this->assertSame(1, $sizes[1]);
        $this->assertSame(200, $sizes[200]);
    }

    /**
     * get_course_time() forwards the compatibility default (userid = 0) and an explicit userid.
     *
     * @covers \mod_customcert\certificate::get_course_time
     */
    public function test_get_course_time_forwards_default_and_explicit_user(): void {
        $this->setAdminUser();
        global $USER;

        $course = $this->getDataGenerator()->create_course();

        // No log store is enabled, so the current service returns 0 either way; this proves the
        // facade forwards both the implicit-current-user default and an explicit userid rather
        // than duplicating the time-calculation algorithm.
        $this->assertSame(0, certificate::get_course_time((int) $course->id));
        $this->assert_deprecation_diagnostic('certificate_time_service::get_course_time()');

        $this->assertSame(0, certificate::get_course_time((int) $course->id, (int) $USER->id));
        $this->assert_deprecation_diagnostic('certificate_time_service::get_course_time()');
    }

    /**
     * get_issues() honours paging/sorting and returns the current repository row shape.
     *
     * @covers \mod_customcert\certificate::get_issues
     */
    public function test_get_issues_paging_sorting_and_row_shape(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id);

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Aaa']);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Bbb']);

        $repo = new issue_repository();
        $repo->create((int) $customcert->id, (int) $user1->id);
        $repo->create((int) $customcert->id, (int) $user2->id);

        // Legacy groupmode is irrelevant here (an unrestricted course), but callable at its
        // historical position.
        $issues = certificate::get_issues((int) $customcert->id, 0, $cm, 0, 1, 'u.firstname ASC');
        $this->assert_deprecation_diagnostic('issue_repository::get_issues()');
        $this->assertCount(1, $issues);
        $row = reset($issues);
        $this->assertObjectHasProperty('issueid', $row);
        $this->assertObjectHasProperty('code', $row);
        $this->assertObjectHasProperty('timecreated', $row);
        $this->assertSame('Aaa', $row->firstname);

        $issues = certificate::get_issues((int) $customcert->id, 0, $cm, 1, 1, 'u.firstname ASC');
        $this->assert_deprecation_diagnostic('issue_repository::get_issues()');
        $this->assertCount(1, $issues);
        $this->assertSame('Bbb', reset($issues)->firstname);

        // Empty sort follows the current repository default (full-name ordering).
        $default = certificate::get_issues((int) $customcert->id, 0, $cm, 0, 0);
        $this->assert_deprecation_diagnostic('issue_repository::get_issues()');
        $this->assertEquals(
            $repo->get_issues((int) $customcert->id, $cm, 0, 0),
            $default
        );
    }

    /**
     * A falsy legacy $groupmode argument cannot bypass current group/capability restrictions:
     * for a viewer without moodle/site:accessallgroups and no selected group, results stay
     * empty regardless of what the caller claims.
     *
     * @covers \mod_customcert\certificate::get_issues
     * @covers \mod_customcert\certificate::get_number_of_issues
     * @covers \mod_customcert\certificate::get_conditional_issues_sql
     */
    public function test_group_mode_mismatch_cannot_bypass_current_restrictions(): void {
        $course = $this->getDataGenerator()->create_course([
            'groupmode' => SEPARATEGROUPS,
            'groupmodeforce' => 1,
        ]);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id);

        $student = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student');

        $repo = new issue_repository();
        $repo->create((int) $customcert->id, (int) $student->id);
        $repo->create((int) $customcert->id, (int) $other->id);

        $this->setUser($student);

        $directcount = $repo->get_number_of_issues((int) $customcert->id, $cm);
        $this->assertSame(0, $directcount, 'Baseline: an unresolved current-group selection hides all issues.');

        foreach ([0, 1, SEPARATEGROUPS] as $claimedgroupmode) {
            $this->assertSame(
                $directcount,
                certificate::get_number_of_issues((int) $customcert->id, $cm, $claimedgroupmode)
            );
            $this->assert_deprecation_diagnostic('issue_repository::get_number_of_issues()');

            $this->assertSame(
                $repo->get_issues((int) $customcert->id, $cm, 0, 0),
                certificate::get_issues((int) $customcert->id, $claimedgroupmode, $cm, 0, 0)
            );
            $this->assert_deprecation_diagnostic('issue_repository::get_issues()');

            $this->assertSame(
                $repo->get_conditional_issues_sql($cm),
                certificate::get_conditional_issues_sql($cm, $claimedgroupmode)
            );
            $this->assert_deprecation_diagnostic('issue_repository::get_conditional_issues_sql()');
        }
    }

    /**
     * Inverse mismatch: when the current CM/capabilities do not restrict visibility (viewer can
     * access all groups), a legacy caller falsely claiming a restrictive groupmode still gets the
     * current, unrestricted result rather than a compatibility-driven restriction.
     *
     * @covers \mod_customcert\certificate::get_issues
     * @covers \mod_customcert\certificate::get_number_of_issues
     */
    public function test_group_mode_argument_ignored_when_viewer_can_access_all_groups(): void {
        $course = $this->getDataGenerator()->create_course([
            'groupmode' => SEPARATEGROUPS,
            'groupmodeforce' => 1,
        ]);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id);

        $student = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student');

        $repo = new issue_repository();
        $repo->create((int) $customcert->id, (int) $student->id);
        $repo->create((int) $customcert->id, (int) $other->id);

        $this->setAdminUser();

        $directcount = $repo->get_number_of_issues((int) $customcert->id, $cm);
        $this->assertSame(2, $directcount, 'Baseline: an admin can access all groups, so both issues are visible.');

        foreach ([0, 1, SEPARATEGROUPS] as $claimedgroupmode) {
            $this->assertSame(
                $directcount,
                certificate::get_number_of_issues((int) $customcert->id, $cm, $claimedgroupmode)
            );
            $this->assert_deprecation_diagnostic('issue_repository::get_number_of_issues()');
        }
    }

    /**
     * User certificate count/list delegate to certificate_repository, including pagination and
     * the current default sort when $sort is omitted.
     *
     * @covers \mod_customcert\certificate::get_number_of_certificates_for_user
     * @covers \mod_customcert\certificate::get_certificates_for_user
     */
    public function test_user_certificate_methods_delegate(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert1 = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $customcert2 = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        $issuerepo = new issue_repository();
        $certrepo = new certificate_repository();

        $this->assertSame(0, certificate::get_number_of_certificates_for_user((int) $user->id));
        $this->assert_deprecation_diagnostic('certificate_repository::get_number_of_certificates_for_user()');

        $issuerepo->create((int) $customcert1->id, (int) $user->id);
        $issuerepo->create((int) $customcert2->id, (int) $user->id);
        $issuerepo->create((int) $customcert1->id, (int) $otheruser->id);

        $this->assertSame(2, certificate::get_number_of_certificates_for_user((int) $user->id));
        $this->assert_deprecation_diagnostic('certificate_repository::get_number_of_certificates_for_user()');

        // Pagination.
        $page = certificate::get_certificates_for_user((int) $user->id, 0, 1);
        $this->assert_deprecation_diagnostic('certificate_repository::get_certificates_for_user()');
        $this->assertCount(1, $page);

        // Explicit sort is forwarded.
        $expectedsorted = $certrepo->get_certificates_for_user((int) $user->id, 0, 0, 'c.id ASC');
        $actualsorted = certificate::get_certificates_for_user((int) $user->id, 0, 0, 'c.id ASC');
        $this->assert_deprecation_diagnostic('certificate_repository::get_certificates_for_user()');
        $this->assertEquals($expectedsorted, $actualsorted);

        // Empty sort follows the current repository default.
        $expecteddefault = $certrepo->get_certificates_for_user((int) $user->id, 0, 0);
        $actualdefault = certificate::get_certificates_for_user((int) $user->id, 0, 0);
        $this->assert_deprecation_diagnostic('certificate_repository::get_certificates_for_user()');
        $this->assertEquals($expecteddefault, $actualdefault);

        // Row shape/isolation.
        $row = reset($actualdefault);
        $this->assertObjectHasProperty('code', $row);
        $this->assertObjectHasProperty('timecreated', $row);
        $this->assertObjectHasProperty('coursename', $row);
        $this->assertCount(1, certificate::get_certificates_for_user((int) $otheruser->id, 0, 0));
        $this->resetDebugging();
    }

    /**
     * issue_certificate() returns the inserted issue id and preserves current service semantics:
     * emailed = 0, studentemailed = 0, a populated code, and a single issue_created event.
     *
     * @covers \mod_customcert\certificate::issue_certificate
     */
    public function test_issue_certificate_preserves_current_semantics(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEvents();
        $issueid = certificate::issue_certificate((int) $customcert->id, (int) $user->id);
        $events = $sink->get_events();
        $sink->close();
        $this->assert_deprecation_diagnostic('certificate_issue_service::issue_certificate()');

        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);
        $this->assertSame((int) $customcert->id, (int) $issue->customcertid);
        $this->assertSame((int) $user->id, (int) $issue->userid);
        $this->assertSame(0, (int) $issue->emailed);
        $this->assertSame(0, (int) $issue->studentemailed);
        $this->assertNotEmpty($issue->code);

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_customcert\event\issue_created::class, $events[0]);
        $this->assertSame($issueid, $events[0]->objectid);
        $this->assertSame((int) $user->id, (int) $events[0]->relateduserid);
        $this->assertSame(context_module::instance($customcert->cmid)->id, $events[0]->get_context()->id);
    }

    /**
     * generate_code() forwards to the current service and returns a service-generated string for
     * both supported code-generation settings.
     *
     * @covers \mod_customcert\certificate::generate_code
     */
    public function test_generate_code_forwards_to_service(): void {
        set_config('codegenerationmethod', '0', 'customcert');
        $code = certificate::generate_code();
        $this->assert_deprecation_diagnostic('certificate_issue_service::generate_code()');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{10}$/', $code);

        set_config('codegenerationmethod', '1', 'customcert');
        $code = certificate::generate_code();
        $this->assert_deprecation_diagnostic('certificate_issue_service::generate_code()');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}-\d{4}$/', $code);
    }

    /**
     * download_all_for_site() deprecates and delegates to certificate_download_service even
     * though the service path terminates via send_file() when there is something to send.
     *
     * With no certificates on the site the service returns before reaching send_file()/exit(),
     * which lets this test exercise the facade's deprecation/delegation without a process-ending
     * call. Terminal archive/send behaviour remains covered by
     * tests/certificate_download_service_test.php, which injects a stub sendfile callable
     * directly into the service.
     *
     * @covers \mod_customcert\certificate::download_all_for_site
     */
    public function test_download_all_for_site_deprecates_and_delegates_on_empty_site(): void {
        certificate::download_all_for_site();
        $this->assert_deprecation_diagnostic('certificate_download_service::download_all_for_site()');
    }
}
