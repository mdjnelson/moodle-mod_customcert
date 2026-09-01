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

/**
 * Unit tests for mod_customcert_mod_form's completionemailed rule.
 *
 * Moodle's "Default activity completion" settings page renders every module's completion
 * elements in the same page, so since Moodle 4.3 those elements must be suffixed with
 * $this->get_suffix() to avoid colliding element ids across module types. These tests exercise
 * add_completion_rules() and completion_rule_enabled() directly with the constructor skipped,
 * since moodleform_mod builds its full form (including capability/context lookups) eagerly in
 * the constructor and a real "default completion" page is expensive to stand up for this alone.
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
     * @return \mod_customcert_mod_form
     */
    private function make_form(string $suffix, int $currentemailstudents = 1): \mod_customcert_mod_form {
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
        $currentprop->setValue($form, (object)['emailstudents' => $currentemailstudents]);

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
}
