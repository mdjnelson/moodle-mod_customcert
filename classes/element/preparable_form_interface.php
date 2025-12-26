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

namespace mod_customcert\element;

use MoodleQuickForm;

/**
 * Elements implementing this interface can prepare their form after fields are built.
 *
 * This is typically used to set draft areas or preselect control values. The
 * `form_service` will call this hook once per request after dynamic selects are
 * populated.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface preparable_form_interface {
    /**
     * Prepare the form after all fields have been built by the form_service.
     *
     * @param MoodleQuickForm $mform The Moodle form instance to prepare.
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void;
}
