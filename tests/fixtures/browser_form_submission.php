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

/**
 * Builds save_element values the way the rearranger modal does.
 *
 * The modal serialises the real form with FormData, so the form's own submission marker and sesskey field are
 * included, and the AJAX request carries the sesskey in its query string.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait browser_form_submission {
    /**
     * Returns the name/value pairs of a browser submission of the element edit form.
     *
     * @param array $pairs List of ['name' => string, 'value' => string] pairs entered by the user.
     * @param bool $marker Whether to include the form's hidden submission marker.
     * @return array
     */
    protected function browser_submission(array $pairs, bool $marker = true): array {
        $_GET['sesskey'] = sesskey();

        $hidden = [['name' => 'sesskey', 'value' => sesskey()]];
        if ($marker) {
            array_unshift($hidden, ['name' => '_qf__mod_customcert_edit_element_form', 'value' => '1']);
        }

        return array_merge($hidden, $pairs);
    }
}
