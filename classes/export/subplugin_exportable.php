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

namespace mod_customcert\export;

use mod_customcert\export\datatypes\field_interface;
use mod_customcert\export\datatypes\file_field_interface;
use mod_customcert\export\datatypes\format_error;
use mod_customcert\export\datatypes\format_exception;
use mod_customcert\export\datatypes\file_field;
use mod_customcert\export\datatypes\user_field;
use stored_file;

/**
 * Provides a base structure for exportable custom certificate subplugins.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class subplugin_exportable {
    /**
     * @var string Name of the plugin for debugging reasons.
     */
    protected readonly string $pluginname;
    /**
     * @var template_import_logger_interface Logger instance used for reporting import issues and notices.
     */
    protected readonly template_import_logger_interface $logger;
    /**
     * @var template_appendix_manager_interface File manager instance.
     */
    protected readonly template_appendix_manager_interface $filemng;
    /**
     * @var user_field User field instance for exporting and importing user references.
     */
    protected readonly user_field $userfield;
    /**
     * @var file_field File field instance for exporting and importing file references.
     */
    protected readonly file_field $filefield;

    /**
     * Constructor.
     *
     * @param string $pluginname Name of the plugin from the element
     * @param template_import_logger_interface $logger Logger instance
     * @param template_appendix_manager_interface $filemng File manager
     */
    public function __construct(
        string $pluginname,
        template_import_logger_interface $logger,
        template_appendix_manager_interface $filemng,
    ) {
        $this->pluginname = $pluginname;
        $this->logger = $logger;
        $this->filemng = $filemng;
        $this->userfield = new user_field();
        $this->filefield = new file_field('mod_customcert', $filemng);
    }

    /**
     * Returns the customdata fields of the subplugin
     *
     * @return field_interface[] customdata fields
     */
    abstract protected function get_fields(): array;

    /**
     * Converts raw import data to a string format suitable for subplugin storage.
     *
     * @param array $data The data to convert.
     * @return string|null Converted string.
     */
    public function convert_for_import(array $data): ?string {
        $fields = $this->get_fields();
        $customdata = [];

        foreach ($fields as $key => $field) {
            $value = $data[$key] ?? null;
            if (!is_array($value)) {
                throw new format_error("Field '$key' is missing or has an unexpected format");
            }

            try {
                $fielddata = $field->import($value);
            } catch (format_exception $e) {
                $this->logger->warning($key . ': ' . $e->getMessage());
                $fielddata = $field->get_fallback();
            }

            if (is_array($fielddata)) {
                foreach ($fielddata as $subkey => $subvalue) {
                    $customdata[str_replace('$', $subkey, $key)] = $subvalue;
                }
            } else {
                $customdata[$key] = $fielddata;
            }
        }

        return json_encode($customdata);
    }

    /**
     * Exports subplugin data.
     *
     * The exported structure will later be passed to convert_for_import again.
     *
     * @param string $customdata Subplugin custom data.
     * @return array Exported data in array format.
     */
    public function export(string $customdata): array {
        $data = $this->read_custom_data($customdata);

        $safedata = [];
        $fields = $this->get_fields();
        foreach ($fields as $key => $field) {
            $fielddata = $this->get_relevant_data($key, $data);
            $exportedfielddata = $field->export($fielddata);

            $safedata[$key] = $exportedfielddata;
        }

        return $safedata;
    }

    /**
     * Retrieves file references used by the subplugin element.
     *
     * @param string $customdata Subplugin custom data.
     * @return stored_file[] Stored files, defaulting to an empty array.
     */
    public function get_used_files(string $customdata): array {
        $fields = $this->get_fields();
        $data = $this->read_custom_data($customdata);
        $files = [];

        foreach ($fields as $key => $field) {
            if (!$field instanceof file_field_interface) {
                continue;
            }

            $fielddata = $this->get_relevant_data($key, $data);
            try {
                $file = $field->get_file($fielddata);
                if ($file !== false) {
                    $files[] = $file;
                }
            } catch (format_exception $e) {
                $this->logger->warning($key . ': ' . $e->getMessage());
            }
        }

        return $files;
    }

    /**
     * Retrieves field data from the customdata array, handling multi-key structures.
     *
     * If the field key uses `$` as a placeholder, replaces it with expected subkeys
     * and collects associated data. Otherwise, returns the direct field value.
     *
     * @param string $key Field key, potentially containing a `$` placeholder.
     * @param array $data Decoded custom data array.
     * @return mixed Single field value or associative array of file-related subkeys.
     */
    public function get_relevant_data(string $key, array $data): mixed {
        if (str_contains($key, '$')) {
            $subkeys = ['contextid', 'component', 'filearea', 'itemid', 'filepath', 'filename'];
            $fielddata = array_map(function ($subkey) use ($data, $key) {
                return $data[str_replace('$', $subkey, $key)] ?? null;
            }, $subkeys);
            $fielddata = array_combine($subkeys, $fielddata);
        } else {
            $fielddata = $data[$key] ?? null;
        }

        return $fielddata;
    }

    /**
     * Returns the custom data array from the db string
     *
     * @param string $customdata custom data from database
     * @return array Custom data as array
     */
    public function read_custom_data(string $customdata): array {
        if ($customdata === '') {
            return [];
        }

        $decoded = json_decode($customdata, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($decoded)) {
                return $decoded;
            }

            return ['value' => $decoded];
        }

        return ['value' => $customdata];
    }
}
