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

namespace mod_customcert\export\datatypes;

use Exception;

/**
 * Represents a recoverable exception during certificate export/import formatting.
 *
 * This exception is thrown when a formatting-related issue occurs that should
 * be caught and handled gracefully, such as invalid field data or mismatched input.
 * A default value will be applied.
 *
 * @package    mod_customcert
 * @copyright  2026, onCampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_exception extends Exception {
}
