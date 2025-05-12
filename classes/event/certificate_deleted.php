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

namespace mod_customcert\event;

// Prevent direct access to this file.
defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a certificate issue is deleted.
 *
 * @package   mod_customcert
 * @copyright 2025 William Entriken <@fulldecent>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_deleted extends \core\event\base {

    /**
     * Initialises the event.
     *
     */
    protected function init() {
        $this->data['crud'] = 'd'; // A 'delete' operation.
        $this->data['edulevel'] = self::LEVEL_OTHER; // Not teaching, participation, etc.
        $this->data['objecttable'] = 'customcert_issues'; // The DB table this event pertains to.
    }

    /**
     * Returns the localized name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcertificatedeleted', 'mod_customcert');
    }

    /**
     * Returns a description of the event for logging/debugging.
     *
     * @return string
     */
    public function get_description() {
        return "The certificate issue with id '{$this->objectid}' was deleted. It belonged to user with id '{$this->relateduserid}'.";
    }

    /**
     * Returns a relevant URL for viewing more information about the event.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/customcert/view.php', ['id' => $this->contextinstanceid]);
    }
}
