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
 * Event class for when a custom certificate is issued to a user.
 *
 * @package    mod_customcert
 * @copyright  2023 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event class for when a custom certificate is issued to a user.
 *
 * This event is triggered when a new row is inserted into the customcert_issues table.
 *
 * @package    mod_customcert
 */
class certificate_issued extends \core\event\base {

    /**
     * Initialize the event properties.
     *
     * - 'crud' indicates the type of action: 'c' = create
     * - 'edulevel' classifies the nature of the event. LEVEL_OTHER means it's not directly teaching-related.
     * - 'objecttable' is the table this event relates to.
     */
    protected function init() {
        $this->data['crud'] = 'c'; // Indicates a 'create' operation
        $this->data['edulevel'] = self::LEVEL_OTHER; // Not teaching, participation, etc.
        $this->data['objecttable'] = 'customcert_issues'; // The DB table this event pertains to
    }

    /**
     * Returns the localized event name.
     *
     * @return string The name of the event.
     */
    public static function get_name() {
        return get_string('eventcertificateissued', 'mod_customcert');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string A detailed description of the event.
     */
    public function get_description() {
        return "The user with id '{$this->userid}' was issued a custom certificate with issue id '{$this->objectid}'.";
    }

    /**
     * Returns the URL relevant to the event.
     *
     * @return \moodle_url A URL to view the certificate or related activity.
     */
    public function get_url() {
        return new \moodle_url('/mod/customcert/view.php', ['id' => $this->contextinstanceid]);
    }
}
