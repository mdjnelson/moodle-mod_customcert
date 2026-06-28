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

declare(strict_types=1);

namespace mod_customcert\element;

use html_writer;
use mod_customcert\element;
use mod_customcert\edit_element_form;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Placeholder element used when an element type is unknown or missing.
 * Renders a visible warning in HTML preview; renders nothing to PDF.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class unknown_element extends element implements element_interface, form_element_interface, renderable_element_interface {
    /** @var string */
    private string $unknowntype;

    /**
     * Constructor.
     *
     * @param stdClass $record Raw DB record for the unknown element
     * @param string $unknowntype The missing type string
     */
    public function __construct(stdClass $record, string $unknowntype) {
        parent::__construct($record);
        $this->unknowntype = $unknowntype;
    }

    /**
     * No-op form builder — unknown elements have no editable fields.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
    }

    /**
     * No-op — unknown elements do not use an edit form.
     *
     * @param edit_element_form $editelementform
     * @return void
     */
    public function set_edit_element_form(edit_element_form $editelementform): void {
    }

    /**
     * Not supported — unknown elements do not have an edit form.
     *
     * @return edit_element_form
     * @throws \coding_exception always
     */
    public function get_edit_element_form(): edit_element_form {
        throw new \coding_exception('unknown_element does not support edit forms.');
    }

    /**
     * Renders the element to PDF.
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @param element_renderer|null $renderer
     * @return void
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        // Do not render anything to PDF to avoid altering certificate output.
    }

    /**
     * Renders the element to HTML.
     *
     * @param element_renderer|null $renderer
     * @return string
     * @throws \coding_exception
     */
    public function render_html(?element_renderer $renderer = null): string {
        $label = get_string('unknownelementtype', 'mod_customcert');
        $message = $label . ': ' . s($this->unknowntype);
        $content = html_writer::div(
            html_writer::tag('strong', $message),
            'customcert-unknown-element'
        );
        // Unknown_element extends mod_customcert\element which implements both
        // stylable_element_interface and layout_element_interface, so we can delegate
        // to render_content() to preserve the element's stored position in the designer preview.
        if ($renderer) {
            return (string) $renderer->render_content($this, $content);
        }
        return \mod_customcert\element_helper::render_html_content($this, $content);
    }
}
