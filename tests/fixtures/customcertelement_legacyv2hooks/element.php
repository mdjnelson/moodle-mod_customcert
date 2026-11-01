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
 * Fixture: historical-style element that adopts optional 5.2 v2 hooks without renderable_element_interface.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_legacyv2hooks;

use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\validatable_element_interface;
use MoodleQuickForm;
use stdClass;

/**
 * Untyped historical rendering with optional v2 persistence, preparation and validation hooks.
 */
class element extends \mod_customcert\element implements
    persistable_element_interface,
    preparable_form_interface,
    validatable_element_interface
{
    /** @var bool Whether prepare_form() was called. */
    public $prepared = false;

    /**
     * Build the form.
     *
     * @param MoodleQuickForm $mform
     */
    public function build_form(MoodleQuickForm $mform): void {
        $mform->addElement('text', 'v2value', 'Value');
        $mform->setType('v2value', PARAM_TEXT);
    }

    /**
     * Normalise the form data.
     *
     * @param stdClass $formdata
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        return ['v2value' => (string) ($formdata->v2value ?? ''), 'nested' => ['a' => 1]];
    }

    /**
     * Prepare the form.
     *
     * @param MoodleQuickForm $mform
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        $this->prepared = true;
    }

    /**
     * Validate the submitted data.
     *
     * @param array $data
     * @return array
     */
    public function validate(array $data): array {
        return (($data['v2value'] ?? '') === 'bad') ? ['v2value' => 'Invalid value'] : [];
    }

    /**
     * Render the element on the PDF.
     *
     * @param \pdf $pdf
     * @param bool $preview
     * @param \stdClass $user
     */
    public function render($pdf, $preview, $user) {
    }

    /**
     * Render the element as HTML.
     *
     * @return string
     */
    public function render_html() {
        return 'v2hooks';
    }
}
