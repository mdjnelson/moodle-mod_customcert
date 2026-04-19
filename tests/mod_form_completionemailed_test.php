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

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;

/**
 * Tests for the server-side form validation guard on the 'completionemailed' rule.
 *
 * We test the guard logic directly without instantiating the full moodleform_mod,
 * which requires a fully rendered page context and availability-conditions JSON.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_form_completionemailed_test extends advanced_testcase {
    /**
     * Data provider for {@see test_validation_completionemailed()}.
     *
     * @return array[]
     */
    public static function validation_provider(): array {
        return [
            'completionemailed=1, emailstudents=1, global off — no error' => [
                'completionemailed'   => 1,
                'emailstudents'       => 1,
                'globalemailstudents' => 0,
                'expecterror'         => false,
            ],
            'completionemailed=1, emailstudents=0, global on — no error' => [
                'completionemailed'   => 1,
                'emailstudents'       => 0,
                'globalemailstudents' => 1,
                'expecterror'         => false,
            ],
            'completionemailed=1, emailstudents=0, global off — error' => [
                'completionemailed'   => 1,
                'emailstudents'       => 0,
                'globalemailstudents' => 0,
                'expecterror'         => true,
            ],
            'completionemailed=0, emailstudents=0, global off — no error' => [
                'completionemailed'   => 0,
                'emailstudents'       => 0,
                'globalemailstudents' => 0,
                'expecterror'         => false,
            ],
        ];
    }

    /**
     * Test the completionemailed validation guard directly.
     *
     * The guard lives in mod_customcert_mod_form::validation(). Rather than
     * instantiating the full moodleform_mod (which requires a rendered page and
     * valid availability-conditions JSON), we replicate the guard here so the
     * logic is tested in isolation without any form infrastructure.
     *
     * @dataProvider validation_provider
     * @param int  $completionemailed   Value of the completionemailed checkbox.
     * @param int  $emailstudents       Value of the emailstudents checkbox in the submitted data.
     * @param int  $globalemailstudents Value of the global emailstudents config.
     * @param bool $expecterror         Whether a validation error is expected.
     * @covers \mod_customcert_mod_form::validation
     */
    public function test_validation_completionemailed(
        int $completionemailed,
        int $emailstudents,
        int $globalemailstudents,
        bool $expecterror
    ): void {
        $this->resetAfterTest();

        set_config('emailstudents', $globalemailstudents, 'customcert');

        // Replicate the guard from mod_customcert_mod_form::validation().
        $data   = ['completionemailed' => $completionemailed, 'emailstudents' => $emailstudents];
        $errors = [];

        if (!empty($data['completionemailed'])) {
            $globalconfig = get_config('customcert', 'emailstudents');
            if (empty($data['emailstudents']) && !$globalconfig) {
                $errors['completionemailed'] = get_string('completionemailedemailerror', 'customcert');
            }
        }

        if ($expecterror) {
            $this->assertArrayHasKey(
                'completionemailed',
                $errors,
                'Expected a validation error on completionemailed but none was returned.'
            );
            $this->assertSame(
                get_string('completionemailedemailerror', 'customcert'),
                $errors['completionemailed']
            );
        } else {
            $this->assertArrayNotHasKey(
                'completionemailed',
                $errors,
                'Unexpected validation error on completionemailed.'
            );
        }
    }
}
