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
 * Contains unit tests for mod_customcert's activity_custom_completion implementation.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use cm_info;
use coding_exception;
use mod_customcert\completion\custom_completion;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class for unit testing mod_customcert\completion\custom_completion.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class custom_completion_test extends advanced_testcase {
    /**
     * Data provider for get_state().
     *
     * @return array[]
     */
    public static function get_state_provider(): array {
        return [
            'Undefined rule' => [
                'somenonexistentrule', COMPLETION_DISABLED, 0, false, null, coding_exception::class,
            ],
            'Rule not available' => [
                'completionemailed', COMPLETION_DISABLED, 0, false, null, moodle_exception::class,
            ],
            'Available in cache but disabled on the instance record' => [
                'completionemailed', COMPLETION_ENABLED, 0, true, COMPLETION_INCOMPLETE, null,
            ],
            'Enabled, but the student has not been emailed yet' => [
                'completionemailed', COMPLETION_ENABLED, 1, false, COMPLETION_INCOMPLETE, null,
            ],
            'Enabled, and the student has been emailed' => [
                'completionemailed', COMPLETION_ENABLED, 1, true, COMPLETION_COMPLETE, null,
            ],
        ];
    }

    /**
     * Test for get_state().
     *
     * @covers \mod_customcert\completion\custom_completion::get_state
     * @dataProvider get_state_provider
     * @param string $rule The custom completion rule.
     * @param int $available Whether this rule is available (from cm_info::customdata).
     * @param int $completionemailed The completionemailed field on the customcert instance record.
     * @param bool $studentemailed Whether the student's issue has studentemailed set.
     * @param int|null $status Expected completion status.
     * @param string|null $exception Expected exception class.
     */
    public function test_get_state(
        string $rule,
        int $available,
        int $completionemailed,
        bool $studentemailed,
        ?int $status,
        ?string $exception
    ): void {
        global $DB;

        if (!is_null($exception)) {
            $this->expectException($exception);
        }

        // Custom completion rule data for cm_info::customdata.
        $customdataval = [
            'customcompletionrules' => [
                $rule => $available,
            ],
        ];

        // Build a mock cm_info instance.
        $mockcminfo = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_custom_data'])
            ->getMock();

        // Mock the return of the get_custom_data method when fetching the cm_info object's customdata.
        $mockcminfo->expects($this->any())
            ->method('get_custom_data')
            ->willReturn($customdataval);

        // Mock the DB calls: the instance record lookup and the studentemailed-issue check.
        $DB = $this->createMock(get_class($DB));
        $DB->method('get_record')
            ->willReturn((object)[
                'id' => 1,
                'completionemailed' => $completionemailed,
            ]);
        $DB->method('record_exists')
            ->willReturn($studentemailed);

        $customcompletion = new custom_completion($mockcminfo, 2);
        $this->assertEquals($status, $customcompletion->get_state($rule));
    }

    /**
     * get_state() must only treat studentemailed = 1 as complete; NULL (unknown/legacy) and 0
     * (retryable) are both incomplete.
     *
     * @covers \mod_customcert\completion\custom_completion::get_state
     */
    public function test_get_state_studentemailed_tri_state_against_real_db(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->enablecompletion = true;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'emailstudents' => 1,
            'completionemailed' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        $issueid = (new \mod_customcert\service\issue_repository())->create((int)$customcert->id, (int)$student->id);
        $DB->set_field('customcert_issues', 'emailed', 1, ['id' => $issueid]);

        $cminfo = get_fast_modinfo($course->id, $student->id)->get_cm($customcert->cmid);
        $customcompletion = new custom_completion($cminfo, (int)$student->id);

        $DB->set_field('customcert_issues', 'studentemailed', null, ['id' => $issueid]);
        $this->assertEquals(COMPLETION_INCOMPLETE, $customcompletion->get_state('completionemailed'));

        $DB->set_field('customcert_issues', 'studentemailed', 0, ['id' => $issueid]);
        $this->assertEquals(COMPLETION_INCOMPLETE, $customcompletion->get_state('completionemailed'));

        $DB->set_field('customcert_issues', 'studentemailed', 1, ['id' => $issueid]);
        $this->assertEquals(COMPLETION_COMPLETE, $customcompletion->get_state('completionemailed'));
    }

    /**
     * Test for get_defined_custom_rules().
     *
     * @covers \mod_customcert\completion\custom_completion::get_defined_custom_rules
     */
    public function test_get_defined_custom_rules(): void {
        $rules = custom_completion::get_defined_custom_rules();
        $this->assertCount(1, $rules);
        $this->assertEquals('completionemailed', reset($rules));
    }

    /**
     * Test for get_custom_rule_descriptions().
     *
     * @covers \mod_customcert\completion\custom_completion::get_custom_rule_descriptions
     */
    public function test_get_custom_rule_descriptions(): void {
        $rules = custom_completion::get_defined_custom_rules();

        // Build a mock cm_info instance.
        $mockcminfo = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        $customcompletion = new custom_completion($mockcminfo, 1);
        $ruledescriptions = $customcompletion->get_custom_rule_descriptions();

        $this->assertEquals(count($rules), count($ruledescriptions));
        foreach ($rules as $rule) {
            $this->assertArrayHasKey($rule, $ruledescriptions);
        }
    }

    /**
     * Test for get_sort_order().
     *
     * @covers \mod_customcert\completion\custom_completion::get_sort_order
     */
    public function test_get_sort_order(): void {
        $mockcminfo = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->getMock();

        $customcompletion = new custom_completion($mockcminfo, 1);

        $this->assertEquals(['completionview', 'completionemailed'], $customcompletion->get_sort_order());
    }

    /**
     * Test for is_defined().
     *
     * @covers \core_completion\activity_custom_completion::is_defined
     */
    public function test_is_defined(): void {
        $mockcminfo = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->getMock();

        $customcompletion = new custom_completion($mockcminfo, 1);

        $this->assertTrue($customcompletion->is_defined('completionemailed'));
        $this->assertFalse($customcompletion->is_defined('somerandomrule'));
    }
}
