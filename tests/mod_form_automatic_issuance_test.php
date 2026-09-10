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

/**
 * Tests that mod/customcert:manageautomaticissuance gates the "Issue certificates
 * automatically" setting, independently of the manageemail* capabilities.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use context_course;
use mod_customcert\callback\instance_callbacks;
use MoodleQuickForm;
use ReflectionClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/customcert/mod_form.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Tests that mod/customcert:manageautomaticissuance gates the "Issue certificates
 * automatically" setting, independently of the manageemail* capabilities.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_form_automatic_issuance_test extends advanced_testcase {
    /**
     * Build a mod_customcert_mod_form with the given context, skipping the constructor (which
     * would otherwise eagerly build the full form and require a real course module context).
     *
     * @param \context $context
     * @param object $current Value for $this->current, e.g. (object)['add' => 'customcert'] for
     *   a new instance.
     * @return \mod_customcert_mod_form
     */
    private function make_form(\context $context, object $current): \mod_customcert_mod_form {
        $refclass = new ReflectionClass(\mod_customcert_mod_form::class);
        $form = $refclass->newInstanceWithoutConstructor();

        $formprop = $refclass->getProperty('_form');
        $formprop->setAccessible(true);
        $formprop->setValue($form, new MoodleQuickForm('mod_form_automatic_issuance_test', 'post', '#'));

        $contextprop = $refclass->getProperty('context');
        $contextprop->setAccessible(true);
        $contextprop->setValue($form, $context);

        $currentprop = $refclass->getProperty('current');
        $currentprop->setAccessible(true);
        $currentprop->setValue($form, $current);

        return $form;
    }

    /**
     * Create a course, a user with a fresh role in it, and return [user, context, roleid].
     *
     * @return array{0: \stdClass, 1: \context_course, 2: int}
     */
    private function create_user_with_role(): array {
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();

        $roleid = create_role('Certificate manager', 'certmanager', 'Test role');
        role_assign($roleid, $user->id, $context->id);

        return [$user, $context, $roleid];
    }

    /**
     * The capability-to-field mapping used to fill in defaults on new instances (when the
     * current user lacks the mapped capability) must include issueautomatically. This mapping
     * plays no part in preserving the value on update -- that happens because the checkbox is
     * simply never registered on the form for a capability-less user, so it is absent from
     * submitted data and update_instance() never writes over the stored value (see the
     * test_update_instance_* tests below).
     *
     * @covers \mod_customcert_mod_form::get_options_elements_with_required_caps
     */
    public function test_options_elements_with_required_caps_includes_issueautomatically(): void {
        $this->resetAfterTest();

        $refclass = new ReflectionClass(\mod_customcert_mod_form::class);
        $form = $refclass->newInstanceWithoutConstructor();
        $method = $refclass->getMethod('get_options_elements_with_required_caps');
        $method->setAccessible(true);

        $map = $method->invoke($form);

        $this->assertArrayHasKey('issueautomatically', $map);
        $this->assertSame('mod/customcert:manageautomaticissuance', $map['issueautomatically']);
    }

    /**
     * A user without manageautomaticissuance never has the field in their submitted data (the
     * mform never registers it for them), so on a new instance it falls back to the default.
     * This is the same mechanism that already protects emailstudents/emailteachers/emailothers.
     *
     * @covers \mod_customcert_mod_form::data_postprocessing
     */
    public function test_new_instance_defaults_issueautomatically_without_capability(): void {
        $this->resetAfterTest();

        [$user, $context, $roleid] = $this->create_user_with_role();
        assign_capability('mod/customcert:manageautomaticissuance', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $form = $this->make_form($context, (object)['add' => 'customcert']);

        // No issueautomatically key: the field was never registered for this user, so it can
        // never appear in real submitted data, regardless of what a crafted POST contains.
        $data = (object)['add' => 1];
        $form->data_postprocessing($data);

        $this->assertTrue(empty($data->issueautomatically));
    }

    /**
     * A user with manageautomaticissuance submits the field normally, and their value passes
     * through data_postprocessing() untouched.
     *
     * @covers \mod_customcert_mod_form::data_postprocessing
     */
    public function test_new_instance_preserves_submitted_issueautomatically_with_capability(): void {
        $this->resetAfterTest();

        [$user, $context, $roleid] = $this->create_user_with_role();
        assign_capability('mod/customcert:manageautomaticissuance', CAP_ALLOW, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $form = $this->make_form($context, (object)['add' => 'customcert']);

        $data = (object)['add' => 1, 'issueautomatically' => 1];
        $form->data_postprocessing($data);

        $this->assertEquals(1, $data->issueautomatically);
    }

    /**
     * Editing an existing certificate as a user without manageautomaticissuance must not reset
     * issueautomatically: since the field was never in their submitted data, update_instance()
     * only ever writes the columns actually present on $data, leaving the stored value alone.
     *
     * @covers \mod_customcert\callback\instance_callbacks::update_instance
     */
    public function test_update_instance_preserves_issueautomatically_when_absent_from_submission(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'issueautomatically' => 1,
        ]);

        // Simulate the submission of a user without manageautomaticissuance: the checkbox was
        // never added to their form, so issueautomatically is simply absent, even though they
        // are legitimately editing some other setting (here, the name).
        $data = (object)[
            'instance' => $customcert->id,
            'name' => 'Renamed by a user without manageautomaticissuance',
        ];

        instance_callbacks::update_instance($data, null);

        $this->assertEquals(1, (int)$DB->get_field('customcert', 'issueautomatically', ['id' => $customcert->id]));
        $this->assertEquals(
            'Renamed by a user without manageautomaticissuance',
            $DB->get_field('customcert', 'name', ['id' => $customcert->id])
        );
    }

    /**
     * A user with manageautomaticissuance submits a real change to the setting, and
     * update_instance() applies it.
     *
     * @covers \mod_customcert\callback\instance_callbacks::update_instance
     */
    public function test_update_instance_applies_issueautomatically_when_submitted(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'issueautomatically' => 1,
        ]);

        $data = (object)['instance' => $customcert->id, 'issueautomatically' => 0];
        instance_callbacks::update_instance($data, null);

        $this->assertEquals(0, (int)$DB->get_field('customcert', 'issueautomatically', ['id' => $customcert->id]));
    }

    /**
     * mod/customcert:manageautomaticissuance is independent of mod/customcert:manageemailothers:
     * a user who can manage "Email others" but not automatic issuance keeps their emailothers
     * submission untouched while issueautomatically still falls back to the default.
     *
     * @covers \mod_customcert_mod_form::data_postprocessing
     */
    public function test_manageautomaticissuance_independent_of_manageemailothers(): void {
        $this->resetAfterTest();

        [$user, $context, $roleid] = $this->create_user_with_role();
        assign_capability('mod/customcert:manageemailothers', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('mod/customcert:manageautomaticissuance', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $this->assertTrue(has_capability('mod/customcert:manageemailothers', $context));
        $this->assertFalse(has_capability('mod/customcert:manageautomaticissuance', $context));

        $form = $this->make_form($context, (object)['add' => 'customcert']);

        // The emailothers field was submitted (this user has that capability); issueautomatically was not,
        // since they lack that one -- both fields are otherwise handled by the same loop.
        $data = (object)['add' => 1, 'emailothers' => 'teacher@example.com'];
        $form->data_postprocessing($data);

        $this->assertEquals('teacher@example.com', $data->emailothers);
        $this->assertTrue(empty($data->issueautomatically));
    }
}
