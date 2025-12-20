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
 * Tests for factory behavior with migrated vs legacy elements.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use customcertelement_image\element as image_element;
use customcertelement_date\element as date_element;
use customcertelement_text\element as text_element;
use customcertelement_qrcode\element as qrcode_element;
use customcertelement_teachername\element as teachername_element;
use customcertelement_userpicture\element as userpicture_element;
use customcertelement_userfield\element as userfield_element;
use customcertelement_coursename\element as coursename_element;
use customcertelement_code\element as code_element;
use customcertelement_bgimage\element as bgimage_element;
use customcertelement_border\element as border_element;
use customcertelement_categoryname\element as categoryname_element;
use customcertelement_coursefield\element as coursefield_element;
use customcertelement_daterange\element as daterange_element;
use customcertelement_digitalsignature\element as digitalsignature_element;
use customcertelement_expiry\element as expiry_element;
use customcertelement_grade\element as grade_element;
use customcertelement_gradeitemname\element as gradeitemname_element;
use customcertelement_studentname\element as studentname_element;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;

/**
 * Tests for factory behavior with migrated vs legacy elements.
 *
 * @package    mod_customcert
 * @category   test
 */
final class element_factory_migration_test extends advanced_testcase {
    /**
     * Ensure the factory returns the migrated element directly for all migrated types.
     *
     * @covers \mod_customcert\service\element_factory::create
     */
    public function test_create_returns_concrete_for_migrated_elements(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        // Register element types used in this test.
        $registry->register('text', text_element::class);
        $registry->register('image', image_element::class);
        $registry->register('date', date_element::class);
        $registry->register('qrcode', qrcode_element::class);
        $registry->register('teachername', teachername_element::class);
        $registry->register('userpicture', userpicture_element::class);
        $registry->register('userfield', userfield_element::class);
        $registry->register('coursename', coursename_element::class);
        $registry->register('code', code_element::class);
        $registry->register('bgimage', bgimage_element::class);
        $registry->register('border', border_element::class);
        $registry->register('categoryname', categoryname_element::class);
        $registry->register('coursefield', coursefield_element::class);
        $registry->register('daterange', daterange_element::class);
        $registry->register('digitalsignature', digitalsignature_element::class);
        $registry->register('expiry', expiry_element::class);
        $registry->register('grade', grade_element::class);
        $registry->register('gradeitemname', gradeitemname_element::class);
        $registry->register('studentname', studentname_element::class);

        $factory = new element_factory($registry);

        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Example',
            'data' => '',
            'font' => null,
            'fontsize' => null,
            'colour' => null,
            'posx' => null,
            'posy' => null,
            'width' => null,
            'refpoint' => null,
            'alignment' => 'L',
        ];

        $migrated = [
            'text' => text_element::class,
            'image' => image_element::class,
            'date' => date_element::class,
            'qrcode' => qrcode_element::class,
            'teachername' => teachername_element::class,
            'userpicture' => userpicture_element::class,
            'userfield' => userfield_element::class,
            'coursename' => coursename_element::class,
            'code' => code_element::class,
            'bgimage' => bgimage_element::class,
            'border' => border_element::class,
            'categoryname' => categoryname_element::class,
            'coursefield' => coursefield_element::class,
            'daterange' => daterange_element::class,
            'digitalsignature' => digitalsignature_element::class,
            'expiry' => expiry_element::class,
            'grade' => grade_element::class,
            'gradeitemname' => gradeitemname_element::class,
            'studentname' => studentname_element::class,
        ];

        foreach ($migrated as $type => $expectedclass) {
            $instance = $factory->create($type, $record);
            $this->assertInstanceOf(
                $expectedclass,
                $instance,
                "Factory should return concrete instance for '{$type}'"
            );
        }
    }
}
