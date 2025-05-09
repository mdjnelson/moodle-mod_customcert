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

/**
 * Event triggered when a certificate issue is deleted.
 *
 * @package    mod_customcert
 * @copyright  2023 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\event;

/**
 * Event triggered when a certificate issue is deleted.
 *
 * This class defines the event for when a record is deleted from the `customcert_issues` table.
 *
 * @package    mod_customcert
 */
class certificate_deleted extends \core\event\base {

    /**
     * Initialize event properties.
     *
     * Sets the CRUD type to 'd' (delete), the educational level to LEVEL_OTHER,
     * and the object table this event is related to.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'customcert_issues';
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
        return "The certificate issue with id '{$this->objectid}' was deleted. 
        It belonged to user with id '{$this->relateduserid}'.";
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
