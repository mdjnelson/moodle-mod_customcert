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
 * Unit tests for the userfield certificate element.
 *
 * @package    customcertelement_userfield
 * @category   test
 * @copyright  2026 Vlad Kidanov <vlad.kidanov@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_userfield;

use advanced_testcase;
use context_course;
use context_module;
use context_system;
use mod_customcert\element_factory;
use mod_customcert\element_helper;
use mod_customcert\template;

/**
 * Unit tests for the userfield certificate element.
 *
 * @package    customcertelement_userfield
 * @category   test
 * @copyright  2026 Vlad Kidanov <vlad.kidanov@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \customcertelement_userfield\element
 */
final class element_test extends advanced_testcase {
    /** @var \stdClass course used across tests */
    private $course;

    /** @var \stdClass the customcert activity used across tests */
    private $customcert;

    /** @var template the certificate template used across tests */
    private $template;

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();

        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        // Registers the customcert_colourpicker element type needed by a real MoodleQuickForm.
        require_once($CFG->dirroot . '/mod/customcert/classes/edit_element_form.php');

        $this->course = $this->getDataGenerator()->create_course();
        $this->customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $this->course->id]);

        $templaterecord = $GLOBALS['DB']->get_record('customcert_templates', [
            'contextid' => context_module::instance($this->customcert->cmid)->id,
        ]);
        $this->template = new template($templaterecord);
    }

    /**
     * Assigns a role to the user in the test course.
     *
     * @param int $userid
     * @param string $roleshortname
     */
    private function enrol(int $userid, string $roleshortname): void {
        $this->getDataGenerator()->role_assign(
            $roleshortname,
            $userid,
            context_course::instance($this->course->id)->id
        );
    }

    /**
     * Creates a "user field" certificate element pointing at the given field value.
     *
     * @param string $userfieldvalue
     * @return element the instantiated element
     */
    private function create_userfield_element(string $userfieldvalue): element {
        global $DB;

        $pageid = $DB->get_field('customcert_pages', 'id', ['templateid' => $this->template->get_id()]);

        $record = new \stdClass();
        $record->name = 'Test user field element';
        $record->element = 'userfield';
        $record->pageid = $pageid;
        $record->data = $userfieldvalue;
        $record->sequence = element_helper::get_element_sequence($pageid);
        $record->timecreated = time();
        $record->timemodified = time();
        $record->id = $DB->insert_record('customcert_elements', $record);

        return element_factory::get_element_instance($record);
    }

    /**
     * Creates a custom profile field with the given visibility and, optionally, a value for a user.
     *
     * @param int $visible one of PROFILE_VISIBLE_NONE/PRIVATE/TEACHERS/ALL
     * @param int|null $userid if given, a value is stored for this user
     * @param string $value the value to store
     * @return int the created field's id
     */
    private function create_custom_field(int $visible, ?int $userid = null, string $value = 'SECRET-VALUE'): int {
        global $DB;

        $categoryid = $DB->get_field_sql('SELECT MIN(id) FROM {user_info_category}');
        if (!$categoryid) {
            $categoryid = $DB->insert_record('user_info_category', (object) ['name' => 'Test category', 'sortorder' => 1]);
        }

        $fieldid = $DB->insert_record('user_info_field', (object) [
            'shortname' => 'restrictedfield',
            'name' => 'Restricted field',
            'datatype' => 'text',
            'description' => '',
            'descriptionformat' => 1,
            'categoryid' => $categoryid,
            'sortorder' => 1,
            'required' => 0,
            'locked' => 0,
            'visible' => $visible,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'defaultdataformat' => 1,
            'param1' => 30,
            'param2' => 2048,
            'param3' => 0,
        ]);

        if ($userid !== null) {
            $DB->insert_record('user_info_data', (object) [
                'userid' => $userid,
                'fieldid' => $fieldid,
                'data' => $value,
                'dataformat' => 0,
            ]);
        }

        return $fieldid;
    }

    /**
     * A plain teacher must not see a PROFILE_VISIBLE_NONE custom field's value for another user.
     *
     * @covers \customcertelement_userfield\element::render
     */
    public function test_admin_only_custom_field_is_not_disclosed_to_teacher(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $victim = $this->getDataGenerator()->create_user();
        $this->enrol($teacher->id, 'editingteacher');
        $this->enrol($victim->id, 'student');

        $fieldid = $this->create_custom_field(PROFILE_VISIBLE_NONE, $victim->id, 'SECRET-VICTIM-001');

        $element = $this->create_userfield_element((string) $fieldid);

        $this->setUser($teacher);
        $this->assertFalse(
            has_capability('moodle/user:viewalldetails', context_module::instance($this->customcert->cmid)),
            'Precondition: the teacher must not hold moodle/user:viewalldetails.'
        );

        $method = new \ReflectionMethod($element, 'get_user_field_value');
        $method->setAccessible(true);
        $value = $method->invoke($element, $DB->get_record('user', ['id' => $victim->id]), false);

        $this->assertStringNotContainsString(
            'SECRET-VICTIM-001',
            $value,
            'The admin-only custom profile field value must not be disclosed to a plain teacher.'
        );
    }

    /**
     * An admin holding moodle/user:viewalldetails must still see the PROFILE_VISIBLE_NONE field's value.
     *
     * @covers \customcertelement_userfield\element::render
     */
    public function test_admin_only_custom_field_is_disclosed_to_admin(): void {
        global $DB;

        $admin = $this->getDataGenerator()->create_user();
        $systemcontext = context_system::instance();
        $adminrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($adminrole, $admin->id, $systemcontext->id);
        assign_capability('moodle/user:viewalldetails', CAP_ALLOW, $adminrole, $systemcontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $victim = $this->getDataGenerator()->create_user();
        $this->enrol($victim->id, 'student');

        $fieldid = $this->create_custom_field(PROFILE_VISIBLE_NONE, $victim->id, 'SECRET-VICTIM-002');

        $element = $this->create_userfield_element((string) $fieldid);

        $this->setUser($admin);
        $this->assertTrue(has_capability('moodle/user:viewalldetails', $systemcontext));

        $method = new \ReflectionMethod($element, 'get_user_field_value');
        $method->setAccessible(true);
        $value = $method->invoke($element, $DB->get_record('user', ['id' => $victim->id]), false);

    }

    /**
     * A PROFILE_VISIBLE_ALL custom field must still render normally for any viewer.
     *
     * @covers \customcertelement_userfield\element::render
     */
    public function test_publicly_visible_custom_field_still_renders(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $victim = $this->getDataGenerator()->create_user();
        $this->enrol($teacher->id, 'editingteacher');
        $this->enrol($victim->id, 'student');

        $fieldid = $this->create_custom_field(PROFILE_VISIBLE_ALL, $victim->id, 'PUBLIC-VALUE-123');

        $element = $this->create_userfield_element((string) $fieldid);

        $this->setUser($teacher);

        $method = new \ReflectionMethod($element, 'get_user_field_value');
        $method->setAccessible(true);
        $value = $method->invoke($element, $DB->get_record('user', ['id' => $victim->id]), false);

        $this->assertStringContainsString(
            'PUBLIC-VALUE-123',
            $value,
            'A PROFILE_VISIBLE_ALL custom field must still render for any viewer.'
        );
    }

    /**
     * When a standard identity field IS part of showuseridentity and the viewer holds moodle/site:viewuseridentity,
     * it must still render normally.
     *
     * @covers \customcertelement_userfield\element::render
     */
    public function test_allowed_standard_identity_field_still_renders(): void {
        global $DB, $CFG;

        $CFG->showuseridentity = 'email,idnumber';

        $teacher = $this->getDataGenerator()->create_user();
        $victim = $this->getDataGenerator()->create_user(['idnumber' => 'PUBLIC-IDNUMBER-002']);
        $this->enrol($teacher->id, 'editingteacher');
        $this->enrol($victim->id, 'student');

        $this->assertTrue(
            has_capability('moodle/site:viewuseridentity', context_module::instance($this->customcert->cmid), $teacher->id),
            'Precondition: editingteacher must hold moodle/site:viewuseridentity by default.'
        );

        $element = $this->create_userfield_element('idnumber');

        $this->setUser($teacher);

        $method = new \ReflectionMethod($element, 'get_user_field_value');
        $method->setAccessible(true);
        $value = $method->invoke($element, $DB->get_record('user', ['id' => $victim->id]), false);

        $this->assertStringContainsString(
            'PUBLIC-IDNUMBER-002',
            $value,
            'idnumber must render when part of showuseridentity and the viewer holds moodle/site:viewuseridentity.'
        );
    }

    /**
     * A PROFILE_VISIBLE_NONE custom field must not be offered in the dropdown to a user without moodle/user:viewalldetails.
     *
     * @covers \customcertelement_userfield\element::render_form_elements
     */
    public function test_admin_only_custom_field_not_offered_in_dropdown(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $this->enrol($teacher->id, 'editingteacher');
        $this->setUser($teacher);

        $fieldid = $this->create_custom_field(PROFILE_VISIBLE_NONE);

        $element = $this->create_userfield_element('');

        $mform = new \MoodleQuickForm('test', 'post', '');
        $element->render_form_elements($mform);

        $choices = $mform->getElement('userfield')->_options;
        $values = array_column($choices, 'attr');
        $values = array_column($values, 'value');

        $this->assertNotContains(
            (string) $fieldid,
            $values,
            'PROFILE_VISIBLE_NONE fields must not be offered to a plain teacher.'
        );
    }
}
