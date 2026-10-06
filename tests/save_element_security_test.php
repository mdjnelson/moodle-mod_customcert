<?php
// This file is part of Moodle - http://moodle.org/
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
use context_course;
use core_external\external_api;
use customcertelement_gradeitemname\element as gradeitemname_element;
use customcertelement_userfield\element as userfield_element;
use grade_item;
use invalid_parameter_exception;
use mod_customcert\service\element_factory;
use ReflectionMethod;
use stdClass;

/**
 * Security regression tests for mod_customcert_save_element value validation.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_element_security_test extends advanced_testcase {
    /** @var stdClass Course the teacher can manage. */
    private stdClass $coursea;

    /** @var stdClass Course the teacher has no access to. */
    private stdClass $courseb;

    /** @var stdClass The teacher, enrolled in course A only. */
    private stdClass $teacher;

    /** @var int Template id of the certificate in course A. */
    private int $templateid;

    /** @var int Page id of the certificate in course A. */
    private int $pageid;

    /**
     * Create two courses, a teacher in course A and a certificate with one page in course A.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $this->coursea = $this->getDataGenerator()->create_course();
        $this->courseb = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->coursea, 'editingteacher');

        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $this->coursea->id]);
        $this->templateid = (int)$DB->get_field('customcert', 'templateid', ['id' => $customcert->id], MUST_EXIST);
        $this->pageid = (int)$DB->insert_record('customcert_pages', (object)[
            'templateid' => $this->templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $this->setUser($this->teacher);
    }

    /**
     * Insert an element into the course A certificate.
     *
     * @param string $type Element type.
     * @param array $data Stored payload.
     * @return stdClass
     */
    private function create_element(string $type, array $data = []): stdClass {
        global $DB;

        $element = (object)[
            'pageid' => $this->pageid,
            'element' => $type,
            'name' => 'Test ' . $type,
            'posx' => 10,
            'posy' => 10,
            'refpoint' => 1,
            'alignment' => 'L',
            'data' => json_encode($data),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $element->id = (int)$DB->insert_record('customcert_elements', $element);

        return $element;
    }

    /**
     * Submit values through save_element and return the decoded stored payload.
     *
     * @param stdClass $element The element.
     * @param array $values Map of field name to value.
     * @return array|null Payload, or null if the request was rejected.
     */
    private function save(stdClass $element, array $values): ?array {
        global $DB;

        $submitted = [];
        foreach ($values as $name => $value) {
            $submitted[] = ['name' => $name, 'value' => (string)$value];
        }
        try {
            $result = external::save_element($this->templateid, $element->id, $submitted);
            external_api::clean_returnvalue(external::save_element_returns(), $result);
        } catch (invalid_parameter_exception $e) {
            return null;
        }

        return json_decode($DB->get_field('customcert_elements', 'data', ['id' => $element->id]), true);
    }

    /**
     * Create a stored file in the given course's mod_customcert file area.
     *
     * @param stdClass $course The course.
     * @param string $filearea The file area.
     * @param string $filename The file name.
     * @return int The stored file id.
     */
    private function create_file(stdClass $course, string $filearea, string $filename): int {
        $file = get_file_storage()->create_file_from_string([
            'contextid' => context_course::instance($course->id)->id,
            'component' => 'mod_customcert',
            'filearea' => $filearea,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        return (int)$file->get_id();
    }

    /**
     * An arbitrary user table field must not be saved.
     *
     * @covers \mod_customcert\external::save_element
     */
    public function test_save_element_rejects_sensitive_userfield(): void {
        $element = $this->create_element('userfield', ['userfield' => 'email']);

        $payload = $this->save($element, ['userfield' => 'password']);

        $this->assertNotSame('password', $payload['userfield'] ?? null);
    }

    /**
     * A field offered by the form still saves.
     *
     * @covers \mod_customcert\external::save_element
     */
    public function test_save_element_saves_valid_userfield(): void {
        $element = $this->create_element('userfield', ['userfield' => 'email']);

        $payload = $this->save($element, ['userfield' => 'city']);

        $this->assertSame('city', $payload['userfield']);
    }

    /**
     * Stored values outside the allowed core fields are never read from the user record.
     *
     * @covers \customcertelement_userfield\element::get_user_field_value
     */
    public function test_userfield_render_ignores_non_allowed_field(): void {
        $element = $this->create_element('userfield', ['userfield' => 'password']);
        $student = $this->getDataGenerator()->create_user(['password' => 'Secret-1234!', 'city' => 'Perth']);
        $student = \core_user::get_user($student->id);

        $instance = element_factory::build_with_defaults()->create_from_legacy_record($element);
        $this->assertInstanceOf(userfield_element::class, $instance);
        $method = new ReflectionMethod($instance, 'get_user_field_value');
        $this->assertSame('', $method->invoke($instance, $student, false));

        $element = $this->create_element('userfield', ['userfield' => 'city']);
        $instance = element_factory::build_with_defaults()->create_from_legacy_record($element);
        $this->assertSame('Perth', $method->invoke($instance, $student, false));
    }

    /**
     * Create an assignment with a grade item in the given course.
     *
     * @param stdClass $course The course.
     * @return array{0: int, 1: grade_item} Course module id and grade item.
     */
    private function create_graded_module(stdClass $course): array {
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $gradeitem = grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'courseid' => $course->id,
        ]);

        return [(int)$assign->cmid, $gradeitem];
    }

    /**
     * Create a manual grade item in the given course.
     *
     * @param stdClass $course The course.
     * @return grade_item
     */
    private function create_manual_item(stdClass $course): grade_item {
        return new grade_item($this->getDataGenerator()->create_grade_item(['courseid' => $course->id]), false);
    }

    /**
     * Grade, date and grade item name elements must not accept other courses' items.
     *
     * @dataProvider grade_element_provider
     * @covers \mod_customcert\external::save_element
     * @param string $type Element type.
     * @param string $field The select field holding the grade item.
     * @param array $extra Other required values.
     */
    public function test_save_element_rejects_foreign_grade_items(string $type, string $field, array $extra): void {
        [$foreigncmid] = $this->create_graded_module($this->courseb);
        [$localcmid] = $this->create_graded_module($this->coursea);
        $localitem = $this->create_manual_item($this->coursea);
        $foreignitem = $this->create_manual_item($this->courseb);
        $element = $this->create_element($type, [$field => (string)$localcmid] + $extra);

        foreach (['gradeitem:' . $foreignitem->id, (string)$foreigncmid] as $foreign) {
            $payload = $this->save($element, [$field => $foreign] + $extra);
            $this->assertNotSame($foreign, $payload[$field] ?? null);
        }

        // Valid values from the current course still save.
        $this->assertSame((string)$localcmid, $this->save($element, [$field => $localcmid] + $extra)[$field]);
        $this->assertSame(
            'gradeitem:' . $localitem->id,
            $this->save($element, [$field => 'gradeitem:' . $localitem->id] + $extra)[$field]
        );
    }

    /**
     * Provider for grade-referencing elements.
     *
     * @return array
     */
    public static function grade_element_provider(): array {
        return [
            'grade' => ['grade', 'gradeitem', ['gradeformat' => 1]],
            'date' => ['date', 'dateitem', ['dateformat' => '1']],
            'gradeitemname' => ['gradeitemname', 'gradeitem', []],
        ];
    }

    /**
     * Grade lookups must be restricted to the certificate's course.
     *
     * @covers \mod_customcert\element_helper::get_grade_item_info
     * @covers \mod_customcert\element_helper::get_mod_grade_info
     */
    public function test_grade_helpers_restrict_to_course(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->courseb, 'student');
        [$cmid, $gradeitem] = $this->create_graded_module($this->courseb);

        $this->assertNotFalse(element_helper::get_grade_item_info($gradeitem->id, GRADE_DISPLAY_TYPE_REAL, $student->id));
        $this->assertNotFalse(
            element_helper::get_grade_item_info($gradeitem->id, GRADE_DISPLAY_TYPE_REAL, $student->id, $this->courseb->id)
        );
        $this->assertFalse(
            element_helper::get_grade_item_info($gradeitem->id, GRADE_DISPLAY_TYPE_REAL, $student->id, $this->coursea->id)
        );

        $this->assertNotFalse(
            element_helper::get_mod_grade_info($cmid, GRADE_DISPLAY_TYPE_REAL, $student->id, $this->courseb->id)
        );
        $this->assertFalse(
            element_helper::get_mod_grade_info($cmid, GRADE_DISPLAY_TYPE_REAL, $student->id, $this->coursea->id)
        );
    }

    /**
     * A stored cross-course reference does not resolve to a grade item name.
     *
     * @covers \customcertelement_gradeitemname\element::get_grade_item_name
     */
    public function test_gradeitemname_render_restricted_to_course(): void {
        [$foreigncmid] = $this->create_graded_module($this->courseb);
        $foreignitem = $this->create_manual_item($this->courseb);
        [$localcmid] = $this->create_graded_module($this->coursea);
        $method = new ReflectionMethod(gradeitemname_element::class, 'get_grade_item_name');

        foreach (['gradeitem:' . $foreignitem->id, (string)$foreigncmid] as $reference) {
            $instance = element_factory::build_with_defaults()
                ->create_from_legacy_record($this->create_element('gradeitemname', ['gradeitem' => $reference]));
            $this->assertSame('', $method->invoke($instance));
        }

        $instance = element_factory::build_with_defaults()
            ->create_from_legacy_record($this->create_element('gradeitemname', ['gradeitem' => (string)$localcmid]));
        $this->assertNotSame('', $method->invoke($instance));
    }

    /**
     * Image, background image and digital signature elements must not accept other courses' files.
     *
     * @dataProvider file_element_provider
     * @covers \mod_customcert\external::save_element
     * @param string $type Element type.
     */
    public function test_save_element_rejects_foreign_image_file(string $type): void {
        $foreign = $this->create_file($this->courseb, 'image', 'foreign.png');
        $local = $this->create_file($this->coursea, 'image', 'local.png');
        $element = $this->create_element($type);

        $payload = $this->save($element, ['fileid' => $foreign]);
        $this->assertNotSame('foreign.png', $payload['filename'] ?? null);
        $this->assertNotEquals(context_course::instance($this->courseb->id)->id, $payload['contextid'] ?? null);

        $payload = $this->save($element, ['fileid' => $local]);
        $this->assertSame('local.png', $payload['filename']);
        $this->assertEquals(context_course::instance($this->coursea->id)->id, $payload['contextid']);
    }

    /**
     * Provider for elements with an image select.
     *
     * @return array
     */
    public static function file_element_provider(): array {
        return [
            'image' => ['image'],
            'bgimage' => ['bgimage'],
            'digitalsignature' => ['digitalsignature'],
        ];
    }

    /**
     * Digital signature certificates from other courses must not be selectable.
     *
     * @covers \mod_customcert\external::save_element
     */
    public function test_save_element_rejects_foreign_signature_file(): void {
        $foreign = $this->create_file($this->courseb, 'signature', 'foreign.p12');
        $local = $this->create_file($this->coursea, 'signature', 'local.p12');
        $element = $this->create_element('digitalsignature');

        $payload = $this->save($element, ['signaturefileid' => $foreign]);
        $this->assertNotSame('foreign.p12', $payload['signaturefilename'] ?? null);

        $payload = $this->save($element, ['signaturefileid' => $local]);
        $this->assertSame('local.p12', $payload['signaturefilename']);
    }

    /**
     * Element normalisation ignores file ids that the form would not offer.
     *
     * @covers \customcertelement_image\element::normalise_data
     */
    public function test_image_normalise_data_ignores_foreign_file(): void {
        global $COURSE;

        $COURSE = $this->coursea;
        $foreign = $this->create_file($this->courseb, 'image', 'foreign.png');
        $instance = element_factory::build_with_defaults()->create_from_legacy_record($this->create_element('image'));

        $payload = $instance->normalise_data((object)['fileid' => $foreign]);

        $this->assertArrayNotHasKey('filename', $payload);
    }

    /**
     * Ownership and capability checks still apply.
     *
     * @covers \mod_customcert\external::save_element
     */
    public function test_save_element_still_requires_template_ownership(): void {
        global $DB;

        $customcertb = $this->getDataGenerator()->create_module('customcert', ['course' => $this->courseb->id]);
        $templateidb = (int)$DB->get_field('customcert', 'templateid', ['id' => $customcertb->id], MUST_EXIST);
        $element = $this->create_element('userfield', ['userfield' => 'email']);

        $this->expectException(\moodle_exception::class);
        external::save_element($templateidb, $element->id, [['name' => 'userfield', 'value' => 'city']]);
    }
}
