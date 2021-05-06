<?php

class dam_Module
{
    static function registerModule()
    {
        $moduleVO = pinax_Modules::getModuleVO();
        $moduleVO->id = 'meta_dam';
        $moduleVO->name = 'Meta Dam';
        $moduleVO->description = 'DAM';
        $moduleVO->version = '1.0.0';
        $moduleVO->classPath = 'dam';
        $moduleVO->pageType = '';
        $moduleVO->model = '';
        $moduleVO->author = 'ICAR - ICCU - Polo Digitale degli istituti culturali di Napoli';
        $moduleVO->authorUrl = '';
        $moduleVO->pluginUrl = '';
        $moduleVO->siteMapAdmin = '';
        $moduleVO->canDuplicated = false;
        $moduleVO->path = __DIR__.'/../';

        pinax_Modules::addModule($moduleVO);
    }
}
