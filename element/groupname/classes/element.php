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
 * This file contains the customcert element groupname's core interaction API.
 *
 * @package    customcertelement_groupname
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_groupname;

use mod_customcert\element as base_element;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use pdf;
use stdClass;

/**
 * The customcert element groupname's core interaction API.
 *
 * @package    customcertelement_groupname
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    renderable_element_interface,
    validatable_element_interface
{
    /**
     * Build the configuration form for this element.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function build_form(\MoodleQuickForm $mform): void {
        element_helper::render_common_form_elements($mform, $this->showposxy);
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
        if ($renderer) {
            $renderer->render_content($this, $this->get_group_name($user));
        } else {
            element_helper::render_content($pdf, $this, $this->get_group_name($user));
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
        global $USER;
        $groupname = $this->get_group_name($USER);
        if ($groupname === '') {
            $groupname = get_string('groupnamedefault', 'customcertelement_groupname');
        }
        if ($renderer) {
            return (string) $renderer->render_content($this, $groupname);
        }
        return element_helper::render_html_content($this, $groupname);
    }

    /**
     * Normalise data for persistence. Group name has no custom payload.
     *
     * @param stdClass $formdata
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        return [
            'font' => (string)($formdata->font ?? ''),
            'fontsize' => (int)($formdata->fontsize ?? 0),
            'colour' => (string)($formdata->colour ?? ''),
            'width' => (int)($formdata->width ?? 0),
        ];
    }

    /**
     * Validate submitted form data for this element.
     * Core validations are handled by validation_service; no extra rules here.
     *
     * @param array $data
     * @return array<string,string>
     */
    public function validate(array $data): array {
        return [];
    }

    /**
     * Helper function that returns the group name(s) for the user in the course.
     *
     * @param stdClass $user
     * @return string
     */
    protected function get_group_name(stdClass $user): string {
        $courseid = element_helper::get_courseid($this->get_id());
        $context = element_helper::get_context($this->get_id());
        $groups = groups_get_user_groups($courseid, (int) $user->id);
        if (empty($groups[0])) {
            return '';
        }
        $names = [];
        foreach ($groups[0] as $groupid) {
            $group = groups_get_group($groupid);
            if ($group) {
                $names[] = format_string($group->name, true, ['context' => $context]);
            }
        }
        return implode(', ', $names);
    }
}
