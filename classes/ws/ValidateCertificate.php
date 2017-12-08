<?php


namespace mod_customcert;

global $CFG;
require_once("$CFG->libdir/externallib.php");


class ValidateCertificate extends \external_api
{
    /**
     * Verify if the given values are valid for a issued certificate.
     */
    public static function validate_certificate_parameters() {
        return new \external_function_parameters(
                    array(
                        'context_id' => new \external_value(PARAM_INT,
                            'id of the course'),
                        'code' => new \external_value(PARAM_ALPHANUM,
                            'The code for the certificate that going to verify.')
                    )
        );
    }

    public static function validate_certificate_returns(){
        return new \external_single_structure (
            array(
                'validated' => new \external_value(PARAM_BOOL,
                    'If the certificate is valid or not.',
                    True),
                'course_id' => new \external_value(PARAM_INT, 'id of the course',
                    False),
                'course_fullname' => new \external_value(PARAM_ALPHANUM, 'FullName of the Course',
                    False),
                'course_shortname' => new \external_value(PARAM_ALPHANUM, 'ShortName of the Course',
                    False),
                'course_uid' => new \external_value(PARAM_ALPHANUM, 'Course External/Unique Identifier',
                    False),
                'course_summary' => new \external_value(PARAM_TEXT, 'Course Summary',
                    False),
                'student_id' => new \external_value(PARAM_INT, 'Students id',
                    False),
                'student_name' => new \external_value(PARAM_TEXT, 'Students Name',
                    False),
                'certificate_id' => new \external_value(PARAM_ALPHANUM, 'Cert Id',
                    False),
                'cert_issue_timestamp' => new \external_value(PARAM_INT,
                    'Certificate Issued Date in unix timestamp format',
                    False),
            )
        );
    }

    public static function validate_certificate($contextid, $code){
        //TODO: Implement it!
        global $DB;

        $context = \context::instance_by_id($contextid);
        if (!$cm = get_coursemodule_from_id('customcert', $context->instanceid, 0, false)){
            throw new \invalid_parameter_exception('No context with given ID Found.');
        }

        $course = $DB->get_record('course', array('id' => $cm->course), '*',
            MUST_EXIST);
        $customcert = $DB->get_record('customcert', array('id' => $cm->instance));

        // TODO: This SQL Should die.
        $userfields = get_all_user_name_fields(true, 'u');
        $sql = "SELECT ci.id, u.id as userid,$userfields, co.id as courseid,
                   co.fullname as coursefullname, c.name as certificatename, c.verifyany
              FROM {customcert} c
              JOIN {customcert_issues} ci
                ON c.id = ci.customcertid
              JOIN {course} co
                ON c.course = co.id
              JOIN {user} u
                ON ci.userid = u.id
             WHERE ci.code = :code";

        $records = $DB->get_record_sql($sql, ['code'=>$code]);

        if (!$records){
            // Return a Crippled array here. No more information than needed.
            return array(
                'validated' => False
            );
        }

        return array(
            'validated' => True,
            'course_id' => $course->id,
            'course_fullname' => $course->fullname,
            'course_shortname' => $course->shortname,
            'course_summary' => $course->summary,
            'certificate_id' => $customcert->id,
            'cert_issue_timestamp' => $customcert->timecreated,
            'student_id' => $records->userid,
            'student_name' => $records->firstname . ' ' . $records->lastname
        );
    }
}