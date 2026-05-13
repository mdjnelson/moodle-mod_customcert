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
 * Minimal persistable element fixture for tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\element\persistable_element_interface;
use stdClass;

/**
 * A minimal element implementing persistable_element_interface for persistence_helper tests.
 */
final class minimal_persistable_element implements persistable_element_interface {
    /**
     * Normalise incoming form data for persistence.
     *
     * @param stdClass $formdata Raw form data.
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        return ['value' => (string)($formdata->text ?? '')];
    }
}
