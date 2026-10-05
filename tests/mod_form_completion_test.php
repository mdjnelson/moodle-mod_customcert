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
 * Tests for the completionemailed completion rule on the activity instance form.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use advanced_testcase;
use context_course;
use MoodleQuickForm;
use ReflectionClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/customcert/mod_form.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Tests for the completionemailed completion rule on the activity instance form.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_form_completion_test extends advanced_testcase {
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
        $formprop->setValue($form, new MoodleQuickForm('mod_form_completion_test', 'post', '#'));

        $contextprop = $refclass->getProperty('context');
        $contextprop->setAccessible(true);
        $contextprop->setValue($form, $context);

        $currentprop = $refclass->getProperty('current');
        $currentprop->setAccessible(true);
        $currentprop->setValue($form, $current);

        return $form;
    }

    /**
     * Fetch the MoodleQuickForm underlying a form built by make_form(), bypassing get_form()'s
     * protected visibility.
     *
     * @param \mod_customcert_mod_form $form
     * @return MoodleQuickForm
     */
    private function get_mform(\mod_customcert_mod_form $form): MoodleQuickForm {
        $refclass = new ReflectionClass(\mod_customcert_mod_form::class);
        $formprop = $refclass->getProperty('_form');
        $formprop->setAccessible(true);

        return $formprop->getValue($form);
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
     * completion_rule_enabled() must reflect the submitted completionemailed value.
     *
     * @covers \mod_customcert_mod_form::completion_rule_enabled
     */
    public function test_completion_rule_enabled(): void {
        $this->resetAfterTest();

        $refclass = new ReflectionClass(\mod_customcert_mod_form::class);
        $form = $refclass->newInstanceWithoutConstructor();

        $this->assertTrue($form->completion_rule_enabled(['completionemailed' => 1]));
        $this->assertFalse($form->completion_rule_enabled(['completionemailed' => 0]));
        $this->assertFalse($form->completion_rule_enabled([]));
    }

    /**
     * A user with mod/customcert:manageemailstudents gets the checkbox wired with a disabledIf()
     * against emailstudents, rather than a static note.
     *
     * @covers \mod_customcert_mod_form::add_completion_rules
     */
    public function test_add_completion_rules_disables_on_emailstudents_for_manager(): void {
        $this->resetAfterTest();

        [$user, $context, $roleid] = $this->create_user_with_role();
        assign_capability('mod/customcert:manageemailstudents', CAP_ALLOW, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $form = $this->make_form($context, (object)['add' => 'customcert']);
        $elements = $form->add_completion_rules();

        $this->assertEquals(['completionemailed'], $elements);

        $mform = $this->get_mform($form);
        $this->assertFalse($mform->elementExists('completionemailed_disabled_note'));

        $refclass = new ReflectionClass(\MoodleQuickForm::class);
        $depsprop = $refclass->getProperty('_dependencies');
        $depsprop->setAccessible(true);
        $dependencies = $depsprop->getValue($mform);

        $this->assertArrayHasKey('emailstudents', $dependencies);
        $this->assertArrayHasKey('eq', $dependencies['emailstudents']);
        $this->assertArrayHasKey('0', $dependencies['emailstudents']['eq']);
        $this->assertContains('completionemailed', $dependencies['emailstudents']['eq']['0']);
    }

    /**
     * A user without mod/customcert:manageemailstudents, on an instance that doesn't have
     * emailstudents enabled, gets the checkbox force-disabled with an explanatory note, since they
     * have no way to make emailstudents true themselves.
     *
     * @covers \mod_customcert_mod_form::add_completion_rules
     */
    public function test_add_completion_rules_force_disabled_without_capability_and_emailstudents_off(): void {
        $this->resetAfterTest();
        set_config('emailstudents', 0, 'customcert');

        [$user, $context, $roleid] = $this->create_user_with_role();
        assign_capability('mod/customcert:manageemailstudents', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $form = $this->make_form($context, (object)['add' => 'customcert']);
        $form->add_completion_rules();

        $mform = $this->get_mform($form);
        $this->assertTrue($mform->elementExists('completionemailed_disabled_note'));
    }

    /**
     * A user without mod/customcert:manageemailstudents, editing an existing instance that already
     * has emailstudents enabled, is not force-disabled: the stored value is used as the effective
     * emailstudents, even though they can't see or change the field themselves.
     *
     * @covers \mod_customcert_mod_form::add_completion_rules
     */
    public function test_add_completion_rules_not_forced_when_existing_instance_has_emailstudents(): void {
        $this->resetAfterTest();

        [$user, $context, $roleid] = $this->create_user_with_role();
        assign_capability('mod/customcert:manageemailstudents', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $form = $this->make_form($context, (object)['update' => 1, 'emailstudents' => 1]);
        $form->add_completion_rules();

        $mform = $this->get_mform($form);
        $this->assertFalse($mform->elementExists('completionemailed_disabled_note'));
    }

    /**
     * data_postprocessing() clears a submitted completionemailed = 1 whenever the submitted
     * completion tracking is not automatic, so a stale value can't survive a switch to
     * none/manual tracking.
     *
     * @covers \mod_customcert_mod_form::data_postprocessing
     */
    public function test_data_postprocessing_clears_completionemailed_when_not_automatic(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $form = $this->make_form(context_course::instance($course->id), (object)['add' => 'customcert']);

        $data = (object)[
            'add' => 1,
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionemailed' => 1,
        ];
        $form->data_postprocessing($data);

        $this->assertEquals(0, $data->completionemailed);
    }

    /**
     * data_postprocessing() leaves a submitted completionemailed = 1 untouched when completion
     * tracking is automatic.
     *
     * @covers \mod_customcert_mod_form::data_postprocessing
     */
    public function test_data_postprocessing_preserves_completionemailed_when_automatic(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $form = $this->make_form(context_course::instance($course->id), (object)['add' => 'customcert']);

        $data = (object)[
            'add' => 1,
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionemailed' => 1,
        ];
        $form->data_postprocessing($data);

        $this->assertEquals(1, $data->completionemailed);
    }

    /**
     * validation() rejects completionemailed = 1 submitted alongside emailstudents = 0 by a user
     * who can manage emailstudents -- the server-side guard against a crafted POST bypassing the
     * JS-side disabledIf().
     *
     * @covers \mod_customcert_mod_form::validation
     */
    public function test_validation_rejects_completionemailed_without_emailstudents(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $form = $this->make_form(context_course::instance($course->id), (object)['add' => 'customcert']);

        $data = [
            'completionemailed' => 1,
            'emailstudents' => 0,
            'modulename' => 'customcert',
            'instance' => 0,
            'coursemodule' => 0,
            'availabilityconditionsjson' => '{"op":"&","c":[],"showc":[]}',
        ];
        $errors = $form->validation($data, []);

        $this->assertArrayHasKey('completionemailed', $errors);
    }

    /**
     * validation() accepts completionemailed = 1 when emailstudents = 1 is submitted alongside it.
     *
     * @covers \mod_customcert_mod_form::validation
     */
    public function test_validation_accepts_completionemailed_with_emailstudents(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $form = $this->make_form(context_course::instance($course->id), (object)['add' => 'customcert']);

        $data = [
            'completionemailed' => 1,
            'emailstudents' => 1,
            'modulename' => 'customcert',
            'instance' => 0,
            'coursemodule' => 0,
            'availabilityconditionsjson' => '{"op":"&","c":[],"showc":[]}',
        ];
        $errors = $form->validation($data, []);

        $this->assertArrayNotHasKey('completionemailed', $errors);
    }

    /**
     * A user without mod/customcert:manageemailstudents never submits the emailstudents field at
     * all, so validation() must fall back to the effective (stored, or site default) value rather
     * than treating its absence as false.
     *
     * @covers \mod_customcert_mod_form::validation
     */
    public function test_validation_falls_back_to_effective_emailstudents_when_absent(): void {
        $this->resetAfterTest();
        set_config('emailstudents', 1, 'customcert');

        $course = $this->getDataGenerator()->create_course();
        $form = $this->make_form(context_course::instance($course->id), (object)['add' => 'customcert']);

        // No 'emailstudents' key at all: this user never had the field registered for them.
        $data = [
            'completionemailed' => 1,
            'modulename' => 'customcert',
            'instance' => 0,
            'coursemodule' => 0,
            'availabilityconditionsjson' => '{"op":"&","c":[],"showc":[]}',
        ];
        $errors = $form->validation($data, []);

        $this->assertArrayNotHasKey('completionemailed', $errors);
    }
}
