<?php
dam_Module::registerModule();

$application = pinax_ObjectValues::get('org.pinax', 'application' );
if ($application) {
    $application->registerProxy('dam.services.SchemaManagerService');
}

pinax_defineBaseHost();
if (__Config::get('dam.url')=='') {
    __Config::set('dam.url', PNX_HOST.'/rest/dam');
}
