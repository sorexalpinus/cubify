<?php
define('DS', DIRECTORY_SEPARATOR);
define('CUBIFY_ROOT',str_replace(['\\', '/'], [DS, DS], realpath(__DIR__)));
define('CUBIFY_SRC_PATH', CUBIFY_ROOT . DS . 'src');
define('CUBIFY_TESTS_PATH', CUBIFY_ROOT . DS . 'tests');
define('CUBIFY_RSRC_PATH', CUBIFY_ROOT . DS . 'resources');
define('CUBIFY_SQL_PATH', CUBIFY_ROOT . DS . 'resources' . DS . 'sql');