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

namespace mod_customcert\export\contracts;

defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/formslib.php");

use moodleform;

class import_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'filepicker',
            'backup',
            get_string('file'),
            null,
            [
                'accepted_types' => '.zip',
            ]
        );

        $mform->addElement(
            'hidden',
            'context_id',
            required_param('context_id', PARAM_INT),
        );
        $mform->setType('context_id', PARAM_INT);

        $this->add_action_buttons();
    }
}
