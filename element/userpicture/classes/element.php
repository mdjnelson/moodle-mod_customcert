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
 * This file contains the customcert element userpicture's core interaction API.
 *
 * @package    customcertelement_userpicture
 * @copyright  2017 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_userpicture;

use context_user;
use html_writer;
use mod_customcert\element as base_element;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;
use user_picture;

/**
 * The customcert element userpicture's core interaction API.
 *
 * @package    customcertelement_userpicture
 * @copyright  2017 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    persistable_element_interface,
    preparable_form_interface
{
    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        element_helper::render_form_element_width($mform);
        element_helper::render_form_element_height($mform);
        if (get_config('customcert', 'showposxy')) {
            element_helper::render_form_element_position($mform);
        }
    }

    /**
     * Normalise user picture element data.
     *
     * @param stdClass $formdata Form submission data
     * @return array JSON-serialisable payload
     */
    public function normalise_data(stdClass $formdata): array {
        return [
            'width' => isset($formdata->width) ? (int)$formdata->width : 0,
            'height' => isset($formdata->height) ? (int)$formdata->height : 0,
        ];
    }

    /**
     * Prepare the form by populating the width and height fields from stored data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        $payload = $this->get_payload();
        if (isset($payload['width'])) {
            // Use defaults so values persist through Moodle's set_data lifecycle.
            $mform->setDefault('width', (int)$payload['width']);
        }
        if (isset($payload['height'])) {
            $mform->setDefault('height', (int)$payload['height']);
        }
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
        global $CFG;

        if (empty($this->get_data())) {
            return;
        }

        $payload = $this->get_payload();

        $context = context_user::instance($user->id);

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'user', 'icon', 0);

        $file = null;
        foreach ($files as $filefound) {
            if (!$filefound->is_directory()) {
                $file = $filefound;
                break;
            }
        }

        if ($file) {
            $location = make_request_directory() . '/target';
            $file->copy_content_to($location);
            $pdf->Image($location, $this->get_posx(), $this->get_posy(), (int)$payload['width'], (int)$payload['height']);
        } else if ($preview) { // Can't find an image, but we are in preview mode then display default pic.
            $location = $CFG->dirroot . '/pix/u/f1.png';
            $pdf->Image($location, $this->get_posx(), $this->get_posy(), (int)$payload['width'], (int)$payload['height']);
        }
    }

    /**
     * Render the element in html for the drag and drop interface.
     *
     * @param element_renderer|null $renderer the renderer service
     * @return string the html
     */
    public function render_html(?element_renderer $renderer = null): string {
        global $PAGE, $USER;

        if (empty($this->get_data())) {
            return '';
        }

        $payload = $this->get_payload();

        $userpicture = new user_picture($USER);
        $userpicture->size = 1;
        $url = $userpicture->get_url($PAGE)->out(false);

        $style = '';
        if ((int)($payload['width'] ?? 0) === 0 && (int)($payload['height'] ?? 0) === 0) {
            // Neither dimension is set, so no style is applied.
            $style .= '';
        } else if ((int)($payload['width'] ?? 0) === 0) { // Then the height must be set.
            $style .= 'width: ' . (int)$payload['height'] . 'mm; ';
            $style .= 'height: ' . (int)$payload['height'] . 'mm';
        } else if ((int)($payload['height'] ?? 0) === 0) { // Then the width must be set.
            $style .= 'width: ' . (int)$payload['width'] . 'mm; ';
            $style .= 'height: ' . (int)$payload['width'] . 'mm';
        } else { // Must both be set.
            $style .= 'width: ' . (int)$payload['width'] . 'mm; ';
            $style .= 'height: ' . (int)$payload['height'] . 'mm';
        }

        $content = html_writer::tag('img', '', ['src' => $url, 'style' => $style]);

        if ($renderer) {
            return (string) $renderer->render_content($this, $content);
        }

        return $content;
    }
}
