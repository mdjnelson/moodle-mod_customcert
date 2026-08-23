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
 * Upgrade code for the digitalsignature element.
 *
 * @package    customcertelement_digitalsignature
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the digitalsignature element.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool always true
 */
function xmldb_customcertelement_digitalsignature_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026042002) {
        // Encrypt any existing plaintext signaturepassword values stored in customcert_elements.
        // Values that have already been encrypted (from a previous run of this upgrade step or
        // from the new save_unique_data() code) are left untouched, making this step idempotent.
        $sql = "SELECT id, data
                  FROM {customcert_elements}
                 WHERE element = :element
                   AND " . $DB->sql_isnotempty('customcert_elements', 'data', false, false);
        $elements = $DB->get_records_sql($sql, ['element' => 'digitalsignature']);

        foreach ($elements as $element) {
            $data = json_decode($element->data, true);
            if (!is_array($data) || !array_key_exists('signaturepassword', $data) || $data['signaturepassword'] === '') {
                // Use a strict check here rather than empty(), as empty('0') is true and would
                // incorrectly skip migrating a valid password consisting solely of "0".
                continue;
            }

            $storedpassword = (string) $data['signaturepassword'];

            // Reuse the same normalisation logic applied when restoring digitalsignature elements
            // from a backup: legacy plaintext is encrypted, ciphertext that decrypts with this
            // site's key is left untouched (idempotency), and ciphertext that cannot be decrypted
            // (for example, it was encrypted with a different site's key, or is corrupt) is
            // cleared rather than encrypted again, which would otherwise double-encrypt unusable
            // ciphertext.
            $normalisedpassword = \customcertelement_digitalsignature\element::normalise_restore_password($storedpassword);

            if ($normalisedpassword !== $storedpassword) {
                $data['signaturepassword'] = $normalisedpassword;
                $DB->set_field('customcert_elements', 'data', json_encode($data), ['id' => $element->id]);
            }
        }

        upgrade_plugin_savepoint(true, 2026042002, 'customcertelement', 'digitalsignature');
    }

    return true;
}
