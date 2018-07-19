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
 * This file contains the instance add/edit form.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Instance add/edit form.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_customcert_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        global $CFG;

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'customcert'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('description', 'customcert'));

        $optionsheader = $mform->createElement('header', 'options', get_string('options', 'customcert'));

        if (has_capability('mod/customcert:manageemailstudents', $this->get_context())) {
            $mform->addElement('selectyesno', 'emailstudents', get_string('emailstudents', 'customcert'));
            $mform->setDefault('emailstudents', 0);
            $mform->addHelpButton('emailstudents', 'emailstudents', 'customcert');
            $firstoption = 'emailstudents';
        } else {
            $mform->addElement('hidden', 'emailstudents', 0);
            $mform->setType('emailstudents', PARAM_INT);
        }

        if (has_capability('mod/customcert:manageemailteachers', $this->get_context())) {
            $mform->addElement('selectyesno', 'emailteachers', get_string('emailteachers', 'customcert'));
            $mform->setDefault('emailteachers', 0);
            $mform->addHelpButton('emailteachers', 'emailteachers', 'customcert');
            $firstoption = empty($firstoption) ? 'emailteachers' : $firstoption;
        } else {
            $mform->addElement('hidden', 'emailteachers', 0);
            $mform->setType('emailstudents', PARAM_INT);
        }

        if (has_capability('mod/customcert:manageemailothers', $this->get_context())) {
            $mform->addElement('text', 'emailothers', get_string('emailothers', 'customcert'), array('size' => '40'));
            $mform->setType('emailothers', PARAM_TEXT);
            $mform->addHelpButton('emailothers', 'emailothers', 'customcert');
            $firstoption = empty($firstoption) ? 'emailothers' : $firstoption;
        } else {
            $mform->addElement('hidden', 'emailothers', '');
            $mform->setType('emailothers', PARAM_TEXT);
        }

        if (has_capability('mod/customcert:manageverifyany', $this->get_context())) {
            $mform->addElement('selectyesno', 'verifyany', get_string('verifycertificateanyone', 'customcert'));
            $mform->setType('verifyany', 0);
            $mform->addHelpButton('verifyany', 'verifycertificateanyone', 'customcert');
            $firstoption = empty($firstoption) ? 'verifyany' : $firstoption;
        } else {
            $mform->addElement('hidden', 'verifyany', 0);
            $mform->setType('emailothers', PARAM_INT);
        }

        if (has_capability('mod/customcert:managerequiredtime', $this->get_context())) {
            $mform->addElement('text', 'requiredtime', get_string('coursetimereq', 'customcert'), array('size' => '3'));
            $mform->setType('requiredtime', PARAM_INT);
            $mform->addHelpButton('requiredtime', 'coursetimereq', 'customcert');
            $firstoption = empty($firstoption) ? 'requiredtime' : $firstoption;
        } else {
            $mform->addElement('hidden', 'requiredtime', 0);
            $mform->setType('requiredtime', PARAM_INT);
        }

        if (has_capability('mod/customcert:manageprotection', $this->get_context())) {
            $mform->addElement('checkbox', 'protection_print', get_string('setprotection', 'customcert'),
                get_string('print', 'customcert'));
            $mform->addElement('checkbox', 'protection_modify', '', get_string('modify', 'customcert'));
            $mform->addElement('checkbox', 'protection_copy', '', get_string('copy', 'customcert'));
            $mform->addHelpButton('protection_print', 'setprotection', 'customcert');
            $firstoption = empty($firstoption) ? 'protection_print' : $firstoption;
        } else {
            $mform->addElement('hidden', 'protection_print', 0);
            $mform->addElement('hidden', 'protection_modify', 0);
            $mform->addElement('hidden', 'protection_copy', 0);
            $mform->setType('protection_print', PARAM_INT);
            $mform->setType('protection_modify', PARAM_INT);
            $mform->setType('protection_copy', PARAM_INT);
        }

        if (!empty($firstoption)) {
            $mform->insertElementBefore($optionsheader, $firstoption);
        }

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Any data processing needed before the form is displayed.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        if (!empty($defaultvalues['protection'])) {
            $protection = explode(', ', $defaultvalues['protection']);
            // Set the values in the form to what has been set in database.
            if (in_array(\mod_customcert\certificate::PROTECTION_PRINT, $protection)) {
                $defaultvalues['protection_print'] = 1;
            }
            if (in_array(\mod_customcert\certificate::PROTECTION_MODIFY, $protection)) {
                $defaultvalues['protection_modify'] = 1;
            }
            if (in_array(\mod_customcert\certificate::PROTECTION_COPY, $protection)) {
                $defaultvalues['protection_copy'] = 1;
            }
        }
    }

    /**
     * Some basic validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check that the required time entered is valid if it was entered at all.
        if (!empty($data['requiredtime'])) {
            if ((!is_number($data['requiredtime']) || $data['requiredtime'] < 0)) {
                $errors['requiredtime'] = get_string('requiredtimenotvalid', 'customcert');
            }
        }

        return $errors;
    }
}
