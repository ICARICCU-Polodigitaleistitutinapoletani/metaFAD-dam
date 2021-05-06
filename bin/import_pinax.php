<?php
require_once("../vendor/autoload.php");

$application = pinax_ObjectFactory::createObject('pinaxcms.core.application.AdminApplication', '../admin/application', '../vendor/icariccu/pinax-2/', '../admin/application/');
$application->useXmlSiteMap = true;
$application->setLanguage('it');
$application->runSoft();