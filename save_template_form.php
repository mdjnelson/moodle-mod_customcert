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

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->libdir . '/formslib.php');

/**
 * The form for handling saving customcert templates.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_customcert_save_template_form extends moodleform {

    /**
     * Form definition.
     */
    function definition() {
        $mform =& $this->_form;

        $mform->addElement('header', 'savetemplateheader', get_string('savetemplate', 'customcert'));

        $group = array();
        $group[] = $mform->createElement('text', 'name');
        $group[] = $mform->createElement('submit', 'savetemplatesubmit', get_string('save', 'customcert'));

        $mform->addElement('group', 'savetemplategroup', get_string('templatename', 'customcert'), $group, '', false);

        // Set the template name to required and set the type.
        $mform->addGroupRule('savetemplategroup', array(
            'name' => array(
                array(null, 'required', null, 'client')
            )
        ));
        $mform->setType('name', PARAM_NOTAGS);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', $this->_customdata['cmid']);
    }

    /**
     * Some basic validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        // Ensure the name does not already exist.
        if ($DB->record_exists('customcert_template', array('name' => $data['name']))) {
            $errors['savetemplategroup'] = get_string('templatenameexists', 'customcert');
        }

        return $errors;
    }
}
