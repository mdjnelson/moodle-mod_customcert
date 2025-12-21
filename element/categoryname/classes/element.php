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
 * This file contains the customcert element categoryname's core interaction API.
 *
 * @package    customcertelement_categoryname
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_categoryname;

use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use pdf;
use stdClass;

/**
 * The customcert element categoryname's core interaction API.
 *
 * @package    customcertelement_categoryname
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements element_interface {
    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param stdClass $user the user we are rendering this for
     * @param element_renderer|null $renderer the renderer service
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        element_helper::render_content($pdf, $this, $this->get_category_name());
        if ($renderer) {
            $renderer->render_content($this, $this->get_category_name());
        } else {
            element_helper::render_content($pdf, $this, $this->get_category_name());
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
        if ($renderer) {
            return (string) $renderer->render_content($this, $this->get_category_name());
        }

        return element_helper::render_html_content($this, $this->get_category_name());
    }

    /**
     * Helper function that returns the category name.
     *
     * @return string
     */
    protected function get_category_name(): string {
        global $DB, $SITE;

        $courseid = element_helper::get_courseid($this->get_id());
        $course = get_course($courseid);
        $context = element_helper::get_context($this->get_id());

        // Check that there is a course category available.
        if (!empty($course->category)) {
            $categoryname = $DB->get_field('course_categories', 'name', ['id' => $course->category], MUST_EXIST);
        } else { // Must be in a site template.
            $categoryname = $SITE->fullname;
        }

        return format_string($categoryname, true, ['context' => $context]);
    }
}
