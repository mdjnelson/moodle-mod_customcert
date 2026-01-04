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
use mod_customcert\service\element_renderer;
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
final class unknown_element extends element implements element_interface, renderable_element_interface {
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
        if ($renderer) {
            return (string)$renderer->render_content($this, $content);
        }
        return $content;
    }
}
