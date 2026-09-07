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
 * Unit tests for the userfield element.
 *
 * @package    customcertelement_userfield
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_userfield;

use advanced_testcase;
use availability_profile\condition;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use stdClass;
use context_system;
use MoodleQuickForm;
use ReflectionMethod;

/**
 * Unit tests for the userfield element.
 *
 * @package    customcertelement_userfield
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class element_test extends advanced_testcase {
    /**
     * Requires user/profile/lib.php so PROFILE_VISIBLE_* constants are defined.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Helper to build a minimal element DB record.
     *
     * @param array $override
     * @return stdClass
     */
    private function make_record(array $override = []): stdClass {
        return (object) array_merge([
            'id' => 1,
            'pageid' => 1,
            'name' => 'User field',
            'element' => 'userfield',
            'data' => json_encode([
                'userfield' => 'email',
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 0,
            ]),
            'posx' => 10,
            'posy' => 10,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $override);
    }

    /**
     * Helper to create a persisted userfield element.
     *
     * @param string $userfield The userfield value.
     * @param int|null $contextid The context id.
     * @return element
     */
    private function create_persisted_element(string $userfield, ?int $contextid = null): element {
        global $DB;

        $template = (object) [
            'name' => 'Test', 'contextid' => $contextid,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $template->id = $DB->insert_record('customcert_templates', $template);
        $page = (object) [
            'templateid' => $template->id, 'width' => 222, 'height' => 333,
            'leftmargin' => 0, 'rightmargin' => 0,
            'sequence' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $page->id = $DB->insert_record('customcert_pages', $page);
        $record = $this->make_record([
            'pageid' => $page->id,
            'data' => json_encode([
                'userfield' => $userfield,
                'font' => 'times',
                'fontsize' => 14,
                'colour' => '#C0FFEE',
                'width' => 0,
            ]),
        ]);
        $record->id = $DB->insert_record('customcert_elements', $record);

        return new element($record);
    }

    /**
     * Helper to create a custom user profile field with the given visibility.
     *
     * @param string $shortname The shortname of the custom profile field.
     * @param int|string $visible One of PROFILE_VISIBLE_NONE/PRIVATE/TEACHERS/ALL.
     * @return stdClass The inserted user_info_field record (with id set).
     */
    private function create_custom_profile_field(string $shortname, int|string $visible): stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $field = (object) [
            'shortname' => $shortname,
            'name' => 'Test ' . $shortname,
            'datatype' => 'text',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'categoryid' => 0,
            'sortorder' => 1,
            'required' => 0,
            'locked' => 0,
            'visible' => (int) $visible,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'defaultdataformat' => FORMAT_HTML,
            'param1' => '30',
            'param2' => '2048',
        ];
        $field->id = $DB->insert_record('user_info_field', $field);
        // Wipe the static cache so build_form() sees this field.
        condition::wipe_static_cache();

        return $field;
    }

    /**
     * Helper to set a value for a user's custom profile field directly in the DB.
     *
     * @param int $userid The user ID.
     * @param int $fieldid The user_info_field ID.
     * @param string $data The data to set for the custom profile field.
     * @return void
     */
    private function set_custom_profile_field_data($userid, int $fieldid, string $data): void {
        global $DB;

        $DB->insert_record('user_info_data', (object) [
            'userid' => (int) $userid,
            'fieldid' => $fieldid,
            'data' => $data,
            'dataformat' => FORMAT_HTML,
        ]);
    }

    /**
     * Helper to invoke the protected get_user_field_value() method via reflection.
     *
     * @param element $el The element instance.
     * @param stdClass $user The user we are rendering this for.
     * @param bool $preview Whether this is a preview.
     * @return string
     */
    private function invoke_get_user_field_value(element $el, stdClass $user, bool $preview = false): string {
        $method = new ReflectionMethod(element::class, 'get_user_field_value');
        $method->setAccessible(true);
        return $method->invoke($el, $user, $preview);
    }

    /**
     * Helper to create a standalone MoodleQuickForm with the colour picker registered.
     *
     * @return MoodleQuickForm
     */
    private function create_test_mform(): MoodleQuickForm {
        global $CFG;

        require_once($CFG->dirroot . '/mod/customcert/includes/colourpicker.php');
        MoodleQuickForm::registerElementType(
            'customcert_colourpicker',
            $CFG->dirroot . '/mod/customcert/includes/colourpicker.php',
            'MoodleQuickForm_customcert_colourpicker'
        );

        return new MoodleQuickForm('test', 'post', '');
    }

    /**
     * Test that the constructor returns an instance of element.
     *
     * @covers \customcertelement_userfield\element
     */
    public function test_instantiation(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     *
     * @covers \customcertelement_userfield\element
     */
    public function test_implements_interfaces(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test that normalise_data() returns expected keys and values.
     *
     * @covers \customcertelement_userfield\element::normalise_data
     */
    public function test_normalise_data_returns_expected_keys(): void {
        $el = new element($this->make_record());
        $formdata = (object) [
            'userfield' => 'city',
            'font' => 'helvetica',
            'fontsize' => 14,
            'colour' => '#ff0000',
            'width' => 100,
        ];
        $result = $el->normalise_data($formdata);
        $this->assertSame('city', $result['userfield']);
        $this->assertSame('helvetica', $result['font']);
        $this->assertSame(14, $result['fontsize']);
        $this->assertSame('#ff0000', $result['colour']);
        $this->assertSame(100, $result['width']);
    }

    /**
     * Test that normalise_data() handles missing fields gracefully.
     *
     * @covers \customcertelement_userfield\element::normalise_data
     */
    public function test_normalise_data_handles_missing_fields(): void {
        $el = new element($this->make_record());
        $result = $el->normalise_data(new stdClass());
        $this->assertSame('', $result['userfield']);
        $this->assertSame('', $result['font']);
        $this->assertSame(0, $result['fontsize']);
        $this->assertSame('', $result['colour']);
        $this->assertSame(0, $result['width']);
    }

    /**
     * Test that validate() returns an empty array.
     *
     * @covers \customcertelement_userfield\element::validate
     */
    public function test_validate_returns_empty_array(): void {
        $el = new element($this->make_record());
        $this->assertSame([], $el->validate([]));
    }

    /**
     * Test that render_html() returns the user's email in preview mode.
     *
     * @covers \customcertelement_userfield\element::render_html
     */
    public function test_render_html_returns_user_field_value(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'test@example.com']);
        $this->setUser($user);

        global $DB;
        $contextid = context_system::instance()->id;
        $template = (object) [
            'name' => 'Test', 'contextid' => $contextid,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $template->id = $DB->insert_record('customcert_templates', $template);
        $page = (object) [
            'templateid' => $template->id, 'width' => 210, 'height' => 297,
            'leftmargin' => 0, 'rightmargin' => 0,
            'sequence' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $page->id = $DB->insert_record('customcert_pages', $page);
        $record = $this->make_record(['pageid' => $page->id]);
        $record->id = $DB->insert_record('customcert_elements', $record);

        $el = new element($record);
        $html = $el->render_html();
        $this->assertIsString($html);
        $this->assertStringContainsString('test@example.com', $html);
    }

    /**
     * Test that render_html() returns the field name when user field is empty.
     *
     * @covers \customcertelement_userfield\element::render_html
     */
    public function test_render_html_returns_field_name_when_empty(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['city' => '']);
        $this->setUser($user);

        global $DB;
        $contextid = context_system::instance()->id;
        $template = (object) [
            'name' => 'Test', 'contextid' => $contextid,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $template->id = $DB->insert_record('customcert_templates', $template);
        $page = (object) [
            'templateid' => $template->id, 'width' => 210, 'height' => 297,
            'leftmargin' => 0, 'rightmargin' => 0,
            'sequence' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $page->id = $DB->insert_record('customcert_pages', $page);
        $record = $this->make_record([
            'pageid' => $page->id,
            'data' => json_encode([
                'userfield' => 'city',
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 0,
            ]),
        ]);
        $record->id = $DB->insert_record('customcert_elements', $record);

        $el = new element($record);
        $html = $el->render_html();
        $this->assertIsString($html);
        // In preview mode with empty field, the field name 'city' is shown.
        $this->assertStringContainsString('city', $html);
    }

    /**
     * Test that get_type() returns 'userfield'.
     *
     * @covers \customcertelement_userfield\element
     */
    public function test_get_type(): void {
        $el = new element($this->make_record());
        $this->assertSame('userfield', $el->get_type());
    }

    /**
     * Test a PROFILE_VISIBLE_NONE field is hidden from another user without viewalldetails.
     *
     * @covers \customcertelement_userfield\element::get_user_field_value
     */
    public function test_admin_only_custom_field_is_not_disclosed_to_other_user(): void {
        $this->resetAfterTest();

        $field = $this->create_custom_profile_field('nationalid', PROFILE_VISIBLE_NONE);
        $victim = $this->getDataGenerator()->create_user();
        $this->set_custom_profile_field_data($victim->id, $field->id, 'SECRET-VICTIM-001');

        // Viewer has no special capabilities.
        $viewer = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);

        $el = $this->create_persisted_element((string) $field->id, context_system::instance()->id);
        $value = $this->invoke_get_user_field_value($el, $victim);

        $this->assertStringNotContainsString('SECRET-VICTIM-001', $value);
    }

    /**
     * Test a PROFILE_VISIBLE_NONE field is shown to a viewer with viewalldetails.
     *
     * @covers \customcertelement_userfield\element::get_user_field_value
     */
    public function test_admin_only_custom_field_is_disclosed_with_viewalldetails(): void {
        $this->resetAfterTest();

        $field = $this->create_custom_profile_field('nationalid', PROFILE_VISIBLE_NONE);
        $victim = $this->getDataGenerator()->create_user();
        $this->set_custom_profile_field_data($victim->id, $field->id, 'SECRET-VICTIM-001');

        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $viewer->id);
        $this->setUser($viewer);

        $el = $this->create_persisted_element((string) $field->id, context_system::instance()->id);
        $value = $this->invoke_get_user_field_value($el, $victim);

        $this->assertStringContainsString('SECRET-VICTIM-001', $value);
    }

    /**
     * Test a PROFILE_VISIBLE_PRIVATE field is shown to its own owner.
     *
     * @covers \customcertelement_userfield\element::get_user_field_value
     */
    public function test_private_custom_field_is_disclosed_to_own_user(): void {
        $this->resetAfterTest();

        $field = $this->create_custom_profile_field('nationalid', PROFILE_VISIBLE_PRIVATE);
        $user = $this->getDataGenerator()->create_user();
        $this->set_custom_profile_field_data($user->id, $field->id, 'PRIVATE-OWN-001');
        $this->setUser($user);

        $el = $this->create_persisted_element((string) $field->id, context_system::instance()->id);
        $value = $this->invoke_get_user_field_value($el, $user);

        $this->assertStringContainsString('PRIVATE-OWN-001', $value);
    }

    /**
     * Test an identity field is shown with viewuseridentity and showuseridentity set.
     *
     * @covers \customcertelement_userfield\element::get_user_field_value
     */
    public function test_identity_field_is_disclosed_with_capability_and_showuseridentity(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->showuseridentity = 'email';

        $victim = $this->getDataGenerator()->create_user(['email' => 'victim@example.com']);
        // Editingteacher holds moodle/site:viewuseridentity by default.
        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('editingteacher', $viewer->id);
        $this->setUser($viewer);

        $el = $this->create_persisted_element('email', context_system::instance()->id);
        $value = $this->invoke_get_user_field_value($el, $victim);

        $this->assertStringContainsString('victim@example.com', $value);
    }

    /**
     * Test build_form() excludes a PROFILE_VISIBLE_NONE field for a regular user.
     *
     * @covers \customcertelement_userfield\element::build_form
     */
    public function test_build_form_excludes_admin_only_field_for_regular_user(): void {
        $this->resetAfterTest();

        $field = $this->create_custom_profile_field('nationalid', PROFILE_VISIBLE_NONE);
        $viewer = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);

        $el = new element($this->make_record());
        $mform = $this->create_test_mform();
        $el->build_form($mform);

        $options = $mform->getElement('userfield')->_options;
        $values = array_column(array_column($options, 'attr'), 'value');
        $this->assertNotContains($field->id, $values);
    }

    /**
     * Test build_form() includes a PROFILE_VISIBLE_NONE field for a privileged user.
     *
     * @covers \customcertelement_userfield\element::build_form
     */
    public function test_build_form_includes_admin_only_field_for_privileged_user(): void {
        $this->resetAfterTest();

        $field = $this->create_custom_profile_field('nationalid', PROFILE_VISIBLE_NONE);
        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $viewer->id);
        $this->setUser($viewer);

        $el = new element($this->make_record());
        $mform = $this->create_test_mform();
        $el->build_form($mform);

        $options = $mform->getElement('userfield')->_options;
        $values = array_column(array_column($options, 'attr'), 'value');
        $this->assertContains($field->id, $values);
    }
}
