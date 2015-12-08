<?php

// This file is part of the Certificate module for Moodle - http://moodle.org/
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
 * This file keeps track of upgrades to the customcert module
 *
 * @package    mod_customcert
 * @copyright  2015 Shamim Rezaie <rezaie@foodle.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_customcert_upgrade($oldversion=0) {

    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2015073000) {
        // Add the margin fields to customcert_pages table
        $table = new xmldb_table('customcert_pages');
        $field = new xmldb_field('margin', XMLDB_TYPE_INTEGER, 10, null, null, null, 0, 'height');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add the margin fields to customcert_template_pages table
        $table = new xmldb_table('customcert_template_pages');
        $field = new xmldb_field('margin', XMLDB_TYPE_INTEGER, 10, null, null, null, 0, 'height');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Retrieve the customcert_elements table to add some elements to it
        $table = new xmldb_table('customcert_elements');
        // Add the width fields to customcert_elements table
        $field = new xmldb_field('width', XMLDB_TYPE_INTEGER, 10, null, null, null, 0, 'posy');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Add the refpoint fields to customcert_elements table.
        $field = new xmldb_field('refpoint', XMLDB_TYPE_INTEGER, 4, null, null, null, 0, 'width');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Add the align fields to customcert_elements table.
        $field = new xmldb_field('align', XMLDB_TYPE_CHAR, 1, null, null, null, 0, 'refpoint');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Retrieve the customcert_template_elements table to add some elements to it
        $table = new xmldb_table('customcert_template_elements');
        // Add the width fields to customcert_template_elements table
        $field = new xmldb_field('width', XMLDB_TYPE_INTEGER, 10, null, null, null, 0, 'posy');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Add the refpoint fields to customcert_template_elements table.
        $field = new xmldb_field('refpoint', XMLDB_TYPE_INTEGER, 4, null, null, null, 0, 'width');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Add the align fields to customcert_template_elements table.
        $field = new xmldb_field('align', XMLDB_TYPE_CHAR, 1, null, null, null, 0, 'refpoint');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Customcert savepoint reached.
        upgrade_mod_savepoint(true, 2015073000, 'customcert');
    }

    if ($oldversion < 2015120800) {
        // Remove the align column from both the 'customcert_elements' and 'customcert_template_elements' table.
        $table = new xmldb_table('customcert_elements');
        $field = new xmldb_field('align');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $table = new xmldb_table('customcert_template_elements');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Customcert savepoint reached.
        upgrade_mod_savepoint(true, 2015120800, 'customcert');
    }

    if ($oldversion < 2015120801) {
        // Rename the 'margin' field to 'rightmargin' in the 'customcert_pages' and 'customcert_template_pages' tables.
        $table = new xmldb_table('customcert_pages');
        $field = new xmldb_field('margin', XMLDB_TYPE_INTEGER, 10, null, null, null, 0, 'height');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'rightmargin');
        }

        $table = new xmldb_table('customcert_template_pages');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'rightmargin');
        }

        // Add 'leftmargin' fields to the 'customcert_pages' and 'customcert_template_pages' tables.
        $table = new xmldb_table('customcert_pages');
        $field = new xmldb_field('leftmargin', XMLDB_TYPE_INTEGER, 10, null, null, null, 0, 'height');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('customcert_template_pages');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Customcert savepoint reached.
        upgrade_mod_savepoint(true, 2015120801, 'customcert');
    }

    return true;
}
