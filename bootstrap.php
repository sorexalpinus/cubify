<?php
/**
 * Created by PhpStorm.
 * User: jhlatky
 * Date: 12. 9. 2019
 * Time: 12:42
 */

define('DS', DIRECTORY_SEPARATOR);

$root = str_replace(['\\', '/'], [DS, DS], realpath(__DIR__));
$srcPath = $root . '/src';
$funcTestPath = $root . '/functional_tests';
$loader = require 'vendor/autoload.php';
$loader->addPsr4('Cubify\\', $srcPath);
$loader->addPsr4('Cubify\\', $funcTestPath);
define('SRC_PATH', $srcPath);
define('RSRC_PATH', $root . DS . 'resources');
define('SQL_PATH', $root . DS . 'resources' . DS . 'sql');