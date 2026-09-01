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
 * Contains unit tests for mod_customcert_mod_form's completionemailed rule.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use context_system;
use MoodleQuickForm;
use ReflectionClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/customcert/mod_form.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Unit tests for mod_customcert_mod_form's completionemailed rule.
 *
 * Since Moodle 4.3, the Default activity completion page renders all modules' completion
 * elements on one shared form, so element ids must be suffixed. These tests call
 * add_completion_rules() etc. directly with the constructor skipped, since building a real
 * moodleform_mod (or that shared page) is expensive for this alone.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_form_completion_test extends advanced_testcase {
    /**
     * Build a mod_customcert_mod_form with the given suffix, skipping the constructor (which
     * would otherwise eagerly build the full form and require a real course module context).
     *
     * @param string $suffix
     * @param int $currentemailstudents The emailstudents value to report as currently stored.
     *   Ignored if $current is provided.
     * @param object|null $current Override for $this->current, e.g. to simulate a new instance
     *   (which has no stored emailstudents yet) via (object)['add' => 'customcert'].
     * @return \mod_customcert_mod_form
     */
    private function make_form(string $suffix, int $currentemailstudents = 1, ?object $current = null): \mod_customcert_mod_form {
        $refclass = new ReflectionClass(\mod_customcert_mod_form::class);
        $form = $refclass->newInstanceWithoutConstructor();

        $formprop = $refclass->getProperty('_form');
        $formprop->setAccessible(true);
        $formprop->setValue($form, new MoodleQuickForm('mod_form_completion_test', 'post', '#'));

        $contextprop = $refclass->getProperty('context');
        $contextprop->setAccessible(true);
        $contextprop->setValue($form, context_system::instance());

        $currentprop = $refclass->getProperty('current');
        $currentprop->setAccessible(true);
        $currentprop->setValue($form, $current ?? (object)['emailstudents' => $currentemailstudents]);

        $form->set_suffix($suffix);

        return $form;
    }

    /**
     * add_completion_rules() must append the suffix to the checkbox element id it adds, and
     * completion_rule_enabled() must read from the correspondingly suffixed data key.
     *
     * @covers \mod_customcert_mod_form::add_completion_rules
     * @covers \mod_customcert_mod_form::completion_rule_enabled
     */
    public function test_completion_elements_are_suffixed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = $this->make_form('_customcert');

        $elements = $form->add_completion_rules();
        $this->assertSame(['completionemailed_customcert'], $elements);

        $this->assertTrue($form->completion_rule_enabled(['completionemailed_customcert' => 1]));
        $this->assertFalse($form->completion_rule_enabled(['completionemailed' => 1]));
        $this->assertFalse($form->completion_rule_enabled(['completionemailed_customcert' => 0]));
    }

    /**
     * With no suffix set (the ordinary per-instance settings page), the element id and data key
     * must remain the plain, unsuffixed 'completionemailed'.
     *
     * @covers \mod_customcert_mod_form::add_completion_rules
     * @covers \mod_customcert_mod_form::completion_rule_enabled
     */
    public function test_completion_elements_without_suffix(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = $this->make_form('');

        $elements = $form->add_completion_rules();
        $this->assertSame(['completionemailed'], $elements);

        $this->assertTrue($form->completion_rule_enabled(['completionemailed' => 1]));
    }

    /**
     * When the current user can't manage emailstudents and it isn't already enabled for this
     * instance, the disabled-note static element must also carry the suffix -- otherwise it
     * would collide with another module's element of the same unsuffixed id on the same page.
     *
     * @covers \mod_customcert_mod_form::add_completion_rules
     */
    public function test_disabled_note_element_is_suffixed(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $form = $this->make_form('_customcert', 0);

        $form->add_completion_rules();

        $formprop = (new ReflectionClass(\mod_customcert_mod_form::class))->getProperty('_form');
        $formprop->setAccessible(true);
        $mform = $formprop->getValue($form);

        $this->assertTrue($mform->elementExists('completionemailed_disabled_note_customcert'));
        $this->assertFalse($mform->elementExists('completionemailed_disabled_note'));
    }

    /**
     * On a new instance, a user without manageemailstudents falls back to the site default
     * for emailstudents, not the (nonexistent) stored value.
     *
     * @covers \mod_customcert_mod_form::add_completion_rules
     */
    public function test_completion_checkbox_uses_site_default_on_new_instance_when_enabled(): void {
        $this->resetAfterTest();
        set_config('emailstudents', 1, 'customcert');
        $this->setUser($this->getDataGenerator()->create_user());

        $form = $this->make_form('_customcert', 0, (object)['add' => 'customcert']);

        $form->add_completion_rules();

        $formprop = (new ReflectionClass(\mod_customcert_mod_form::class))->getProperty('_form');
        $formprop->setAccessible(true);
        $mform = $formprop->getValue($form);

        $this->assertFalse($mform->elementExists('completionemailed_disabled_note_customcert'));
    }

    /**
     * Same as above, but the checkbox stays disabled when the site default is off.
     *
     * @covers \mod_customcert_mod_form::add_completion_rules
     */
    public function test_completion_checkbox_disabled_on_new_instance_when_site_default_off(): void {
        $this->resetAfterTest();
        set_config('emailstudents', 0, 'customcert');
        $this->setUser($this->getDataGenerator()->create_user());

        $form = $this->make_form('_customcert', 0, (object)['add' => 'customcert']);

        $form->add_completion_rules();

        $formprop = (new ReflectionClass(\mod_customcert_mod_form::class))->getProperty('_form');
        $formprop->setAccessible(true);
        $mform = $formprop->getValue($form);

        $this->assertTrue($mform->elementExists('completionemailed_disabled_note_customcert'));
    }

    /**
     * validation() must use the same site-default fallback as add_completion_rules() for a
     * new instance.
     *
     * @covers \mod_customcert_mod_form::validation
     */
    public function test_validation_uses_site_default_emailstudents_on_new_instance(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        // Irrelevant to what's under test here; skips core_availability's own data requirements.
        $CFG->enableavailability = false;

        $form = $this->make_form('_customcert', 0, (object)['add' => 'customcert']);
        $data = [
            'modulename' => 'customcert',
            'instance' => 0,
            'coursemodule' => 0,
            'completionemailed_customcert' => 1,
        ];

        set_config('emailstudents', 1, 'customcert');
        $errors = $form->validation($data, []);
        $this->assertArrayNotHasKey('completionemailed_customcert', $errors);

        set_config('emailstudents', 0, 'customcert');
        $errors = $form->validation($data, []);
        $this->assertArrayHasKey('completionemailed_customcert', $errors);
    }

    /**
     * data_postprocessing() clears completionemailed when completion tracking isn't automatic.
     *
     * @covers \mod_customcert_mod_form::data_postprocessing
     */
    public function test_data_postprocessing_clears_rule_when_not_automatic(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = $this->make_form('_customcert');

        $data = (object)[
            'add' => 1,
            'completionunlocked' => 1,
            'completion_customcert' => COMPLETION_TRACKING_MANUAL,
            'completionemailed_customcert' => 1,
        ];

        $form->data_postprocessing($data);

        $this->assertEquals(0, $data->completionemailed_customcert);
    }

    /**
     * data_postprocessing() leaves completionemailed alone when automatic and checked.
     *
     * @covers \mod_customcert_mod_form::data_postprocessing
     */
    public function test_data_postprocessing_keeps_rule_when_automatic_and_checked(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = $this->make_form('_customcert');

        $data = (object)[
            'add' => 1,
            'completionunlocked' => 1,
            'completion_customcert' => COMPLETION_TRACKING_AUTOMATIC,
            'completionemailed_customcert' => 1,
        ];

        $form->data_postprocessing($data);

        $this->assertEquals(1, $data->completionemailed_customcert);
    }

    /**
     * Data provider of the site-wide 'emailstudents' default, for the default-completion-form
     * tests below that must behave identically regardless of it.
     *
     * @return array[]
     */
    public static function site_default_emailstudents_provider(): array {
        return [
            'Site default disabled' => [0],
            'Site default enabled' => [1],
        ];
    }

    /**
     * With manageemailstudents, disabledIf() binds to 'emailstudents' -- a field the shared
     * Default activity completion form never adds, so the checkbox stays usable there
     * regardless of the site default.
     *
     * @covers \mod_customcert_mod_form::add_completion_rules
     * @dataProvider site_default_emailstudents_provider
     * @param int $sitedefault The site-wide 'emailstudents' config value.
     */
    public function test_default_completion_form_with_manageemailstudents_capability(int $sitedefault): void {
        $this->resetAfterTest();
        set_config('emailstudents', $sitedefault, 'customcert');
        $this->setAdminUser();

        // Core function core_completion\manager::get_module_form() sets $data->add = $modname
        // when there is no cm_info, which is always the case on the Default activity completion
        // page.
        $form = $this->make_form('_customcert', 0, (object)['add' => 'customcert']);
        $elements = $form->add_completion_rules();

        $this->assertSame(['completionemailed_customcert'], $elements);

        $formprop = (new ReflectionClass(\mod_customcert_mod_form::class))->getProperty('_form');
        $formprop->setAccessible(true);
        $mform = $formprop->getValue($form);

        $this->assertFalse($mform->elementExists('completionemailed_disabled_note_customcert'));
        $this->assertFalse($mform->elementExists('emailstudents'));
        $this->assertTrue($mform->elementExists('completionemailed_customcert'));
    }

    /**
     * Without manageemailstudents, the checkbox instead follows the site default via
     * get_effective_emailstudents().
     *
     * @covers \mod_customcert_mod_form::add_completion_rules
     * @dataProvider site_default_emailstudents_provider
     * @param int $sitedefault The site-wide 'emailstudents' config value.
     */
    public function test_default_completion_form_without_manageemailstudents_capability(int $sitedefault): void {
        $this->resetAfterTest();
        set_config('emailstudents', $sitedefault, 'customcert');
        $this->setUser($this->getDataGenerator()->create_user());

        $form = $this->make_form('_customcert', 0, (object)['add' => 'customcert']);
        $form->add_completion_rules();

        $formprop = (new ReflectionClass(\mod_customcert_mod_form::class))->getProperty('_form');
        $formprop->setAccessible(true);
        $mform = $formprop->getValue($form);

        if ($sitedefault) {
            $this->assertFalse($mform->elementExists('completionemailed_disabled_note_customcert'));
        } else {
            $this->assertTrue($mform->elementExists('completionemailed_disabled_note_customcert'));
        }
    }
}
