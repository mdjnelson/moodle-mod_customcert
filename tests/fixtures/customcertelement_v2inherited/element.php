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
 * Fixture: element updated for the released 5.2 API that relied on inheriting renderable_element_interface.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_v2inherited;

use mod_customcert\element\copyable_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\restorable_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use restore_customcert_activity_task;
use stdClass;

/**
 * Typed 5.2 rendering and optional v2 hooks, without declaring renderable_element_interface.
 */
class element extends \mod_customcert\element implements
    copyable_element_interface,
    persistable_element_interface,
    preparable_form_interface,
    restorable_element_interface,
    validatable_element_interface
{
    /** @var bool Whether prepare_form() was called. */
    public $prepared = false;

    /** @var bool Whether after_restore_from_backup() was called. */
    public $restored = false;

    /** @var bool Whether copy_from() was called. */
    public $copied = false;

    /** @var array|null Arguments received by render(). */
    public $renderargs = null;

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
        return ['v2value' => (string) ($formdata->v2value ?? ''), 'items' => [['a' => 1], ['b' => 2]]];
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
     * Handle backup restore.
     *
     * @param restore_customcert_activity_task $restore
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        $this->restored = true;
    }

    /**
     * Handle copying.
     *
     * @param stdClass $source
     * @return bool
     */
    public function copy_from(stdClass $source): bool {
        $this->copied = true;
        return true;
    }

    /**
     * Render the element on the PDF.
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @param element_renderer|null $renderer
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        $this->renderargs = [$pdf, $preview, $user, $renderer];
    }

    /**
     * Render the element as HTML.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        return 'v2inherited';
    }
}
