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
 * This file contains the customcert element code's core interaction API.
 *
 * @package    customcertelement_code
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_code;

use mod_customcert\certificate;
use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use pdf;
use stdClass;

/**
 * The customcert element code's core interaction API.
 *
 * @package    customcertelement_code
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements element_interface, form_definable_interface {
    /**
     * Define the configuration fields for this element.
     * Include standard fields expected by tests: Font and Position controls, etc.
     *
     * @return array
     */
    public function get_form_fields(): array {
        return [
            'font' => [],
            'colour' => [],
            'posx' => [],
            'posy' => [],
            'width' => [],
            'refpoint' => [],
            'alignment' => [],
        ];
    }
    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param stdClass $user the user we are rendering this for
     * @param element_renderer|null $renderer the renderer service
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        global $DB;

        if ($preview) {
            $code = certificate::generate_code();
        } else {
            // Get the page.
            $page = $DB->get_record('customcert_pages', ['id' => $this->get_pageid()], '*', MUST_EXIST);
            // Get the customcert this page belongs to.
            $customcert = $DB->get_record('customcert', ['templateid' => $page->templateid], '*', MUST_EXIST);
            // Now we can get the issue for this user.
            $issue = $DB->get_record(
                'customcert_issues',
                ['userid' => $user->id, 'customcertid' => $customcert->id],
                '*',
                IGNORE_MULTIPLE
            );
            $code = $issue->code;
        }

        if ($renderer) {
            $renderer->render_content($this, $code);
        } else {
            element_helper::render_content($pdf, $this, $code);
        }
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @param element_renderer|null $renderer the renderer service
     * @return string the html
     */
    public function render_html(?element_renderer $renderer = null): string {
        $code = certificate::generate_code();

        if ($renderer) {
            return (string) $renderer->render_content($this, $code);
        }

        return element_helper::render_html_content($this, $code);
    }
}
