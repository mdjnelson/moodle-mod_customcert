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
 * Certificate template duplicated event.
 *
 * @package   mod_customcert
 * @copyright 2026 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\event;

use context_system;
use core\event\base;
use moodle_url;

/**
 * Certificate template duplicated event class.
 *
 * @package   mod_customcert
 * @copyright 2026 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_duplicated extends base {
    /**
     * Initialises the event.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'customcert_templates';
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $source = $this->other['sourceid'] ?? null;

        if ($this->contextlevel == context_system::instance()->contextlevel) {
            return "The user with id '$this->userid' duplicated the certificate template with id '$source' to new " .
                "template id '$this->objectid'.";
        }

        return "The user with id '$this->userid' duplicated the certificate template for course module '" .
            $this->contextinstanceid . "' from source id '$source' to new template id '$this->objectid'.";
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventtemplateduplicated', 'customcert');
    }

    /**
     * Returns relevant URL.
     *
     * @return moodle_url|null
     */
    public function get_url() {
        if ($this->contextlevel == context_system::instance()->contextlevel) {
            return new moodle_url('/mod/customcert/manage_templates.php');
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @return string[]
     */
    public static function get_objectid_mapping() {
        return ['db' => 'customcert_templates', 'restore' => 'customcert_templates'];
    }

    /**
     * {@inheritdoc}
     *
     * @return array|bool
     */
    public static function get_other_mapping() {
        return ['sourceid' => ['db' => 'customcert_templates', 'restore' => 'customcert_templates']];
    }
}
