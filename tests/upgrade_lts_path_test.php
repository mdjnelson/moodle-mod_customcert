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
 * Direct Moodle 4.5 → 5.3 (main) upgrade regression coverage for schema/data.
 *
 * Models a genuine LTS-to-current upgrade from the last MOODLE_404_STABLE plugin
 * state (version 2024042224), NOT a 5.2-era starting state mislabelled as 4.5.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_customcert_upgrade
 */

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\service\issue_repository;
use stdClass;
use xmldb_field;
use xmldb_table;

/**
 * Regression tests for the direct Moodle 4.5 → 5.3 customcert upgrade path.
 *
 * Baseline reference: MOODLE_404_STABLE tip (plugin version 2024042224). That
 * branch already included usecustomfilename, customfilenamepattern and
 * issueautomatically, and still stored element visuals as discrete columns
 * (font, fontsize, colour, width) alongside the data field. Columns introduced
 * after that baseline on main are completionemailed and studentemailed, plus
 * the 2025122800 visual→JSON migration that drops the discrete visual columns.
 *
 * @covers ::xmldb_customcert_upgrade
 */
final class upgrade_lts_path_test extends advanced_testcase {
    /**
     * Genuine Moodle 4.5-era (MOODLE_404_STABLE) plugin version.
     *
     * Sites on this version have not yet run any of the post-4.5 savepoints on
     * main (2025041401+, including the 2025122800 visuals migration).
     */
    private const int VERSION_MOODLE_45_ERA = 2024042224;

    /**
     * Moodle 5.2-era plugin version (MOODLE_502_STABLE tip).
     *
     * Used only to model the incremental "already on 5.2" side of the
     * equivalence comparison — not as a substitute 4.5 seed.
     */
    private const int VERSION_MOODLE_52_ERA = 2026042012;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Direct LTS upgrade: seed a genuine Moodle 4.5-era schema/data state, run
     * xmldb_customcert_upgrade() from that version through current main, and
     * assert the supported final schema/data shape.
     *
     * This is intentionally distinct from any 5.2 → 5.3 incremental coverage.
     *
     * @covers ::xmldb_customcert_upgrade
     */
    public function test_direct_moodle_45_to_53_upgrade_migrates_schema_and_element_data(): void {
        global $CFG;

        [$customcert, $issueid, $elementids] = $this->seed_moodle_45_era_state();

        // Roll the stored plugin version back so upgrade_mod_savepoint() will
        // accept each post-4.5 savepoint on main.
        set_config('version', self::VERSION_MOODLE_45_ERA, 'mod_customcert');

        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/mod/customcert/db/upgrade.php');
        $this->run_customcert_upgrade(self::VERSION_MOODLE_45_ERA);

        $this->assert_current_main_schema();
        $this->assert_migrated_element_data($elementids);
        $this->assert_post_upgrade_instance_and_issue_defaults($customcert->id, $issueid);
    }

    /**
     * Proves the direct 4.5 → main path reaches the same final schema/data as
     * the incremental path that is already on a 5.2-era state (visuals already
     * migrated to JSON; completionemailed/studentemailed already present) and
     * then upgrades through the remaining main savepoints.
     *
     * Both paths start from the same logical 4.5-era fixture values; the 5.2
     * path pre-applies the migrations that MOODLE_502_STABLE already contained,
     * then runs only the residual main upgrade steps.
     *
     * @covers ::xmldb_customcert_upgrade
     */
    public function test_direct_45_and_incremental_52_paths_reach_equivalent_final_state(): void {
        global $DB, $CFG;

        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/mod/customcert/db/upgrade.php');

        // Direct path: genuine 4.5-era seed → full upgrade to main.
        [$customcertdirect, $issueiddirect, $elementidsdirect] = $this->seed_moodle_45_era_state();
        set_config('version', self::VERSION_MOODLE_45_ERA, 'mod_customcert');
        $this->run_customcert_upgrade(self::VERSION_MOODLE_45_ERA);

        $directelements = [];
        foreach ($elementidsdirect as $key => $id) {
            $directelements[$key] = $DB->get_record('customcert_elements', ['id' => $id], '*', MUST_EXIST);
        }
        $directcert = $DB->get_record('customcert', ['id' => $customcertdirect->id], '*', MUST_EXIST);
        $directissue = $DB->get_record('customcert_issues', ['id' => $issueiddirect], '*', MUST_EXIST);

        $this->assert_current_main_schema();

        // Incremental path: same fixtures as if already upgraded through 5.2.
        // Rebuild a fresh course/module so we do not reuse mutated direct-path rows.
        [$customcert52, $issueid52, $elementids52] = $this->seed_moodle_52_era_state_from_45_fixtures();
        set_config('version', self::VERSION_MOODLE_52_ERA, 'mod_customcert');
        $this->run_customcert_upgrade(self::VERSION_MOODLE_52_ERA);

        $this->assert_current_main_schema();

        foreach ($elementids52 as $key => $id) {
            $row52 = $DB->get_record('customcert_elements', ['id' => $id], '*', MUST_EXIST);
            $this->assertEquals(
                $this->normalise_json($directelements[$key]->data),
                $this->normalise_json($row52->data),
                "Element '{$key}' final data must match between direct 4.5→main and incremental 5.2→main paths."
            );
            $this->assertObjectNotHasProperty('width', $row52);
            $this->assertObjectNotHasProperty('font', $row52);
        }

        $cert52 = $DB->get_record('customcert', ['id' => $customcert52->id], '*', MUST_EXIST);
        $issue52 = $DB->get_record('customcert_issues', ['id' => $issueid52], '*', MUST_EXIST);

        // Schema-level defaults introduced after 4.5 must agree on both paths.
        $this->assertSame((int)$directcert->completionemailed, (int)$cert52->completionemailed);
        $this->assertSame((int)$directcert->issueautomatically, (int)$cert52->issueautomatically);
        $this->assertNull($directissue->studentemailed);
        $this->assertNull($issue52->studentemailed);
    }

    /**
     * Invoke xmldb_customcert_upgrade() while absorbing incidental CLI progress
     * output (e.g. uninstall_plugin() when the removed daterange subplugin is
     * cleaned up at savepoint 2025122600). That output is not part of the
     * schema/data contract under test and would otherwise mark the test risky.
     *
     * @param int $oldversion The version to upgrade from.
     */
    private function run_customcert_upgrade(int $oldversion): void {
        ob_start();
        try {
            xmldb_customcert_upgrade($oldversion);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Restore schema deltas that differ between MOODLE_404_STABLE and current
     * main, seed representative legacy element rows and an historical issue,
     * and return identifiers for later assertions.
     *
     * @return array{0:stdClass,1:int,2:array<string,int>} customcert, issueid, elementids
     */
    private function seed_moodle_45_era_state(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'issueautomatically' => 0,
        ]);

        $pageid = (int)$DB->get_field('customcert_pages', 'id', [
            'templateid' => $customcert->templateid,
        ], MUST_EXIST);

        $issueid = (new issue_repository())->create((int)$customcert->id, (int)$student->id);
        // Pre-existing emailed marker must survive the studentemailed column add.
        $DB->set_field('customcert_issues', 'emailed', 1, ['id' => $issueid]);

        $this->restore_moodle_45_era_schema();

        $fixtures = $this->legacy_element_fixtures($pageid);
        $elementids = [];
        foreach ($fixtures as $key => $row) {
            $elementids[$key] = (int)$DB->insert_record('customcert_elements', $row);
        }

        // Confirm the seed really looks like the 4.5-era schema before upgrading.
        $dbman = $DB->get_manager();
        $elementstable = new xmldb_table('customcert_elements');
        $this->assertTrue($dbman->field_exists($elementstable, new xmldb_field('width')));
        $this->assertTrue($dbman->field_exists($elementstable, new xmldb_field('font')));
        $this->assertTrue($dbman->field_exists($elementstable, new xmldb_field('fontsize')));
        $this->assertTrue($dbman->field_exists($elementstable, new xmldb_field('colour')));
        $this->assertFalse($dbman->field_exists(new xmldb_table('customcert'), new xmldb_field('completionemailed')));
        $this->assertFalse($dbman->field_exists(new xmldb_table('customcert_issues'), new xmldb_field('studentemailed')));

        $sample = $DB->get_record('customcert_elements', ['id' => $elementids['text_with_visuals']], '*', MUST_EXIST);
        $this->assertSame(23, (int)$sample->width);
        $this->assertSame('Helvetica', $sample->font);

        return [$customcert, $issueid, $elementids];
    }

    /**
     * Seed a modelled representative 5.2 persisted state.
     *
     * Visuals are already folded into JSON and discrete visual columns are absent.
     *
     * @return array{0:stdClass,1:int,2:array<string,int>} The customcert, issue ID, and element IDs.
     */
    private function seed_moodle_52_era_state_from_45_fixtures(): array {
        global $DB;

        // Current install.xml already matches the post-5.2 schema shape for the
        // tables under test (visual columns gone; completionemailed/studentemailed
        // present). Do not re-introduce 4.5-only columns here.
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'issueautomatically' => 0,
            'completionemailed' => 0,
        ]);

        $pageid = (int)$DB->get_field('customcert_pages', 'id', [
            'templateid' => $customcert->templateid,
        ], MUST_EXIST);

        $issueid = (new issue_repository())->create((int)$customcert->id, (int)$student->id);
        $DB->set_field('customcert_issues', 'emailed', 1, ['id' => $issueid]);
        // Historical issues that existed before studentemailed stay NULL on 5.2+
        // after the column was added without a default — mirror that here.
        $DB->set_field('customcert_issues', 'studentemailed', null, ['id' => $issueid]);

        $fixtures = $this->legacy_element_fixtures($pageid);
        $migrateddata = [
            'text_with_visuals' => '{"colour":"#333333","font":"Helvetica","fontsize":12,"width":23}',
            'userfield_scalar' => '{"userfield":"email","width":40}',
            'json_merges_width' => '{"display":"full","width":7}',
            'border_scalar_thickness' => '{"colour":"#000000","width":3}',
            'coursefield_visuals' => '{"colour":"#112233","coursefield":"fullname","font":"Times","fontsize":14,"width":100}',
        ];

        $elementids = [];
        foreach ($fixtures as $key => $row) {
            $elementids[$key] = (int)$DB->insert_record('customcert_elements', (object) [
                'pageid' => $row->pageid,
                'name' => $row->name,
                'element' => $row->element,
                'data' => $migrateddata[$key],
                'posx' => $row->posx,
                'posy' => $row->posy,
                'refpoint' => $row->refpoint,
                'alignment' => $row->alignment,
                'sequence' => $row->sequence,
                'timecreated' => $row->timecreated,
                'timemodified' => $row->timemodified,
            ]);
        }

        return [$customcert, $issueid, $elementids];
    }

    /**
     * Rewind current main schema to the genuine MOODLE_404_STABLE shape for the
     * columns that differ, so xmldb_customcert_upgrade() exercises the real
     * post-4.5 savepoints (visual migration, completionemailed, studentemailed).
     *
     * Fields already present on MOODLE_404_STABLE (usecustomfilename,
     * customfilenamepattern, issueautomatically) are left in place — matching a
     * real 4.5 site that already had those columns from earlier 4.4 savepoints.
     */
    private function restore_moodle_45_era_schema(): void {
        global $DB;

        $dbman = $DB->get_manager();

        // Re-introduce discrete visual columns removed by savepoint 2025122800.
        $elementstable = new xmldb_table('customcert_elements');

        $font = new xmldb_field('font', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'data');
        if (!$dbman->field_exists($elementstable, $font)) {
            $dbman->add_field($elementstable, $font);
        }

        $fontsize = new xmldb_field('fontsize', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'font');
        if (!$dbman->field_exists($elementstable, $fontsize)) {
            $dbman->add_field($elementstable, $fontsize);
        }

        $colour = new xmldb_field('colour', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'fontsize');
        if (!$dbman->field_exists($elementstable, $colour)) {
            $dbman->add_field($elementstable, $colour);
        }

        $width = new xmldb_field('width', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'posy');
        if (!$dbman->field_exists($elementstable, $width)) {
            $dbman->add_field($elementstable, $width);
        }

        // Drop columns that did not exist on MOODLE_404_STABLE.
        $certtable = new xmldb_table('customcert');
        $completionemailed = new xmldb_field('completionemailed');
        if ($dbman->field_exists($certtable, $completionemailed)) {
            $dbman->drop_field($certtable, $completionemailed);
        }

        $issuestable = new xmldb_table('customcert_issues');
        $studentemailed = new xmldb_field('studentemailed');
        if ($dbman->field_exists($issuestable, $studentemailed)) {
            $dbman->drop_field($issuestable, $studentemailed);
        }
    }

    /**
     * Representative legacy element rows as they existed before the 2025122800
     * visuals→JSON migration: mixed scalar/JSON data plus discrete visual columns.
     *
     * @param int $pageid The page ID to associate elements with.
     * @return array<string,stdClass> The legacy element fixtures.
     */
    private function legacy_element_fixtures(int $pageid): array {
        $now = time();
        $base = static function (
            string $name,
            string $element,
            ?string $data,
            ?int $width,
            ?string $font,
            ?int $fontsize,
            ?string $colour,
            int $sequence
        ) use (
            $pageid,
            $now
        ): stdClass {
            return (object) [
                'pageid' => $pageid,
                'name' => $name,
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
                'sequence' => $sequence,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
        };

        return [
            // Text element with discrete visuals and empty data.
            'text_with_visuals' => $base(
                'Legacy text',
                'text',
                null,
                23,
                'Helvetica',
                12,
                '#333333',
                1
            ),
            // Scalar legacy data (pre-JSON object) with a width column.
            'userfield_scalar' => $base(
                'Legacy userfield',
                'userfield',
                'email',
                40,
                null,
                null,
                null,
                2
            ),
            // Already-JSON object data that must merge width from the column.
            'json_merges_width' => $base(
                'Legacy json width',
                'code',
                json_encode(['display' => 'full'], JSON_THROW_ON_ERROR),
                7,
                null,
                null,
                null,
                3
            ),
            // Border stored thickness as a plain scalar in data (classic pre-refactor shape).
            'border_scalar_thickness' => $base(
                'Legacy border',
                'border',
                '3',
                null,
                null,
                null,
                '#000000',
                4
            ),
            // Coursefield scalar with full visual set.
            'coursefield_visuals' => $base(
                'Legacy coursefield',
                'coursefield',
                'fullname',
                100,
                'Times',
                14,
                '#112233',
                5
            ),
        ];
    }

    /**
     * Assert schema matches current main install.xml for the upgrade-touched columns.
     */
    private function assert_current_main_schema(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $elementstable = new xmldb_table('customcert_elements');

        $this->assertFalse($dbman->field_exists($elementstable, new xmldb_field('width')));
        $this->assertFalse($dbman->field_exists($elementstable, new xmldb_field('font')));
        $this->assertFalse($dbman->field_exists($elementstable, new xmldb_field('fontsize')));
        $this->assertFalse($dbman->field_exists($elementstable, new xmldb_field('colour')));

        $this->assertTrue($dbman->field_exists(new xmldb_table('customcert'), new xmldb_field('completionemailed')));
        $this->assertTrue($dbman->field_exists(new xmldb_table('customcert'), new xmldb_field('issueautomatically')));
        $this->assertTrue($dbman->field_exists(new xmldb_table('customcert_issues'), new xmldb_field('studentemailed')));
    }

    /**
     * Assert each seeded legacy element was migrated to the expected JSON shape.
     *
     * @param array $elementids Element IDs keyed by fixture name.
     */
    private function assert_migrated_element_data(array $elementids): void {
        global $DB;

        $expecteddata = [
            'text_with_visuals' => '{"colour":"#333333","font":"Helvetica","fontsize":12,"width":23}',
            'userfield_scalar' => '{"userfield":"email","width":40}',
            'json_merges_width' => '{"display":"full","width":7}',
            'border_scalar_thickness' => '{"colour":"#000000","width":3}',
            'coursefield_visuals' => '{"colour":"#112233","coursefield":"fullname","font":"Times","fontsize":14,"width":100}',
        ];

        foreach ($elementids as $key => $id) {
            $row = $DB->get_record('customcert_elements', ['id' => $id], '*', MUST_EXIST);
            $this->assertEquals(
                $this->normalise_json($expecteddata[$key]),
                $this->normalise_json($row->data),
                "Direct 4.5→main upgrade must migrate element '{$key}' to the expected JSON shape."
            );

            // Discrete visual columns must be gone from the record object.
            $this->assertObjectNotHasProperty('width', $row);
            $this->assertObjectNotHasProperty('font', $row);
            $this->assertObjectNotHasProperty('fontsize', $row);
            $this->assertObjectNotHasProperty('colour', $row);
        }
    }

    /**
     * Assert instance/issue defaults applied by post-4.5 savepoints.
     *
     * @param int $customcertid The customcert ID.
     * @param int $issueid The issue ID.
     */
    private function assert_post_upgrade_instance_and_issue_defaults(int $customcertid, int $issueid): void {
        global $DB;

        $cert = $DB->get_record('customcert', ['id' => $customcertid], '*', MUST_EXIST);
        $this->assertSame(0, (int)$cert->completionemailed);
        $this->assertSame(0, (int)$cert->issueautomatically);

        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);
        // Studentemailed is nullable with no default: pre-existing issues stay NULL.
        $this->assertNull($issue->studentemailed);
        $this->assertSame(1, (int)$issue->emailed);
    }

    /**
     * Decode/re-encode JSON so key-order differences do not fail equality checks.
     *
     * @param string|null $json The JSON string to normalise.
     * @return string|null The normalised JSON string.
     */
    private function normalise_json(?string $json): ?string {
        if ($json === null || $json === '') {
            return $json;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $json;
        }
        ksort($decoded);
        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
}
