<?php

class dam_rest_controllers_Upload extends pinax_rest_core_CommandRest
{
    function execute($instance)
    {
        if ($instance && isset($_FILES["file"])) {
            $fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');
            $uploadsDir = $fileSystemHelper->getUploadDir();

            pinax_helpers_Files::deleteDirectory($uploadsDir, 2*24*60, true);

            $unique = uniqid();
            @mkdir($uploadsDir);
            $moveFileResult = move_uploaded_file($_FILES['file']['tmp_name'], $uploadsDir . $unique . '_' . $_FILES["file"]['name']);
            if (!$moveFileResult || $moveFileResult == false) {
                return array('http-status' => 400);
            }
            return $unique . '_' . $_FILES["file"]['name'];
        }
        return array('http-status' => 400);
    }
}
