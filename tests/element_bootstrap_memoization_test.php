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

/**
 * Verifies element_bootstrap memoizes discovery results during a single request.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\element\element_bootstrap::register_defaults
 */
final class element_bootstrap_memoization_test extends advanced_testcase {
    protected function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * The second registry build should not trigger another provider scan within the same request.
     *
     * @runInSeparateProcess
     */
    public function test_provider_called_once_per_request(): void {
        // Create a unique fake type for this run and alias the fixture class to it before discovery.
        $uniq = 'fakecache_' . bin2hex(random_bytes(4));
        $fqcn = '\\customcertelement_' . $uniq . '\\element';
        if (!class_exists($fqcn, false)) {
            require_once(__DIR__ . '/fixtures/fake_element_fixture.php');
            class_alias(fake_element_fixture::class, $fqcn);
        }

        // A fake provider that counts calls to get_plugins() and returns only our unique type.
        $calls = 0;
        $type = $uniq;
        $provider = new class ($calls, $type) implements plugin_provider {
            /**
             * Constructor.
             *
             * @param int $calls
             * @param string $type
             */
            public function __construct(&$calls, string $type) {
                $this->calls =& $calls;
                $this->type = $type;
            }

            /**
             * Returns a list of plugins that are available for this provider.
             *
             * @return string[]
             */
            public function get_plugins(): array {
                $this->calls++;
                return [$this->type => '/virtual/path'];
            }

            /**
             * Reference to external $calls variable.
             *
             * @var mixed
             */
            private $calls;

            /**
             * Represents the type of a given element.
             *
             * @var mixed
             */
            private string $type;
        };

        // First registry: discovery should call provider once and register the fake element.
        $r1 = new element_registry();
        element_bootstrap::register_defaults($r1, $provider);
        $this->assertTrue($r1->has($uniq));
        $this->assertSame($fqcn, $r1->get($uniq));
        $this->assertSame(1, $calls, 'Provider should have been called exactly once on first bootstrap.');

        // Second registry in same request: should reuse memoized discovery; no extra provider call.
        $r2 = new element_registry();
        element_bootstrap::register_defaults($r2, $provider);
        $this->assertTrue($r2->has($uniq));
        $this->assertSame($fqcn, $r2->get($uniq));
        $this->assertSame(1, $calls, 'Provider should not be called again within the same request.');
    }
}
