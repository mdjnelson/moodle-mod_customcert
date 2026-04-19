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

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use cm_info;
use coding_exception;
use mod_customcert\completion\custom_completion;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Unit tests for mod_customcert/completion/custom_completion.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\completion\custom_completion
 */
final class custom_completion_test extends advanced_testcase {
    /**
     * Data provider for {@see test_get_state()}.
     *
     * @return array[]
     */
    public static function get_state_provider(): array {
        return [
            'Undefined rule throws coding_exception' => [
                'rule'               => 'somenonexistentrule',
                'completionemailed'  => 0,
                'emailed'            => false,
                'expectedstate'      => null,
                'expectedexception'  => coding_exception::class,
            ],
            'Rule disabled on instance — moodle_exception' => [
                'rule'               => 'completionemailed',
                'completionemailed'  => 0,
                'emailed'            => false,
                'expectedstate'      => null,
                'expectedexception'  => \moodle_exception::class,
            ],
            'Rule enabled, certificate not emailed — incomplete' => [
                'rule'               => 'completionemailed',
                'completionemailed'  => 1,
                'emailed'            => false,
                'expectedstate'      => COMPLETION_INCOMPLETE,
                'expectedexception'  => null,
            ],
            'Rule enabled, certificate emailed — complete' => [
                'rule'               => 'completionemailed',
                'completionemailed'  => 1,
                'emailed'            => true,
                'expectedstate'      => COMPLETION_COMPLETE,
                'expectedexception'  => null,
            ],
        ];
    }

    /**
     * Test get_state() for all scenarios.
     *
     * @dataProvider get_state_provider
     * @covers ::get_state
     * @param string   $rule              The completion rule name to evaluate.
     * @param int      $completionemailed The value of customcert.completionemailed for the instance.
     * @param bool     $emailed           Whether a matching emailed issue record exists.
     * @param int|null $expectedstate     Expected return value of get_state().
     * @param string|null $expectedexception  Expected exception class, or null.
     */
    public function test_get_state(
        string $rule,
        int $completionemailed,
        bool $emailed,
        ?int $expectedstate,
        ?string $expectedexception
    ): void {
        global $DB;

        if ($expectedexception !== null) {
            $this->expectException($expectedexception);
        }

        // Build a mock cm_info that returns a fixed instance id.
        $mockcm = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        $mockcm->expects($this->any())
            ->method('__get')
            ->willReturnMap([
                ['instance', 42],
                ['customdata', ['customcompletionrules' => ['completionemailed' => $completionemailed]]],
            ]);

        // Mock the DB so no real database is needed.
        $DB = $this->createMock(get_class($DB));

        $certrecord = (object)['id' => 42, 'completionemailed' => $completionemailed];
        $DB->expects($this->any())
            ->method('get_record')
            ->willReturn($certrecord);

        $DB->expects($this->any())
            ->method('record_exists')
            ->willReturn($emailed);

        $customcompletion = new custom_completion($mockcm, 7);
        $this->assertSame($expectedstate, $customcompletion->get_state($rule));
    }

    /**
     * Test that get_state() does not call record_exists when the rule is disabled on the instance.
     *
     * @covers ::get_state
     */
    public function test_get_state_does_not_check_issues_when_rule_disabled(): void {
        global $DB;

        $mockcm = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        $mockcm->expects($this->any())
            ->method('__get')
            ->willReturnMap([
                ['instance', 42],
                ['customdata', ['customcompletionrules' => ['completionemailed' => 0]]],
            ]);

        $DB = $this->createMock(get_class($DB));

        // Record_exists must never be called when the rule is disabled.
        $DB->expects($this->never())
            ->method('record_exists');

        $customcompletion = new custom_completion($mockcm, 7);
        $this->expectException(\moodle_exception::class);
        $customcompletion->get_state('completionemailed');
    }

    /**
     * Test get_defined_custom_rules() returns exactly the expected rule.
     *
     * @covers ::get_defined_custom_rules
     */
    public function test_get_defined_custom_rules(): void {
        $rules = custom_completion::get_defined_custom_rules();
        $this->assertCount(1, $rules);
        $this->assertContains('completionemailed', $rules);
    }

    /**
     * Test get_custom_rule_descriptions() returns a description for every defined rule.
     *
     * @covers ::get_custom_rule_descriptions
     */
    public function test_get_custom_rule_descriptions(): void {
        $mockcm = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->getMock();

        $customcompletion = new custom_completion($mockcm, 1);
        $descriptions = $customcompletion->get_custom_rule_descriptions();

        $rules = custom_completion::get_defined_custom_rules();
        $this->assertCount(count($rules), $descriptions);
        foreach ($rules as $rule) {
            $this->assertArrayHasKey($rule, $descriptions);
            $this->assertNotEmpty($descriptions[$rule]);
        }
    }

    /**
     * Test get_sort_order() contains all defined custom rules plus completionview.
     *
     * @covers ::get_sort_order
     */
    public function test_get_sort_order(): void {
        $mockcm = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->getMock();

        $customcompletion = new custom_completion($mockcm, 1);
        $sortorder = $customcompletion->get_sort_order();

        $this->assertIsArray($sortorder);
        $this->assertContains('completionview', $sortorder);
        $this->assertContains('completionemailed', $sortorder);
    }

    /**
     * Test is_defined() returns true for the known rule and false for an unknown one.
     *
     * @covers \core_completion\activity_custom_completion::is_defined
     */
    public function test_is_defined(): void {
        $mockcm = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->getMock();

        $customcompletion = new custom_completion($mockcm, 1);

        $this->assertTrue($customcompletion->is_defined('completionemailed'));
        $this->assertFalse($customcompletion->is_defined('somerandomrule'));
    }

    /**
     * Data provider for {@see test_get_available_custom_rules()}.
     *
     * @return array[]
     */
    public static function get_available_custom_rules_provider(): array {
        return [
            'Rule enabled'  => [COMPLETION_ENABLED, ['completionemailed']],
            'Rule disabled' => [COMPLETION_DISABLED, []],
        ];
    }

    /**
     * Test get_available_custom_rules() respects the enabled/disabled state stored in customdata.
     *
     * @dataProvider get_available_custom_rules_provider
     * @covers \core_completion\activity_custom_completion::get_available_custom_rules
     * @param int   $status   COMPLETION_ENABLED or COMPLETION_DISABLED.
     * @param array $expected Expected list of available rules.
     */
    public function test_get_available_custom_rules(int $status, array $expected): void {
        $customdata = ['customcompletionrules' => ['completionemailed' => $status]];

        $mockcm = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        $mockcm->expects($this->any())
            ->method('__get')
            ->with('customdata')
            ->willReturn($customdata);

        $customcompletion = new custom_completion($mockcm, 1);
        $this->assertSame($expected, $customcompletion->get_available_custom_rules());
    }

    /**
     * Integration test: get_state() returns COMPLETE for a real user once their issue is marked emailed.
     *
     * @covers ::get_state
     */
    public function test_get_state_integration_complete_when_emailed(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $customcert = $this->getDataGenerator()->create_module(
            'customcert',
            ['course' => $course->id, 'completionemailed' => 1, 'completion' => COMPLETION_TRACKING_AUTOMATIC]
        );
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        [$course, $cm] = get_course_and_cm_from_instance($customcert->id, 'customcert', $course->id);

        // No issue yet — should be incomplete.
        $customcompletion = new custom_completion($cm, (int)$user->id);
        $this->assertSame(COMPLETION_INCOMPLETE, $customcompletion->get_state('completionemailed'));

        // Insert an issue record with emailed = 1.
        $DB->insert_record('customcert_issues', (object)[
            'customcertid' => $customcert->id,
            'userid'       => $user->id,
            'timecreated'  => time(),
            'emailed'      => 1,
            'code'         => 'TESTCODE123',
        ]);

        // Now should be complete.
        $this->assertSame(COMPLETION_COMPLETE, $customcompletion->get_state('completionemailed'));
    }

    /**
     * Integration test: get_state() stays INCOMPLETE when issue exists but emailed = 0.
     *
     * @covers ::get_state
     */
    public function test_get_state_integration_incomplete_when_not_emailed(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $customcert = $this->getDataGenerator()->create_module(
            'customcert',
            ['course' => $course->id, 'completionemailed' => 1, 'completion' => COMPLETION_TRACKING_AUTOMATIC]
        );
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        [$course, $cm] = get_course_and_cm_from_instance($customcert->id, 'customcert', $course->id);

        // Insert an issue record with emailed = 0 (issued but not yet emailed).
        $DB->insert_record('customcert_issues', (object)[
            'customcertid' => $customcert->id,
            'userid'       => $user->id,
            'timecreated'  => time(),
            'emailed'      => 0,
            'code'         => 'TESTCODE456',
        ]);

        $customcompletion = new custom_completion($cm, (int)$user->id);
        $this->assertSame(COMPLETION_INCOMPLETE, $customcompletion->get_state('completionemailed'));
    }
}
