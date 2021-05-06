<?php
require_once("../../vendor/autoload.php");

$application = new pinax_rest_core_Application('../../admin/application', '../../vendor/icariccu/pinax-2/');
$application->setLanguage('it');
$application->run();

