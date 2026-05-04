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

use context_course;
use context_system;
use stdClass;
use mod_customcert\element\element_interface;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\element\preparable_form_interface;
use MoodleQuickForm;

/**
 * Service for handling element forms.
 */
final class form_service {
    /**
     * @var string the print protection variable
     */
    public const string PROTECTION_PRINT = 'print';

    /**
     * @var string the modify protection variable
     */
    public const string PROTECTION_MODIFY = 'modify';

    /**
     * @var string the copy protection variable
     */
    public const string PROTECTION_COPY = 'copy';

    /**
     * Build the form for an element.
     *
     * @param MoodleQuickForm $mform
     * @param element_interface $element
     */
    public function build_form(MoodleQuickForm $mform, element_interface $element): void {
        if ($element instanceof form_element_interface) {
            $element->build_form($mform);
        }
        // Pure v2 elements that do not implement form_element_interface have no element-specific fields.
    }

    /**
     * Run element-specific form preparation at the definition_after_data() lifecycle point.
     *
     * This must be called from the form's definition_after_data() method, after
     * common fields have been populated. It handles both v2 elements (via
     * preparable_form_interface) and legacy elements (via definition_after_data()).
     *
     * @param MoodleQuickForm $mform
     * @param element_interface $element
     */
    public function prepare_after_data(MoodleQuickForm $mform, element_interface $element): void {
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
            $context = context_system::instance();
        } else if (isset($COURSE)) {
            $context = context_course::instance($COURSE->id);
        } else {
            // Fallback to system context if course is not available here.
            $context = context_system::instance();
        }

        // Known filemanager fields across elements.
        $filemanagers = ['customcertimage', 'digitalsignature'];

        foreach ($filemanagers as $fm) {
            if (array_key_exists($fm, $data) && !empty($data[$fm])) {
                self::upload_files((int) $data[$fm], $context->id, $fm === 'digitalsignature' ? 'signature' : 'image');
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

    /**
     * Handles setting the protection field for the customcert.
     *
     * @param stdClass $data
     * @return string the value to insert into the protection field
     */
    public static function set_protection(stdClass $data): string {
        $protection = [];

        if (!empty($data->protection_print)) {
            $protection[] = self::PROTECTION_PRINT;
        }
        if (!empty($data->protection_modify)) {
            $protection[] = self::PROTECTION_MODIFY;
        }
        if (!empty($data->protection_copy)) {
            $protection[] = self::PROTECTION_COPY;
        }

        return implode(', ', $protection);
    }

    /**
     * Handles uploading an image for the customcert module.
     *
     * @param int $draftitemid the draft area containing the files
     * @param int $contextid the context we are storing this image in
     * @param string $filearea identifies the file area.
     */
    public static function upload_files(int $draftitemid, int $contextid, string $filearea = 'image'): void {
        global $CFG;

        require_once($CFG->dirroot . '/lib/filelib.php');
        file_save_draft_area_files($draftitemid, $contextid, 'mod_customcert', $filearea, 0);
    }
}
