<?php

class dam_helpers_MimeType extends PinaxObject
{
    public function getMediaTypeFromMime($myExtension)
    {
        $myExtension = strtolower($myExtension);

        $fileTypes = array('IMAGE' => array('extension' => array('jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff'), 'class' => 'Image'),
            'OFFICE' => array('extension' => array('doc', 'xls', 'mdb', 'ppt', 'pps', 'html', 'htm', 'odb', 'odc', 'odf', 'odg', 'odi', 'odm', 'odp', 'ods', 'odt', 'otc', 'otf', 'otg', 'oth', 'oti', 'otp', 'ots', 'ott', 'docx', 'dotx', 'xlsx', 'xltx', 'pptx', 'potx'), 'class' => 'Office'),
            'ARCHIVE' => array('extension' => array('zip', 'rar', '7z', 'tar', 'gz', 'tgz'), 'class' => 'Archive'),
            'AUDIO' => array('extension' => array('wav', 'mp3', 'aif'), 'class' => 'Audio'),
            'PDF' => array('extension' => array('pdf'), 'class' => 'Pdf'),
            'VIDEO' => array('extension' => array('avi', 'mov', 'x-flv', 'wmv', 'mp4', 'm4v', 'mpg'), 'class' => 'Video'),
            'FLASH' => array('extension' => array('swf'), 'class' => 'Flash'),
            'OTHER' => array('extension' => array(), 'class' => 'Other'));
        foreach ($fileTypes as $fileType) {
            foreach ($fileType['extension'] as $ext) {
                if ($myExtension == $ext) {
                    return $fileType['class'];
                }
            }
        }
        return 'Other';
    }
}
