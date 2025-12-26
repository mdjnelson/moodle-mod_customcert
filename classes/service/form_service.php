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
use mod_customcert\element;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\dynamic_selects_interface;
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
     * @param element $element
     */
    public function build_form(MoodleQuickForm $mform, element $element): void {
        if ($element instanceof form_definable_interface) {
            $fields = $element->get_form_fields();

            // Render strictly in the order provided by the element using simple mappers.
            $standard = [
                'font' => fn() => element_helper::render_form_element_font($mform),
                'colour' => fn() => element_helper::render_form_element_colour($mform),
                'width' => fn() => element_helper::render_form_element_width($mform),
                'height' => fn() => element_helper::render_form_element_height($mform),
                'refpoint' => fn() => element_helper::render_form_element_refpoint($mform),
                'alignment' => fn() => element_helper::render_form_element_alignment($mform),
                // Accept legacy sequential-style declarations by key and value for standard fields.
                0 => fn() => null, // Placeholder to allow is_int($key) flow below.
            ];

            $renderedposition = false;

            foreach ($fields ?? [] as $name => $field) {
                // Enforce associative format: ['fieldname' => [config]]. Skip invalid entries.
                if (!is_string($name) || $name === '') {
                    continue;
                }
                if (!is_array($field)) {
                    $field = [];
                }

                // Render combined position controls once if either posx/posy encountered and enabled.
                if ($name === 'posx' || $name === 'posy') {
                    if (!$renderedposition && get_config('customcert', 'showposxy')) {
                        element_helper::render_form_element_position($mform);
                    }
                    $renderedposition = true;
                    continue;
                }

                // Standard mapped fields.
                if (isset($standard[$name])) {
                    $standard[$name]();
                    continue;
                }

                // Non-standard: render by type (default text).
                $this->render_by_type($mform, $field['type'] ?? 'text', $name, $field);

                // Apply common settings.
                if (isset($field['help'])) {
                    $mform->addHelpButton($name, $field['help'][0], $field['help'][1]);
                }
                if (isset($field['type_param'])) {
                    $mform->setType($name, $field['type_param']);
                }
                if (isset($field['default'])) {
                    $mform->setDefault($name, $field['default']);
                }
            }
            // Populate dynamic selects centrally if element advertises them via interface.
            if ($element instanceof dynamic_selects_interface) {
                foreach ($element->get_dynamic_selects() as $name => $provider) {
                    if (!$mform->elementExists($name)) {
                        continue;
                    }
                    // Per contract, provider is a callable returning an options array.
                    $options = (array) $provider();
                    $mform->getElement($name)->loadArray($options);
                }
            }

            // Per-element hook for non-select concerns (e.g., filemanager draft areas).
            // Use a formal interface instead of method_exists to keep the contract explicit.
            if ($element instanceof preparable_form_interface) {
                $element->prepare_form($mform);
            }
            return;
        }

        // Fallback for elements not yet migrated or third-party elements.
        $element->render_form_elements($mform);
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

    /**
     * Render a form element by type with sensible defaults.
     *
     * @param MoodleQuickForm $mform
     * @param string $type
     * @param string $name
     * @param array $field
     * @return void
     */
    private function render_by_type(MoodleQuickForm $mform, string $type, string $name, array $field): void {
        switch ($type) {
            case 'select':
                $mform->addElement('select', $name, $field['label'] ?? '', $field['options'] ?? [], $field['attributes'] ?? []);
                break;
            case 'advcheckbox':
                $mform->addElement(
                    'advcheckbox',
                    $name,
                    $field['label'] ?? '',
                    '',
                    $field['attributes'] ?? [],
                    $field['options'] ?? []
                );
                break;
            case 'filemanager':
                $mform->addElement('filemanager', $name, $field['label'] ?? '', null, $field['options'] ?? []);
                break;
            case 'editor':
                $mform->addElement('editor', $name, $field['label'] ?? '', null, $field['options'] ?? []);
                break;
            case 'header':
                $mform->addElement('header', $name, $field['label'] ?? '');
                break;
            case 'date_selector':
                $mform->addElement('date_selector', $name, $field['label'] ?? '', $field['attributes'] ?? []);
                break;
            case 'static':
                $mform->addElement('static', $name, $field['label'] ?? '', $field['text'] ?? '');
                break;
            default:
                $mform->addElement($type ?: 'text', $name, $field['label'] ?? '', $field['attributes'] ?? []);
        }
    }
}
