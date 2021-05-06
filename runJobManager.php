<?php
chdir(dirname($_SERVER['PHP_SELF']));

require_once("vendor/autoload.php");

$application = pinax_ObjectFactory::createObject('pinaxcms.core.application.AdminApplication', 'admin/application', 'vendor/icariccu/pinax-2/', 'admin/application/');
$application->setLanguage('it');
$application->runSoft();
$application->executeCommand('dam.jobmanager.controllers.JobManager');

