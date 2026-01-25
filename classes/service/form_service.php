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
 * Service for handling element forms.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\certificate;
use mod_customcert\element\element_interface;
use mod_customcert\element\form_buildable_interface;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use moodleform;
use MoodleQuickForm;

/**
 * Service for handling element forms.
 */
class form_service {
    /**
     * Build the form for an element.
     *
     * @param MoodleQuickForm $mform
     * @param element_interface $element
     */
    public function build_form(MoodleQuickForm $mform, element_interface $element): void {
        if ($element instanceof form_buildable_interface) {
            $element->build_form($mform);
        } else {
            // Fallback for elements not yet migrated or third-party elements.
            $element->render_form_elements($mform);
        }

        // Per-element hook for form preparation (e.g., filemanager draft areas, default values).
        if ($element instanceof preparable_form_interface) {
            $element->prepare_form($mform);
        } else if ($element instanceof legacy_element_adapter) {
            // Legacy fallback: delegate to the wrapped legacy element's definition_after_data().
            $element->definition_after_data($mform);
        }
    }

    /**
     * Normalise submitted data before element save: upload drafts and clean values.
     *
     * @param array $data Submitted data (by reference)
     */
    public function normalise_submission(array &$data): void {
        global $COURSE, $SITE;

        // Determine context for file operations.
        if (isset($COURSE) && isset($SITE) && $COURSE->id == $SITE->id) {
            $context = \context_system::instance();
        } else if (isset($COURSE)) {
            $context = \context_course::instance($COURSE->id);
        } else {
            // Fallback to system context if course is not available here.
            $context = \context_system::instance();
        }

        // Known filemanager fields across elements.
        $filemanagers = ['customcertimage', 'digitalsignature'];

        foreach ($filemanagers as $fm) {
            if (array_key_exists($fm, $data) && !empty($data[$fm])) {
                certificate::upload_files((int) $data[$fm], $context->id, $fm === 'digitalsignature' ? 'signature' : 'image');
            }
        }

        // Map known select fields to canonical file metadata so save_unique_data() can simply encode.
        if (!empty($data['fileid'])) {
            $fs = get_file_storage();
            if ($file = $fs->get_file_by_id((int)$data['fileid'])) {
                $data['contextid'] = $file->get_contextid();
                $data['filearea'] = $file->get_filearea();
                $data['itemid'] = $file->get_itemid();
                $data['filepath'] = $file->get_filepath();
                $data['filename'] = $file->get_filename();
            }
        }
    }
}
