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
 * Fixture: third-party element written against the released Moodle 5.2 v2 contract (#1090).
 *
 * In 5.2 mod_customcert\element implemented renderable_element_interface, so such an element
 * did not declare it. It implements the optional v2 capabilities and no legacy hooks.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_inheritedv2;

use mod_customcert\element_helper;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\stylable_payload;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Released 5.2 style v2 element: structured payload, form preparation and validation.
 */
class element extends \mod_customcert\element implements
    persistable_element_interface,
    preparable_form_interface,
    validatable_element_interface {
    /** @var bool Whether normalise_data() was called. */
    public bool $normalisecalled = false;

    /**
     * Add the common style fields and the element-specific fields to the edit form.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        element_helper::render_common_form_elements($mform, $this->showposxy);

        $modes = ['stored' => 'stored', 'edited' => 'edited', 'invalid' => 'invalid'];
        $mform->addElement('select', 'mode', 'Mode', $modes);
        $mform->setType('mode', PARAM_ALPHA);

        $items = ['a' => 'a', 'b' => 'b', 'x' => 'x', 'y' => 'y', 'z' => 'z'];
        $mform->addElement('select', 'items', 'Items', $items, ['multiple' => 'multiple']);
        $mform->setType('items', PARAM_ALPHA);
    }

    /**
     * Normalise submitted form data into the structured payload.
     *
     * @param stdClass $formdata
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        $this->normalisecalled = true;
        return stylable_payload::from_form($formdata)->to_array() + [
            'mode' => (string) ($formdata->mode ?? ''),
            'items' => array_values((array) ($formdata->items ?? [])),
        ];
    }

    /**
     * Populate the form from the stored payload.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        $payload = $this->get_payload();
        if ($mform->elementExists('mode')) {
            $mform->getElement('mode')->setValue($payload['mode'] ?? '');
        }
        if ($mform->elementExists('items')) {
            $mform->getElement('items')->setValue($payload['items'] ?? []);
        }
    }

    /**
     * Validate submitted data.
     *
     * @param array $data
     * @return array
     */
    public function validate(array $data): array {
        return (($data['mode'] ?? '') === 'invalid') ? ['mode' => 'Invalid mode'] : [];
    }

    /**
     * Render the element to PDF (released Moodle 5.2 contract).
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @param element_renderer|null $renderer
     * @return void
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        unset($pdf, $preview, $user, $renderer);
    }

    /**
     * Render the element in HTML for the designer (released Moodle 5.2 contract).
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        unset($renderer);
        return 'inheritedv2';
    }
}
