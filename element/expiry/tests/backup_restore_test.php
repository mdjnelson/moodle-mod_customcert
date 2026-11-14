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
 * Tests backing up and restoring the expiry element.
 *
 * @package    customcertelement_expiry
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_expiry;

use restore_date_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');

/**
 * Tests backing up and restoring the expiry element.
 *
 * @package    customcertelement_expiry
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_restore_test extends restore_date_testcase {
    /**
     * Existing (legacy) and new (custom) configurations survive a backup and restore unchanged.
     *
     * @covers \customcertelement_expiry\element::after_restore
     */
    public function test_backup_restore_preserves_configurations(): void {
        global $DB;

        [$course, $customcert] = $this->create_course_and_module('customcert', []);
        $templateid = (int) $DB->get_field('customcert', 'templateid', ['id' => $customcert->id], MUST_EXIST);
        $pageid = (int) $DB->insert_record('customcert_pages', (object) [
            'templateid' => $templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $configurations = [
            'Legacy' => ['dateitem' => '-9', 'dateformat' => 'validfor', 'startfrom' => 'award'],
            'Custom' => [
                'dateitem' => 'custom',
                'dateformat' => '2',
                'startfrom' => 'coursecomplete',
                'expiryduration' => 6,
                'expiryunit' => 'months',
            ],
        ];
        $sequence = 1;
        foreach ($configurations as $name => $data) {
            $DB->insert_record('customcert_elements', (object) [
                'pageid' => $pageid,
                'element' => 'expiry',
                'name' => $name,
                'data' => json_encode($data),
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'posx' => 10,
                'posy' => 10,
                'width' => 0,
                'refpoint' => 1,
                'alignment' => 'L',
                'sequence' => $sequence++,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        $newcourseid = $this->backup_and_restore($course);

        $newcustomcert = $DB->get_record('customcert', ['course' => $newcourseid], '*', MUST_EXIST);
        $sql = "SELECT e.*
                  FROM {customcert_elements} e
                  JOIN {customcert_pages} p ON p.id = e.pageid
                 WHERE p.templateid = :templateid AND e.element = 'expiry'";
        $restored = $DB->get_records_sql($sql, ['templateid' => $newcustomcert->templateid]);
        $this->assertCount(2, $restored);
        foreach ($restored as $element) {
            $this->assertEquals($configurations[$element->name], json_decode($element->data, true), $element->name);
        }
    }
}
