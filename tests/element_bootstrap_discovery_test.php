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
use mod_customcert\tests\fixtures\counting_plugin_provider;
use mod_customcert\tests\fixtures\fake_element_fixture;
use mod_customcert\tests\fixtures\incompatible_element_fixture;
use mod_customcert\tests\fixtures\simple_plugin_provider;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_element_fixture.php');
require_once(__DIR__ . '/fixtures/simple_plugin_provider.php');
require_once(__DIR__ . '/fixtures/counting_plugin_provider.php');
require_once(__DIR__ . '/fixtures/incompatible_element_fixture.php');

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
        $provider = new simple_plugin_provider();

        // Run bootstrap with the fake provider; it should discover and register the fake element class.
        element_bootstrap::register_defaults($registry, $provider);

        $classname = '\\customcertelement_fakeplugin\\element';
        $this->assertTrue(class_exists($classname), 'Expected fake third-party element class to exist for the test.');
        $this->assertTrue($registry->has('fakeplugin'));
        $this->assertSame($classname, $registry->get('fakeplugin'));
    }

    /**
     * A class outside the supported legacy/native envelope (#955) must be skipped during
     * discovery with a warning, not crash bootstrap -- distinct from a plugin that is simply
     * not installed (restore_missing_third_party_element_test.php), since here the class does
     * exist but element_registry::register() refuses it. Both leave the type unregistered, so
     * downstream behaviour (restore, rendering) ends up identical either way.
     */
    public function test_incompatible_class_is_skipped_during_discovery(): void {
        $this->resetAfterTest();

        if (!class_exists('\\customcertelement_incompat959\\element', false)) {
            class_alias(incompatible_element_fixture::class, '\\customcertelement_incompat959\\element');
        }

        $registry = new element_registry();
        $calls = 0;
        $provider = new counting_plugin_provider($calls, 'incompat959');

        $this->resetDebugging();
        element_bootstrap::register_defaults($registry, $provider);

        $this->assertDebuggingCalled(
            "Failed to register customcertelement 'incompat959': Coding error detected, it must be fixed by "
                . "a programmer: Cannot register element type 'incompat959': "
                . "'\\customcertelement_incompat959\\element' must implement form_element_interface "
                . 'and renderable_element_interface, or extend mod_customcert\\element.',
            DEBUG_DEVELOPER
        );
        $this->assertFalse($registry->has('incompat959'), 'An incompatible class must never end up registered.');
    }
}
