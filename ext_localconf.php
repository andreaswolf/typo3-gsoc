<?php

define('RDFAPI_INCLUDE_DIR', t3lib_extMgm::extPath($_EXTKEY) . 'Resources/PHP/rdfapi-php/api/');
#define('EF_PATH_FRAMEWORK', t3lib_extMgm::extPath($_EXTKEY) . 'Resources/PHP/Erfurt/');

include_once t3lib_extMgm::extPath($_EXTKEY) . 'Resources/PHP/Erfurt/Classes/Core/Bootstrap.php';
include_once t3lib_extMgm::extPath($_EXTKEY) . 'Resources/PHP/Erfurt/Classes/Core/ClassLoader.php';
spl_autoload_register(array(t3lib_div::makeInstance('\Erfurt\Core\ClassLoader'), 'loadClass'));


?>