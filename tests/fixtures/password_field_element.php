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

namespace mod_customcert\tests\fixtures;

use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/form_element_test_element.php');

/**
 * Element fixture with password inputs, one of them nested in a group.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class password_field_element extends form_element_test_element {
    /**
     * Build the form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        $mform->addElement('text', 'label', 'Label');
        $mform->addElement('password', 'pin', 'PIN');
        $group = [$mform->createElement('passwordunmask', 'apikey', 'API key', [])];
        $mform->addGroup($group, 'keygroup', 'Key', ' ', false);
    }
}
