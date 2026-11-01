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
 * Fixture: genuine Moodle 4.5-5.1 third-party element with untyped historical signatures.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_legacy45;

/**
 * Historical element: untyped render($pdf, $preview, $user) and render_html() without arguments.
 */
class element extends \mod_customcert\element {
    /** @var array Arguments received by the last render() call. */
    public $renderargs = [];

    /** @var bool Whether after_restore() was called. */
    public $restored = false;

    /**
     * Expose the historical protected properties, as a 4.5-era plugin would read them.
     *
     * @return array
     */
    public function get_legacy_state() {
        return [
            'element' => $this->element,
            'font' => $this->font,
            'fontsize' => $this->fontsize,
            'colour' => $this->colour,
            'width' => $this->width,
        ];
    }

    /**
     * Render the form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function render_form_elements($mform) {
        $mform->addElement('text', 'legacyvalue', 'Legacy value');
        $mform->setType('legacyvalue', PARAM_TEXT);
    }

    /**
     * Set the form data when editing.
     *
     * @param \MoodleQuickForm $mform
     */
    public function definition_after_data($mform) {
        parent::definition_after_data($mform);
        $stored = json_decode((string) $this->get_data());
        if ($stored && $mform->elementExists('legacyvalue')) {
            $mform->getElement('legacyvalue')->setValue($stored->legacyvalue);
        }
    }

    /**
     * Validate the submitted data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_form_elements($data, $files) {
        $errors = [];
        if (isset($data['legacyvalue']) && $data['legacyvalue'] === 'bad') {
            $errors['legacyvalue'] = 'Invalid value';
        }
        return $errors;
    }

    /**
     * Save the unique data.
     *
     * @param \stdClass $data
     * @return string
     */
    public function save_unique_data($data) {
        return json_encode(['legacyvalue' => (string) ($data->legacyvalue ?? '')]);
    }

    /**
     * Render the element on the PDF.
     *
     * @param \pdf $pdf
     * @param bool $preview
     * @param \stdClass $user
     */
    public function render($pdf, $preview, $user) {
        $this->renderargs = func_get_args();
        $stored = json_decode((string) $this->get_data());
        \mod_customcert\element_helper::render_content($pdf, $this, (string) ($stored->legacyvalue ?? ''));
    }

    /**
     * Render the element as HTML; like historical plugins, returns null when there is no data.
     *
     * @return string
     */
    public function render_html() {
        if (empty($this->get_data())) {
            return;
        }
        $stored = json_decode((string) $this->get_data());
        return \mod_customcert\element_helper::render_html_content($this, (string) $stored->legacyvalue);
    }

    /**
     * Handle restoration.
     *
     * @param \restore_customcert_activity_task $restore
     */
    public function after_restore($restore) {
        $this->restored = true;
    }
}
