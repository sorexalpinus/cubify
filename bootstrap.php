<?php
/**
 * Created by PhpStorm.
 * User: jhlatky
 * Date: 12. 9. 2019
 * Time: 12:42
 */

define('DS',DIRECTORY_SEPARATOR);
$path = str_replace(['\\','/'],[DS,DS],realpath(__DIR__).'/src');
$funcTestPath = str_replace(['\\','/'],[DS,DS],realpath(__DIR__).'/functional_tests');
$loader = require 'vendor/autoload.php';
$loader->addPsr4('Cubify\\', $path);
$loader->addPsr4('Cubify\\', $funcTestPath);
define('SRC_PATH',$path);
$rootWebpath = 'http://' . $_SERVER['SERVER_NAME'];
define('ROOT_WEBPATH',$rootWebpath);