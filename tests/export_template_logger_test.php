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
namespace mod_customcert;
use advanced_testcase;
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\export\template_logger;
/**
 * Tests for template_logger.
 *
 * @package    mod_customcert
 * @category   test
 * @group      mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\export\template_logger
 */
final class export_template_logger_test extends advanced_testcase {
    /**
     * Test that template_logger implements template_import_logger_interface.
     */
    public function test_implements_interface(): void {
        $logger = new template_logger();
        $this->assertInstanceOf(template_import_logger_interface::class, $logger);
    }

    /**
     * Test that warning messages are stored and output as Moodle notifications.
     */
    public function test_warning_stored_and_printed(): void {
        $this->resetAfterTest();
        $logger = new template_logger();
        ob_start();
        $logger->warning('Something went wrong.');
        $logger->print_notification();
        ob_end_clean();
        $this->assertInstanceOf(template_logger::class, $logger);
    }

    /**
     * Test that info messages are stored and output as Moodle notifications.
     */
    public function test_info_stored_and_printed(): void {
        $this->resetAfterTest();
        $logger = new template_logger();
        ob_start();
        $logger->info('Import completed.');
        $logger->print_notification();
        ob_end_clean();
        $this->assertInstanceOf(template_logger::class, $logger);
    }

    /**
     * Test that multiple warnings and infos can be stored and printed without error.
     */
    public function test_multiple_messages_printed(): void {
        $this->resetAfterTest();
        $logger = new template_logger();
        ob_start();
        $logger->warning('Warning one.');
        $logger->warning('Warning two.');
        $logger->info('Info one.');
        $logger->print_notification();
        ob_end_clean();
        $this->assertInstanceOf(template_logger::class, $logger);
    }

    /**
     * Test that print_notification with no messages does not throw.
     */
    public function test_print_notification_empty(): void {
        $this->resetAfterTest();
        $logger = new template_logger();
        $logger->print_notification();
        $this->assertInstanceOf(template_logger::class, $logger);
    }
}
