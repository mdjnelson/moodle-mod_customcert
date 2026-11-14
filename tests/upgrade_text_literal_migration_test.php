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

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\local\upgrade\row_migrator;
use stdClass;
use xmldb_field;
use xmldb_table;

/**
 * Database-backed tests for the 2025122800 visuals migration of legacy text elements (#1120).
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_customcert_upgrade
 */
final class upgrade_text_literal_migration_test extends advanced_testcase {
    /** @var int Plugin version immediately before the visuals migration savepoint. */
    private const int VERSION_BEFORE_MIGRATION = 2025122799;

    /** @var string Config name used to checkpoint the migration. */
    private const string CHECKPOINT = 'visualmigrationlastid';

    /** @var int Page that owns the seeded elements. */
    private int $pageid;

    protected function tearDown(): void {
        // A failed test must not leave the legacy columns behind for later tests.
        $this->drop_legacy_columns();
        parent::tearDown();
    }

    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        $this->resetAfterTest();

        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/mod/customcert/db/upgrade.php');

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $this->pageid = (int)$DB->get_field('customcert_pages', 'id', ['templateid' => $customcert->templateid], MUST_EXIST);

        $this->add_legacy_columns(['width', 'font', 'fontsize', 'colour']);
        set_config('version', self::VERSION_BEFORE_MIGRATION, 'mod_customcert');
        unset_config(self::CHECKPOINT, 'mod_customcert');
    }

    /**
     * Literal legacy text, including JSON-looking values, is preserved verbatim.
     */
    public function test_literal_legacy_text_is_preserved(): void {
        $literals = [
            'true', 'false', 'null', '1e3', '19', '"quoted"', '[1,2]', '{"text":"Hello"}', '{"value":"Hello"}',
            '{}', '  {"text":"Hello"}  ', "  padded\n", 'Hello', 'Congratulations to xyz',
        ];
        $ids = [];
        foreach ($literals as $literal) {
            $ids[$literal] = $this->insert_legacy('text', $literal);
        }

        $this->run_upgrade();

        foreach ($ids as $literal => $id) {
            $payload = $this->payload($id);
            $this->assertSame((string)$literal, $payload['text'], "Literal text '$literal' must be preserved.");
            $this->assertSame(23, $payload['width']);
            $this->assertSame('Helvetica', $payload['font']);
            $this->assertSame(12, $payload['fontsize']);
            $this->assertSame('#333333', $payload['colour']);
        }
        $this->assert_migration_finished();
    }

    /**
     * A literal that looks exactly like a migrated payload is still literal text.
     *
     * The legacy columns match the values inside the string, so any heuristic comparing the JSON
     * with the visual columns would wrongly treat the row as already migrated.
     */
    public function test_literal_text_matching_legacy_visuals_is_not_treated_as_migrated(): void {
        $literal = '{"text":"Hello","width":23,"font":"Helvetica","fontsize":12,"colour":"#333333"}';
        $id = $this->insert_legacy('text', $literal);

        $this->run_upgrade();

        $payload = $this->payload($id);
        $this->assertSame($literal, $payload['text']);
        $this->assertSame(23, $payload['width']);
        $this->assertSame('Helvetica', $payload['font']);
        $this->assertSame(12, $payload['fontsize']);
        $this->assertSame('#333333', $payload['colour']);
    }

    /**
     * Empty text keeps its previous behaviour: visuals only, no text key.
     */
    public function test_empty_legacy_text_keeps_existing_behaviour(): void {
        $null = $this->insert_legacy('text', null);
        $empty = $this->insert_legacy('text', '');
        $novisuals = $this->insert_legacy('text', null, null, null, null, null);

        $this->run_upgrade();

        foreach ([$null, $empty] as $id) {
            $payload = $this->payload($id);
            $this->assertArrayNotHasKey('text', $payload);
            $this->assertSame(23, $payload['width']);
        }
        $this->assertNull($this->raw($novisuals));
    }

    /**
     * Non-text elements still use the JSON-aware migration.
     */
    public function test_non_text_elements_are_unchanged_by_literal_handling(): void {
        $userfield = $this->insert_legacy('userfield', 'email');
        $object = $this->insert_legacy('coursefield', '{"coursefield":"fullname","extra":1}');
        $scalar = $this->insert_legacy('userfield', 'true');
        $border = $this->insert_legacy('border', '3', null, null, null, '#000000');
        $untouched = $this->insert_legacy('coursename', '{"coursenamedisplay":2,"width":5}', null, null, null, null);

        $this->run_upgrade();

        $this->assertSame('email', $this->payload($userfield)['userfield']);
        $this->assertSame(23, $this->payload($userfield)['width']);
        $this->assertSame(['coursefield' => 'fullname', 'extra' => 1], array_intersect_key(
            $this->payload($object),
            ['coursefield' => 1, 'extra' => 1]
        ));
        $this->assertSame(23, $this->payload($object)['width']);
        $this->assertTrue($this->payload($scalar)['userfield']);
        $this->assertSame(3, $this->payload($border)['width']);
        $this->assertSame('{"coursenamedisplay":2,"width":5}', $this->raw($untouched));
    }

    /**
     * A border with a NULL legacy width (as in the MOODLE_500_STABLE schema) promotes its scalar thickness.
     */
    public function test_border_with_null_legacy_width_promotes_scalar_thickness(): void {
        global $DB;

        $id = $this->insert_legacy('border', '3', null, null, null, '#000000');
        $this->assertNull($DB->get_field('customcert_elements', 'width', ['id' => $id]));

        $this->run_upgrade();

        $payload = $this->payload($id);
        $this->assertSame(3, $payload['width']);
        $this->assertSame('#000000', $payload['colour']);
        $this->assertArrayNotHasKey('value', $payload);
        $this->assert_migration_finished();
    }

    /**
     * Rows on both sides of the 1000 row batch boundary are each migrated exactly once.
     */
    public function test_rows_across_batch_boundary_are_migrated_once(): void {
        global $DB;

        $rows = [];
        for ($i = 1; $i <= 1002; $i++) {
            $rows[] = $this->legacy_record('text', '{"text":"row' . $i . '"}');
        }
        $DB->insert_records('customcert_elements', $rows);

        $this->run_upgrade();

        $records = $DB->get_records('customcert_elements', ['element' => 'text'], 'id');
        $this->assertCount(1002, $records);
        $i = 0;
        foreach ($records as $record) {
            $i++;
            $payload = json_decode($record->data, true);
            $this->assertSame('{"text":"row' . $i . '"}', $payload['text'], "Row $i migrated exactly once.");
        }
        $this->assert_migration_finished();
    }

    /**
     * An upgrade interrupted after a committed batch resumes after the checkpoint.
     *
     * Models the state left behind by the checkpointed implementation: the first rows are
     * migrated and the checkpoint holds the last committed id.
     */
    public function test_resume_after_committed_batch_does_not_rewrap_rows(): void {
        global $DB;

        $ids = [];
        foreach (['{"text":"one"}', 'two', 'true', '{"text":"four"}', 'five'] as $text) {
            $ids[] = $this->insert_legacy('text', $text);
        }
        $committed = array_slice($ids, 0, 3);
        foreach ($committed as $id) {
            $row = $DB->get_record('customcert_elements', ['id' => $id], '*', MUST_EXIST);
            $DB->set_field('customcert_elements', 'data', row_migrator::migrate_text_row(
                $row->data,
                (int)$row->width,
                $row->font,
                (int)$row->fontsize,
                $row->colour
            ), ['id' => $id]);
        }
        set_config(self::CHECKPOINT, $committed[2], 'mod_customcert');
        $before = array_map(fn($id) => $this->raw($id), $committed);

        $this->run_upgrade();

        foreach ($committed as $index => $id) {
            $this->assertSame($before[$index], $this->raw($id), 'Committed rows must not be processed twice.');
        }
        $this->assertSame('{"text":"one"}', $this->payload($ids[0])['text']);
        $this->assertSame('two', $this->payload($ids[1])['text']);
        $this->assertSame('{"text":"four"}', $this->payload($ids[3])['text']);
        $this->assertSame('five', $this->payload($ids[4])['text']);
        $this->assert_migration_finished();
    }

    /**
     * A failure after every row was migrated (e.g. while dropping columns) is safe to retry.
     */
    public function test_retry_after_failure_while_dropping_columns_is_safe(): void {
        $ids = [];
        foreach (['{"text":"one"}', 'two'] as $text) {
            $ids[] = $this->insert_legacy('text', $text);
        }
        $this->run_upgrade();
        $migrated = array_map(fn($id) => $this->raw($id), $ids);

        // Recreate the interrupted state: rows done, checkpoint left behind, width column still present.
        $this->add_legacy_columns(['width']);
        set_config(self::CHECKPOINT, end($ids), 'mod_customcert');
        set_config('version', self::VERSION_BEFORE_MIGRATION, 'mod_customcert');

        $this->run_upgrade();

        $this->assertSame($migrated, array_map(fn($id) => $this->raw($id), $ids));
        $this->assert_migration_finished();
    }

    /**
     * Once width has been dropped, already-migrated payloads are never wrapped as literal text.
     */
    public function test_retry_after_width_column_dropped_keeps_migrated_payloads(): void {
        global $DB;

        $payload = '{"text":"Hello","width":23,"font":"Helvetica","fontsize":12,"colour":"#333333"}';
        $id = $this->insert_legacy('text', $payload);
        $DB->get_manager()->drop_field(new xmldb_table('customcert_elements'), new xmldb_field('width'));

        $this->run_upgrade();

        $this->assertSame(json_decode($payload, true), $this->payload($id));
    }

    /**
     * Limitation: rows migrated by the pre-checkpoint implementation cannot be recognised.
     *
     * The earlier implementation had no checkpoint and left the legacy columns in place until
     * every row was done. If it was interrupted and the upgrade is re-run with the current code,
     * a migrated payload is byte-for-byte identical to a literal legacy value such as
     * {"text":"Hello"}, and no trustworthy migration state exists to tell them apart. Treating
     * valid JSON, or JSON matching the visual columns, as already migrated would reintroduce
     * #1120, so the row is wrapped as literal text. The original string is retained inside the
     * text value, but the certificate renders that JSON string, not the original text, until the
     * element is edited manually. This test documents the limitation; it does not endorse it.
     */
    public function test_rerun_over_rows_migrated_by_previous_implementation_is_a_known_limitation(): void {
        global $DB;

        $id = $this->insert_legacy('text', 'Hello');
        $row = $DB->get_record('customcert_elements', ['id' => $id], '*', MUST_EXIST);
        $previous = row_migrator::migrate_row($row->data, 23, 'Helvetica', 12, '#333333', 'text');
        $DB->set_field('customcert_elements', 'data', $previous, ['id' => $id]);

        $this->run_upgrade();

        $payload = $this->payload($id);
        $this->assertSame(json_decode($previous, true), json_decode($payload['text'], true));
        $this->assertSame(23, $payload['width']);
    }

    /**
     * Run the real upgrade step from just before the visuals migration.
     */
    private function run_upgrade(): void {
        ob_start();
        try {
            xmldb_customcert_upgrade(self::VERSION_BEFORE_MIGRATION);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Assert the migration completed: columns dropped and checkpoint removed.
     */
    private function assert_migration_finished(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('customcert_elements');
        foreach (['width', 'font', 'fontsize', 'colour'] as $field) {
            $this->assertFalse($dbman->field_exists($table, new xmldb_field($field)));
        }
        $this->assertFalse(get_config('mod_customcert', self::CHECKPOINT));
    }

    /**
     * Drop any remaining legacy visual columns from customcert_elements.
     */
    private function drop_legacy_columns(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('customcert_elements');
        foreach (['width', 'font', 'fontsize', 'colour'] as $name) {
            $field = new xmldb_field($name);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }
    }

    /**
     * Add legacy visual columns back to customcert_elements.
     *
     * @param string[] $names Columns to add.
     */
    private function add_legacy_columns(array $names): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('customcert_elements');
        $fields = [
            'font' => new xmldb_field('font', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'data'),
            'fontsize' => new xmldb_field('fontsize', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'font'),
            'colour' => new xmldb_field('colour', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'fontsize'),
            'width' => new xmldb_field('width', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'posy'),
        ];
        foreach ($names as $name) {
            if (!$dbman->field_exists($table, $fields[$name])) {
                $dbman->add_field($table, $fields[$name]);
            }
        }
    }

    /**
     * Build a legacy element record.
     *
     * @param string $element Element type.
     * @param string|null $data Legacy data.
     * @param int|null $width Legacy width.
     * @param string|null $font Legacy font.
     * @param int|null $fontsize Legacy font size.
     * @param string|null $colour Legacy colour.
     * @return stdClass
     */
    private function legacy_record(
        string $element,
        ?string $data,
        ?int $width = 23,
        ?string $font = 'Helvetica',
        ?int $fontsize = 12,
        ?string $colour = '#333333'
    ): stdClass {
        return (object)[
            'pageid' => $this->pageid,
            'name' => 'Legacy ' . $element,
            'element' => $element,
            'data' => $data,
            'font' => $font,
            'fontsize' => $fontsize,
            'colour' => $colour,
            'posx' => 10,
            'posy' => 20,
            'width' => $width,
            'refpoint' => 1,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
    }

    /**
     * Insert a legacy element and return its id.
     *
     * @param string $element Element type.
     * @param string|null $data Legacy data.
     * @param int|null $width Legacy width.
     * @param string|null $font Legacy font.
     * @param int|null $fontsize Legacy font size.
     * @param string|null $colour Legacy colour.
     * @return int
     */
    private function insert_legacy(
        string $element,
        ?string $data,
        ?int $width = 23,
        ?string $font = 'Helvetica',
        ?int $fontsize = 12,
        ?string $colour = '#333333'
    ): int {
        global $DB;
        return (int)$DB->insert_record(
            'customcert_elements',
            $this->legacy_record($element, $data, $width, $font, $fontsize, $colour)
        );
    }

    /**
     * Fetch the raw data column.
     *
     * @param int $id Element id.
     * @return string|null
     */
    private function raw(int $id): ?string {
        global $DB;
        return $DB->get_field('customcert_elements', 'data', ['id' => $id], MUST_EXIST);
    }

    /**
     * Fetch the decoded JSON payload.
     *
     * @param int $id Element id.
     * @return array
     */
    private function payload(int $id): array {
        $payload = json_decode((string)$this->raw($id), true);
        $this->assertIsArray($payload);
        return $payload;
    }
}
