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
 * PHPUnit tests for the assignfeedback customcert element.
 *
 * @package    customcertelement_assignfeedback
 * @copyright  2026 Joe Rebbeck <joerebbeck@hotmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \customcertelement_assignfeedback\element
 */

namespace customcertelement_assignfeedback;

defined('MOODLE_INTERNAL') || die();

/**
 * Test suite for the assignfeedback customcert element.
 *
 * These tests exercise get_feedback_for_user() directly by creating the
 * minimum necessary database records (course, assignment, grade, feedback)
 * through Moodle's standard generator API, then asserting the correct
 * string is returned for each scenario.
 *
 * @package    customcertelement_assignfeedback
 * @copyright  2026 Joe Rebbeck <joerebbeck@hotmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element_test extends \advanced_testcase {

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a minimal element instance with a given assignment ID stored as
     * its data value, without requiring a real customcert record.
     *
     * get_feedback_for_user() only calls $this->get_data() and the global
     * $DB, so we only need those to work correctly.
     *
     * @param int $assignid The assignment ID to embed in the element data.
     * @return element
     */
    private function make_element(int $assignid): element {
        $record = new \stdClass();
        $record->id = 0;
        $record->pageid = 0;
        $record->name = 'Test feedback element';
        $record->element = 'assignfeedback';
        $record->data = (string) $assignid;
        $record->font = 'freesans';
        $record->fontsize = 12;
        $record->colour = '#000000';
        $record->posx = 0;
        $record->posy = 0;
        $record->width = 0;
        $record->refpoint = 0;
        $record->sequence = 1;

        return new element($record);
    }

    /**
     * Insert an assign_grades row and a matching assignfeedback_comments row,
     * simulating a grader who has left text feedback for a student.
     *
     * @param int    $assignid     The assignment ID.
     * @param int    $userid       The student's user ID.
     * @param string $feedbacktext The HTML/plain-text feedback to store.
     * @return void
     */
    private function create_feedback(int $assignid, int $userid, string $feedbacktext): void {
        global $DB;

        $gradeid = $DB->insert_record('assign_grades', [
            'assignment'    => $assignid,
            'userid'        => $userid,
            'timecreated'   => time(),
            'timemodified'  => time(),
            'grader'        => 2,
            'grade'         => 80.0,
            'attemptnumber' => 0,
        ]);

        $DB->insert_record('assignfeedback_comments', [
            'assignment'    => $assignid,
            'grade'         => $gradeid,
            'commenttext'   => $feedbacktext,
            'commentformat' => FORMAT_HTML,
        ]);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /**
     * A student who has been graded and has feedback should see that feedback
     * rendered as plain text (HTML stripped).
     *
     * @return void
     */
    public function test_feedback_returned_for_graded_student(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $student   = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        /** @var \mod_assign_generator $assigngen */
        $assigngen = $generator->get_plugin_generator('mod_assign');
        $assign    = $assigngen->create_instance(['course' => $course->id]);

        $this->create_feedback($assign->id, $student->id, '<p>Well done, great work!</p>');

        $element = $this->make_element($assign->id);
        $result  = $this->call_get_feedback($element, $student);

        $this->assertEquals('Well done, great work!', $result);
    }

    /**
     * A student with a grade record but no feedback comments entry should
     * receive the "no feedback available" string.
     *
     * @return void
     */
    public function test_no_feedback_comments_returns_unavailable_string(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $student   = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        /** @var \mod_assign_generator $assigngen */
        $assigngen = $generator->get_plugin_generator('mod_assign');
        $assign    = $assigngen->create_instance(['course' => $course->id]);

        global $DB;
        $DB->insert_record('assign_grades', [
            'assignment'    => $assign->id,
            'userid'        => $student->id,
            'timecreated'   => time(),
            'timemodified'  => time(),
            'grader'        => 2,
            'grade'         => 70.0,
            'attemptnumber' => 0,
        ]);

        $element = $this->make_element($assign->id);
        $result  = $this->call_get_feedback($element, $student);

        $this->assertEquals(
            get_string('nofeedbackavailable', 'customcertelement_assignfeedback'),
            $result
        );
    }

    /**
     * A student who has not been graded at all (no assign_grades row) should
     * receive the "no feedback available" string.
     *
     * @return void
     */
    public function test_no_grade_record_returns_unavailable_string(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $student   = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        /** @var \mod_assign_generator $assigngen */
        $assigngen = $generator->get_plugin_generator('mod_assign');
        $assign    = $assigngen->create_instance(['course' => $course->id]);

        $element = $this->make_element($assign->id);
        $result  = $this->call_get_feedback($element, $student);

        $this->assertEquals(
            get_string('nofeedbackavailable', 'customcertelement_assignfeedback'),
            $result
        );
    }

    /**
     * An element configured with an assignment ID that no longer exists in
     * the database should return an empty string rather than an error.
     *
     * @return void
     */
    public function test_deleted_assignment_returns_empty_string(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $element = $this->make_element(99999);
        $result  = $this->call_get_feedback($element, $student);

        $this->assertSame('', $result);
    }

    /**
     * An element with no assignment configured (data = '0') should return
     * an empty string immediately without querying the database.
     *
     * @return void
     */
    public function test_zero_assignid_returns_empty_string(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $element = $this->make_element(0);
        $result  = $this->call_get_feedback($element, $student);

        $this->assertSame('', $result);
    }

    /**
     * HTML tags in the stored feedback text should be stripped so that only
     * plain text is returned for rendering in the PDF.
     *
     * @return void
     */
    public function test_html_is_stripped_from_feedback(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $student   = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        /** @var \mod_assign_generator $assigngen */
        $assigngen = $generator->get_plugin_generator('mod_assign');
        $assign    = $assigngen->create_instance(['course' => $course->id]);

        $this->create_feedback(
            $assign->id,
            $student->id,
            '<p>Your essay was <strong>excellent</strong>. Keep it up!</p>'
        );

        $element = $this->make_element($assign->id);
        $result  = $this->call_get_feedback($element, $student);

        $this->assertEquals('Your essay was excellent. Keep it up!', $result);
        $this->assertStringNotContainsString('<', $result);
    }

    // -----------------------------------------------------------------------
    // Private helper
    // -----------------------------------------------------------------------

    /**
     * Call the protected get_feedback_for_user() method via reflection.
     *
     * Using reflection keeps the method protected in production while still
     * allowing direct unit testing of the core logic without needing to
     * render a PDF.
     *
     * @param element   $element The element instance under test.
     * @param \stdClass $user    The user to pass to the method.
     * @return string The return value of get_feedback_for_user().
     */
    private function call_get_feedback(element $element, \stdClass $user): string {
        $ref = new \ReflectionMethod($element, 'get_feedback_for_user');
        $ref->setAccessible(true);
        return $ref->invoke($element, $user);
    }
}
