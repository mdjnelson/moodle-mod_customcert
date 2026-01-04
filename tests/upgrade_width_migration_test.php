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

// Intentionally no MOODLE_INTERNAL guard in PHPUnit testcase per Moodle CS guidance.

/**
 * Upgrade test for migrating width into JSON data and dropping the width column.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\local\upgrade\row_migrator
 */

namespace mod_customcert;

use mod_customcert\local\upgrade\row_migrator;
use mod_customcert\service\element_renderer;

/**
 * Tests for the width/font/fontsize/colour migration helper and basic getter behaviour.
 *
 * @package   mod_customcert
 * @category  test
 * @covers    \mod_customcert\local\upgrade\row_migrator::migrate_row
 */
final class upgrade_width_migration_test extends \advanced_testcase {
    /**
     * Data provider for migrate_row cases.
     *
     * @return array<string, array{0:mixed,1:?int,2:?string,3:?int,4:?string,5:array}>
     */
    public static function migrate_row_provider(): array {
        return [
            // A: data NULL, width 23 and other visuals.
            'null data with visuals' => [
                null, 23, 'Helvetica', 12, '#333333',
                ['width' => 23, 'font' => 'Helvetica', 'fontsize' => 12, 'colour' => '#333333'],
            ],

            // B: scalar data normalised to JSON {"value": "19"} (preserve string identity).
            'scalar string becomes value' => [
                '19', null, null, null, null,
                ['value' => '19'],
            ],

            // C: width key added as 7.
            'json data merges width' => [
                json_encode(['foo' => 'bar']), 7, null, null, null,
                ['width' => 7, 'foo' => 'bar'],
            ],

            // D: width overridden to 9.
            'json width overridden by column' => [
                json_encode(['width' => 5, 'x' => 1]), 9, null, null, null,
                ['width' => 9, 'x' => 1],
            ],

            // E: empty data became {"width":0}.
            'empty data becomes width only' => [
                '', 0, null, null, null,
                ['width' => 0],
            ],

            // F: non-JSON text preserved as value with width merged.
            'plain text preserved as value' => [
                'address', 23, null, null, null,
                ['value' => 'address', 'width' => 23],
            ],

            // G: numeric-looking string preserved as string 'value' with width merged.
            'numeric string preserved as value with width' => [
                '42', 17, null, null, null,
                ['value' => '42', 'width' => 17],
            ],
        ];
    }

    /**
     * A data provider for migrate_row cases.
     *
     * @dataProvider migrate_row_provider
     *
     * @param mixed $data
     * @param ?int $width
     * @param ?string $font
     * @param ?int $fontsize
     * @param ?string $colour
     * @param array $expected
     */
    public function test_migrate_row_cases(
        $data,
        ?int $width,
        ?string $font,
        ?int $fontsize,
        ?string $colour,
        array $expected
    ): void {
        $json = (string) row_migrator::migrate_row($data, $width, $font, $fontsize, $colour);
        $arr = $this->decode_json_to_array($json);

        $this->assert_array_contains_expected($expected, $arr);
    }

    /**
     * Verify runtime getters read from JSON as the single source of truth.
     */
    public function test_element_getters_read_from_json_data(): void {
        // Simulate a migrated row with visual keys embedded in JSON.
        $data = [
            'width' => 23,
            'font' => 'Helvetica',
            'fontsize' => 12,
            'colour' => '#333333',
        ];

        $ra = (object)[
            'id' => 1,
            'pageid' => 1,
            'name' => 'A',
            'data' => json_encode($data),
        ];
        $elema = $this->make_test_element($ra);

        $this->assertSame(23, (int) $elema->get_width());
        $this->assertSame('Helvetica', (string) $elema->get_font());
        $this->assertSame(12, (int) $elema->get_fontsize());
        $this->assertSame('#333333', (string) $elema->get_colour());

        // Width=0 should remain visible as 0.
        $re = (object)[
            'id' => 2,
            'pageid' => 1,
            'name' => 'E',
            'data' => json_encode(['width' => 0]),
        ];
        $eleme = $this->make_test_element($re);
        $this->assertSame(0, (int) $eleme->get_width());

        // For a scalar data row with no JSON width key, getter should return null.
        $rb = (object)[
            'id' => 3,
            'pageid' => 1,
            'name' => 'B',
            'data' => '19',
        ];
        $elemb = $this->make_test_element($rb);
        $this->assertNull($elemb->get_width());
    }

    /**
     * Decode a JSON string into an array with helpful failure output.
     *
     * @param string $json
     * @return array
     */
    private function decode_json_to_array(string $json): array {
        $this->assertNotSame('', $json, 'Expected non-empty JSON string.');
        $this->assertJson($json, 'Expected valid JSON, got: ' . $json);

        $arr = json_decode($json, true);
        $this->assertIsArray($arr, 'Expected JSON to decode to an array. JSON was: ' . $json);

        return $arr;
    }

    /**
     * Assert the result contains the expected keys/values.
     * Uses strict comparison for strings and loose numeric handling for ints (casts),
     * so the test is resilient to JSON encoding numeric types.
     *
     * @param array $expected
     * @param array $actual
     * @return void
     */
    private function assert_array_contains_expected(array $expected, array $actual): void {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $actual, 'Missing key: ' . $key);

            // For expected ints, compare numerically to avoid brittleness between 23 vs "23".
            if (is_int($value)) {
                $this->assertSame($value, (int) $actual[$key], 'Mismatch for key: ' . $key);
                continue;
            }

            // Otherwise compare strictly.
            $this->assertSame($value, $actual[$key], 'Mismatch for key: ' . $key);
        }
    }

    /**
     * Create a minimal element instance for getter testing.
     *
     * @param \stdClass $record
     * @return element
     */
    private function make_test_element(\stdClass $record): element {
        return new class ($record) extends element {
            /**
             * Renders the PDF content with the provided parameters.
             *
             * @param \pdf $pdf The PDF object to render.
             * @param bool $preview Indicates whether the render is for preview purposes.
             * @param \stdClass $user The user object that contains user-specific data for rendering.
             * @param element_renderer|null $renderer An optional element renderer for custom rendering logic. Defaults to null.
             * @return void
             */
            public function render(
                \pdf $pdf,
                bool $preview,
                \stdClass $user,
                ?element_renderer $renderer = null
            ): void {
                // No-op.
            }

            /**
             * Renders the HTML content using the provided renderer or a default mechanism.
             *
             * @param element_renderer|null $renderer The optional renderer to render the HTML content.
             *                                        If null, a default rendering process is used.
             * @return string The generated HTML content as a string.
             */
            public function render_html(?element_renderer $renderer = null): string {
                return '';
            }
        };
    }
}
