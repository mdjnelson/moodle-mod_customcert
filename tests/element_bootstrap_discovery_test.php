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
use mod_customcert\element\element_bootstrap;
use mod_customcert\element\provider\plugin_provider;
use mod_customcert\service\element_registry;
use mod_customcert\tests\fixtures\fake_element_fixture;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_element_fixture.php');

/**
 * Tests auto-discovery of third-party customcertelement_* plugins by element_bootstrap.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\element\element_bootstrap::register_defaults
 */
final class element_bootstrap_discovery_test extends advanced_testcase {
    /**
     * Provide a fake element class for discovery (namespaced like a real plugin).
     * Avoids eval() by aliasing a local fixture class to the expected FQCN.
     */
    public function setUp(): void {
        parent::setUp();
        if (!class_exists('\\customcertelement_fakeplugin\\element', false)) {
            class_alias(fake_element_fixture::class, '\\customcertelement_fakeplugin\\element');
        }
    }

    /**
     * Test that element_bootstrap discovers and registers a third-party element class.
     */
    public function test_discovers_third_party_elements(): void {
        $this->resetAfterTest();

        // Create a registry with core defaults first.
        $registry = new element_registry();

        // Use an injectable provider that returns a fake plugin list to exercise real bootstrap discovery.
        $provider = new class implements plugin_provider {
            /**
             * {@inheritdoc}
             */
            public function get_plugins(): array {
                return ['fakeplugin' => '/virtual/path'];
            }
        };

        // Run bootstrap with the fake provider; it should discover and register the fake element class.
        element_bootstrap::register_defaults($registry, $provider);

        $classname = '\\customcertelement_fakeplugin\\element';
        $this->assertTrue(class_exists($classname), 'Expected fake third-party element class to exist for the test.');
        $this->assertTrue($registry->has('fakeplugin'));
        $this->assertSame($classname, $registry->get('fakeplugin'));
    }
}
