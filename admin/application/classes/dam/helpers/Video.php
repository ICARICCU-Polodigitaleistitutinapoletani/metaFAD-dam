<?php

class dam_helpers_Video extends PinaxObject
{
    private $uri;
    private $title;
    private $filename;
    private $name;
    private $md5;
    private $size;
    private $type;
    private $location;


    public function thumbnailManipulation($bytestreamURI, $mediaId)
    {
        $uploadsDir = (__Config::get('UPLOAD_DIR'));
        $width = (__Config::get('THUMB_WIDTH'));
        $height = (__Config::get('THUMB_HEIGHT'));
        $videoThumbDefault = (__Config::get('VIDEO_THUMB'));
        $thumbnailVideoFormat = (__Config::get('THUMB_VIDEO_FORMAT'));
        $fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');

        $newTitle = $this->setValue($mediaId, 'thumbnail', $thumbnailVideoFormat);

        $pathForShell = str_replace(" ", '\ ', $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $bytestreamURI);
        $pathForShell = str_replace("(", '\(', $pathForShell);
        $pathForShell = str_replace(")", '\)', $pathForShell);
        $pathForShell = str_replace("&", '\&', $pathForShell);
        $pathForShell = str_replace("$", '\$', $pathForShell);
        $pathForShell = str_replace("'", "\'", $pathForShell);
        $pathForShell = str_replace(";", '\;', $pathForShell);
        $pathForShell = str_replace("[", '\[', $pathForShell);
        $pathForShell = str_replace("]", '\]', $pathForShell);
        $pathForShell = str_replace("{", '\{', $pathForShell);
        $pathForShell = str_replace("}", '\}', $pathForShell);

        $pathForShell2 = str_replace(" ", '\ ', $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle . '.' . $thumbnailVideoFormat);
        $pathForShell2 = str_replace("(", '\(', $pathForShell2);
        $pathForShell2 = str_replace(")", '\)', $pathForShell2);
        $pathForShell2 = str_replace("&", '\&', $pathForShell2);
        $pathForShell2 = str_replace("$", '\$', $pathForShell2);
        $pathForShell2 = str_replace("'", "\'", $pathForShell2);
        $pathForShell2 = str_replace(";", '\;', $pathForShell2);
        $pathForShell2 = str_replace("[", '\[', $pathForShell2);
        $pathForShell2 = str_replace("]", '\]', $pathForShell2);
        $pathForShell2 = str_replace("{", '\{', $pathForShell2);
        $pathForShell2 = str_replace("}", '\}', $pathForShell2);

        shell_exec('avconv -ss 00:00:05 -i ' . $pathForShell . ' -s ' . $width . 'x' . $height . ' -f image2 -frames:v 1 ' . $pathForShell2);

        if(!$fileSystemHelper->has($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle . '.' . $thumbnailVideoFormat)){
            $fileSystemHelper->copy(realpath($videoThumbDefault), $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle . '.' . $thumbnailVideoFormat);
        }

        return $this->createObjImg();
    }


    public function convert($mediaId, $bytestreamName, $newName = null, $format)
    {
        $uploadsDir = (__Config::get('UPLOAD_DIR'));

        if (!$format) {
            return 'error';
        }
        if (!$newName) {
            $name = 'convert_' . $format;
        } else {
            $name = $newName;
        }
        if ($this->nameControll($mediaId, $name)) {
            return 'error';
        }

        $document = $this->getBytestream($mediaId, $bytestreamName);
        $original = $this->returnUri($document);

        if ($original == 'error') {
            return 'error';
        }

        $newTitle = $this->setValue($mediaId, $name, $format);

        if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId)) {
            if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId, 0777, true)) {
                die('Failed to create folders...');
            }
        }

        $original = str_replace(" ", '\ ', $original);
        $pathForShell = str_replace(" ", '\ ', $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle . '.' . $format);

        exec("avconv -i " . $original . " -strict experimental -vcodec libx264 " . $pathForShell, $output);

        $this->location = $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle . '.' . $format;

        return $this->createObjImg();
    }

    public function getBytestream($mediaId, $bytestreamName)
    {
        if ($mediaId && $bytestreamName) {
            $document = pinax_objectFactory::createModel('dam.models.Media');
            if ($document->load($mediaId)) {
                if ($document->getType() == 'dam.models.Media') {
                    foreach ($document->bytestream as $id) {
                        $document2 = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');
                        if ($document2->load($id)) {
                            if ($bytestreamName == $document2->name) {
                                return $document2;
                            }
                        } else {
                            return 'error';
                        }
                    }
                }
            }
        }
        return 'error';
    }

    public function returnUri($document)
    {
        $uploadDir = __Config::get('UPLOAD_DIR');

        $dateTime = $document->getRawData()->document_creationDate;
        $media_id = $document->media_id;
        $title = $document->title;

        return $uploadDir . '/' . substr($dateTime, 0, 10) . '/' . $media_id . '/' . $title;

    }

    private function createObjImg()
    {
        $objImg = new stdClass();

        $objImg->uri = $this->uri;
        $objImg->title = $this->title;
        $objImg->filename = $this->title;
        $objImg->name = $this->name;
        $objImg->md5 = $this->md5;
        $objImg->size = $this->size;
        $objImg->type = $this->type;
        $objImg->location = $this->location;

        return $objImg;
    }

    private function setValue($mediaId, $name, $format)
    {
        $unique = uniqid();
        $newTitle = $name . '_' . $unique;

        $this->uri = 'get/' . $mediaId . '/' . $name;
        $this->title = $newTitle . '.' . $format;
        $this->name = $name;
        $this->md5 = md5($newTitle . '.' . $format);
        $this->type = $format;

        return $newTitle;
    }

    private function nameControll($mediaId, $name)
    {
        if ($mediaId && $name) {
            $document = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');

            if ($document->load($mediaId)) {
                if ($document->getType() == 'dam.models.Media') {
                    foreach ($document->bytestream as $id) {
                        $document2 = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');
                        if ($document2->load($id)) {
                            if ($name == $document2->getRawData()->name) {
                                return true;
                            }
                        } else {
                            return true;
                        }
                    }
                    return false;
                }
            }
        }
        return true;
    }


}
