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
 * Fixture: legacy element whose constructor deliberately fails (#974).
 *
 * Used to prove that the restored historical static factory lets a broken plugin's
 * constructor failure propagate, rather than converting it to false as if the plugin
 * were simply missing.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_legacythrows974;

/**
 * Legacy element with a deliberately broken constructor.
 */
class element extends \mod_customcert\element {
    /**
     * Constructor that always throws, simulating a broken third-party plugin.
     *
     * @param \stdClass $element
     */
    public function __construct($element) {
        unset($element);
        throw new \Exception('Deliberate constructor failure for #974 regression coverage.');
    }
}
