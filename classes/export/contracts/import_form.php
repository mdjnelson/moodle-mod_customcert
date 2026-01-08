<?php

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
