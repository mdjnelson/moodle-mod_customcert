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
 * File contains the unit tests for the element helper class.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Unit tests for the element helper class.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_customcert_element_helper_testcase extends advanced_testcase {

    /**
     * Test set up.
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Tests we are returning the correct course id for an element in a course customcert activity.
     */
    public function test_get_courseid_element_in_course_certificate() {
        global $DB;

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a custom certificate in the course.
        $customcert = $this->getDataGenerator()->create_module('customcert', array('course' => $course->id,
            'emailstudents' => 1));

        // Get the template to add elemenets to.
        $template = $DB->get_record('customcert_templates', array('contextid' => context_module::instance($customcert->cmid)->id));
        $template = new \mod_customcert\template($template);

        // Add a page to the template.
        $pageid = $template->add_page();

        // Add an element to this page.
        $element = new \stdClass();
        $element->name = 'Test element';
        $element->element = 'testelement';
        $element->pageid = $pageid;
        $element->sequence = \mod_customcert\element_helper::get_element_sequence($element->pageid);
        $element->timecreated = time();
        $element->id = $DB->insert_record('customcert_elements', $element);

        // Confirm the correct course id is returned.
        $this->assertEquals($course->id, \mod_customcert\element_helper::get_courseid($element->id));
    }

    /**
     * Tests we are returning the correct course id for an element in a site template.
     */
    public function test_get_courseid_element_in_site_template() {
        global $DB, $SITE;

        // Add a template to the site.
        $template = \mod_customcert\template::create('Site template', context_system::instance()->id);

        // Add a page to the template.
        $pageid = $template->add_page();

        // Add an element to this page.
        $element = new \stdClass();
        $element->name = 'Test element';
        $element->element = 'testelement';
        $element->pageid = $pageid;
        $element->sequence = \mod_customcert\element_helper::get_element_sequence($element->pageid);
        $element->timecreated = time();
        $element->id = $DB->insert_record('customcert_elements', $element);

        // Confirm the correct course id is returned.
        $this->assertEquals($SITE->id, \mod_customcert\element_helper::get_courseid($element->id));
    }
}
