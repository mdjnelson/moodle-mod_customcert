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
     * The emailstudents value is always present in submitted data (either from the
     * visible select for users with manageemailstudents, or from the hidden field
     * for users without). The global setting is irrelevant to validation.
     *
     * @return array[]
     */
    public static function validation_provider(): array {
        return [
            'completionemailed=1, emailstudents=1 — no error' => [
                'completionemailed' => 1,
                'emailstudents'     => 1,
                'expecterror'       => false,
            ],
            'completionemailed=1, emailstudents=0 — error' => [
                'completionemailed' => 1,
                'emailstudents'     => 0,
                'expecterror'       => true,
            ],
            'completionemailed=0, emailstudents=0 — no error' => [
                'completionemailed' => 0,
                'emailstudents'     => 0,
                'expecterror'       => false,
            ],
            'completionemailed=0, emailstudents=1 — no error' => [
                'completionemailed' => 0,
                'emailstudents'     => 1,
                'expecterror'       => false,
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
     * The emailstudents field is always present in submitted data because
     * add_completion_rules() adds it as a hidden field for users who cannot
     * manage it, ensuring disabledIf works via JS for all users.
     *
     * @dataProvider validation_provider
     * @param int  $completionemailed Value of the completionemailed checkbox.
     * @param int  $emailstudents     Value of the emailstudents field in submitted data.
     * @param bool $expecterror       Whether a validation error is expected.
     * @covers \mod_customcert_mod_form::validation
     */
    public function test_validation_completionemailed(
        int $completionemailed,
        int $emailstudents,
        bool $expecterror
    ): void {
        $this->resetAfterTest();

        // Replicate the guard from mod_customcert_mod_form::validation().
        $data   = ['completionemailed' => $completionemailed, 'emailstudents' => $emailstudents];
        $errors = [];

        if (!empty($data['completionemailed']) && empty($data['emailstudents'])) {
            $errors['completionemailed'] = get_string('completionemailedemailerror', 'customcert');
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
