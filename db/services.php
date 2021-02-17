<?php
$functions = array(
    'local_test_get_users' => array(
        'classname' => 'local_test_external',
        'methodname' => 'get_users',
        'classpath' => 'local/test/externallib.php',
        'description' => 'Users list',
        'type' => 'read',
        'capabilities' => 'moodle/user:viewdetails'
    ),
    'local_test_get_courses' => array(
        'classname' => 'local_test_external',
        'methodname' => 'get_courses',
        'classpath' => 'local/test/externallib.php',
        'description' => 'Courses list',
        'type' => 'read',
        'capabilities' => 'moodle/course:view'
    ),
    'local_test_get_enrolled_users' => array(
        'classname' => 'local_test_external',
        'methodname' => 'get_enrolled_users',
        'classpath' => 'local/test/externallib.php',
        'description' => 'Get enrolled users with grade',
        'type' => 'read',
        'capabilities' => 'moodle/grade:view, moodle/course:view, moodle/user:viewdetails'
    ),
);

$services = array(
    'my service' => array(
        'functions' => array (
            'local_test_get_users',
            'local_test_get_courses',
            'local_test_get_enrolled_users'
        ),
        'restrictedusers' => 0,
        'enabled' => 1,
    )
);