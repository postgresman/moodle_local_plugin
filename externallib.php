<?php
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/user/lib.php");

class local_test_external extends external_api {
    public static function get_users_parameters() {
        return new external_function_parameters(array());
    }

    public static function get_users() {
        global $DB;

        $result = array();

        $users = $DB->get_records('user');

        foreach ($users as $user) {
            $userdetails = user_get_user_details_courses($user);

            if (empty($userdetails))
                continue;

            $result[] = $userdetails;
        }

        return $result;
    }

    public static function get_users_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'id' => new external_value(core_user::get_property_type('id'), 'ID of the user'),
                'username' => new external_value(core_user::get_property_type('username'), 'The username', VALUE_OPTIONAL),
                'firstname' => new external_value(core_user::get_property_type('firstname'), 'The first name(s) of the user', VALUE_OPTIONAL),
                'lastname' => new external_value(core_user::get_property_type('lastname'), 'The family name of the user', VALUE_OPTIONAL),
                'fullname' => new external_value(core_user::get_property_type('firstname'), 'The fullname of the user'),
                'profileimageurlsmall' => new external_value(PARAM_URL, 'User image profile URL - small version'),
                'profileimageurl' => new external_value(PARAM_URL, 'User image profile URL - big version'),
                'description' => new external_value(core_user::get_property_type('description'), 'User profile description', VALUE_OPTIONAL),
                'descriptionformat' => new external_format_value(core_user::get_property_type('descriptionformat'), VALUE_OPTIONAL),
                'suspended' => new external_value(core_user::get_property_type('suspended'), 'Suspend user account, either false to enable user login or true to disable it', VALUE_OPTIONAL),
                'firstaccess' => new external_value(core_user::get_property_type('firstaccess'), 'first access to the site (0 if never)', VALUE_OPTIONAL),
                'lastaccess' => new external_value(core_user::get_property_type('lastaccess'), 'last access to the site (0 if never)', VALUE_OPTIONAL),
                'email' => new external_value(core_user::get_property_type('email'), 'An email address - allow email as root@localhost', VALUE_OPTIONAL),
                'department' => new external_value(core_user::get_property_type('department'), 'department', VALUE_OPTIONAL),
                'auth' => new external_value(core_user::get_property_type('auth'), 'Auth plugins include manual, ldap, etc', VALUE_OPTIONAL),
                'confirmed' => new external_value(core_user::get_property_type('confirmed'), 'Active user: 1 if confirmed, 0 otherwise', VALUE_OPTIONAL),
                'lang' => new external_value(core_user::get_property_type('lang'), 'Language code such as "en", must exist on server', VALUE_OPTIONAL),
                'theme' => new external_value(core_user::get_property_type('theme'), 'Theme name such as "standard", must exist on server', VALUE_OPTIONAL),
                'timezone' => new external_value(core_user::get_property_type('timezone'), 'Timezone code such as Australia/Perth, or 99 for default', VALUE_OPTIONAL),
                'mailformat' => new external_value(core_user::get_property_type('mailformat'), 'Mail format code is 0 for plain text, 1 for HTML etc', VALUE_OPTIONAL),
            ))
        );
    }

    public static function get_courses_parameters() {
        return new external_function_parameters(array());
    }

    public static function get_courses() {
        $result = array();

        $courses = get_courses();

        foreach ($courses as $course) {
            $context = context_course::instance($course->id, IGNORE_MISSING);

            try {
                self::validate_context($context);
            } catch (Exception $e) {
                $exceptionparam = new stdClass();
                $exceptionparam->message = $e->getMessage();
                $exceptionparam->courseid = $course->id;
                throw new moodle_exception('errorcoursecontextnotvalid', 'webservice', '', $exceptionparam);
            }

            $courseinfo = array();
            $courseinfo['id'] = $course->id;
            $courseinfo['fullname'] = external_format_string($course->fullname, $context->id);
            $courseinfo['shortname'] = external_format_string($course->shortname, $context->id);
            $courseinfo['displayname'] = external_format_string(get_course_display_name_for_list($course), $context->id);
            $courseinfo['categoryid'] = $course->category;
            list($courseinfo['summary'], $courseinfo['summaryformat']) =
                external_format_text($course->summary, $course->summaryformat, $context->id, 'course', 'summary', 0);
            $courseinfo['format'] = $course->format;
            $courseinfo['startdate'] = $course->startdate;
            $courseinfo['enddate'] = $course->enddate;

            $result[] = $courseinfo;
        }

        return $result;
    }

    public static function get_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'id' => new external_value(PARAM_INT,'Course id'),
                'fullname' => new external_value(PARAM_RAW,'Full name'),
                'shortname' => new external_value(PARAM_RAW,'Course short name'),
                'displayname' => new external_value(PARAM_RAW, 'Course display name'),
                'categoryid' => new external_value(PARAM_INT, 'Category id'),
                'summary' => new external_value(PARAM_RAW, 'Summary'),
                'summaryformat' => new external_format_value('Dummary'),
                'format' => new external_value(PARAM_PLUGIN,'Course format: weeks, topics, social, site,..'),
                'startdate' => new external_value(PARAM_INT,'Timestamp when the course start'),
                'enddate' => new external_value(PARAM_INT,'Timestamp when the course end'),
            ))
        );
    }

    public static function get_enrolled_users_parameters() {
        return new external_function_parameters(array());
    }

    public static function get_enrolled_users() {
        global $DB;

        $result = array();

        $courses = get_courses();

        foreach ($courses as $course) {
            if (!$users = enrol_get_course_users($course->id))
                continue;

            foreach ($users as $user) {
                $userdetails = user_get_user_details_courses($user);

                if (empty($userdetails)) continue;

                $sql = "SELECT finalgrade
                   FROM {grade_items} gi,
                        {grade_grades} gg
                   WHERE 
                         gi.courseid = ? AND
                         gg.userid = ? AND
                         gi.id = gg.itemid";

                $grades = $DB->get_record_sql($sql, array($course->id, $user->id));

                $userdetails['coursegrades'][] = array(
                    'courseid' => $course->id,
                    'coursegrade' => $grades->finalgrade ?? 0
                );

                $result[] = $userdetails;
            }
        }

        return $result;
    }

    public static function get_enrolled_users_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'id' => new external_value(core_user::get_property_type('id'), 'ID of the user'),
                'username' => new external_value(core_user::get_property_type('username'), 'The username', VALUE_OPTIONAL),
                'firstname' => new external_value(core_user::get_property_type('firstname'), 'The first name(s) of the user', VALUE_OPTIONAL),
                'lastname' => new external_value(core_user::get_property_type('lastname'), 'The family name of the user', VALUE_OPTIONAL),
                'fullname' => new external_value(core_user::get_property_type('firstname'), 'The fullname of the user'),
                'profileimageurlsmall' => new external_value(PARAM_URL, 'User image profile URL - small version'),
                'profileimageurl' => new external_value(PARAM_URL, 'User image profile URL - big version'),
                'description' => new external_value(core_user::get_property_type('description'), 'User profile description', VALUE_OPTIONAL),
                'descriptionformat' => new external_format_value(core_user::get_property_type('descriptionformat'), VALUE_OPTIONAL),
                'suspended' => new external_value(core_user::get_property_type('suspended'), 'Suspend user account, either false to enable user login or true to disable it', VALUE_OPTIONAL),
                'firstaccess' => new external_value(core_user::get_property_type('firstaccess'), 'first access to the site (0 if never)', VALUE_OPTIONAL),
                'lastaccess' => new external_value(core_user::get_property_type('lastaccess'), 'last access to the site (0 if never)', VALUE_OPTIONAL),
                'email' => new external_value(core_user::get_property_type('email'), 'An email address - allow email as root@localhost', VALUE_OPTIONAL),
                'department' => new external_value(core_user::get_property_type('department'), 'department', VALUE_OPTIONAL),
                'auth' => new external_value(core_user::get_property_type('auth'), 'Auth plugins include manual, ldap, etc', VALUE_OPTIONAL),
                'confirmed' => new external_value(core_user::get_property_type('confirmed'), 'Active user: 1 if confirmed, 0 otherwise', VALUE_OPTIONAL),
                'lang' => new external_value(core_user::get_property_type('lang'), 'Language code such as "en", must exist on server', VALUE_OPTIONAL),
                'theme' => new external_value(core_user::get_property_type('theme'), 'Theme name such as "standard", must exist on server', VALUE_OPTIONAL),
                'timezone' => new external_value(core_user::get_property_type('timezone'), 'Timezone code such as Australia/Perth, or 99 for default', VALUE_OPTIONAL),
                'mailformat' => new external_value(core_user::get_property_type('mailformat'), 'Mail format code is 0 for plain text, 1 for HTML etc', VALUE_OPTIONAL),
                'coursegrades' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid'  => new external_value(PARAM_INT, 'Course id'),
                            'coursegrade' => new external_value(PARAM_RAW, 'Grade value'),
                        )
                    ), 'Grade values', VALUE_OPTIONAL),
            ))
        );
    }

}