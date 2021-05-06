<?php
class dam_rest_controllers_Media_Download extends pinax_rest_core_CommandRest
{
    function execute($instance, $downloadMode, $mediaId, $name)
    {
        if ($instance && $downloadMode) {
            $data = json_decode(__Request::get('__postBody__'));
            $document = pinax_objectFactory::createModel('dam.models.Media');
            $files = array();

            if (!$data) {

                $myData = new stdClass();
                $myData->media_id = $mediaId;
                $myData->name = $name;
                $myData->new_name = __Request::get('new_name');
                $myData->type_of_change = __Request::get('type_of_change');
                switch (__Request::get('type_of_change')) {
                    case "crop":
                        $myData->width = __Request::get('width');
                        $myData->height = __Request::get('height');
                        $myData->x = __Request::get('x');
                        $myData->y = __Request::get('y');
                        break;
                    case "resize":
                        $myData->columns = __Request::get('columns');
                        $myData->rows = __Request::get('rows');
                        $myData->filter = __Request::get('filter');
                        $myData->blur = __Request::get('blur');
                        $myData->bestfit = __Request::get('bestfit');
                        break;
                    case "rotate":
                        $myData->background = __Request::get('background');
                        $myData->degrees = __Request::get('degrees');
                        break;
                    case "setImageFormat":
                        $myData->format = __Request::get('format');
                        break;
                    case "setVideoFormat":
                        $myData->format = __Request::get('format');
                        break;
                    case "flip":
                        break;
                    case "flop":
                        break;
                    case "":
                        break;
                    default:
                        return array('http-status' => 400, 'message' => 'Missing type_of_change parameter');
                }
                $byteStreamData = $this->modify($myData, $downloadMode, $instance);
                if ($byteStreamData == 'error') {
                    return array('http-status' => 400, 'message' => 'bytestream error');
                }
                $files[$byteStreamData->title] = $byteStreamData;

            } else {
                foreach ($data as $myData) {
                    $byteStreamData = $this->modify($myData, $downloadMode, $instance);
                    if ($byteStreamData == 'error') {
                        return array('http-status' => 400, 'message' => 'bytestream error foreach');
                    }
                    $files[$byteStreamData->title] = $byteStreamData;
                }
            }

            if ($document->load($mediaId)) {
                if ($document->getRawData()->datastream) {
                    $datastream = $document->datastream;
                } else {
                    return array('http-status' => 400, 'message' => 'datastream error');
                }
            }

            foreach ($datastream as $id) {
                $doc = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');

                if ($doc->load($id)) {
                    if ($doc->getType() == 'dam.models.MainData') {
                        if ($doc->getRawData()->filename) {
                            $filename = $doc->filename;
                        }
                    }
                } else {
                    return array('http-status' => 400, 'message' => 'datastream error filename');
                }
            }


            if ($downloadMode == 'true') {
                if (count($files) != 1) {
                    $zipname = $filename . '.zip';

                    $zip = new ZipArchive;
                    $zip->open($zipname, ZipArchive::CREATE);
                    foreach ($files as $title => $file) {
                        $zip->addFile($file->location, $title);
                    }

                    $zip->close();

                    foreach ($files as $file) {
                        if (!$file->base) {
                            if (!unlink($file->location)) {
                                return array('http-status' => 400, 'message' => 'unable to unlink file');
                            }
                        }
                    }
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/zip');
                    header('Content-disposition: attachment; filename=' . $zipname);
                    header('Content-Length: ' . filesize($zipname));
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    readfile($zipname);

                    if (!unlink($zipname)) {
                        return array('http-status' => 400, 'message' => 'unable to unlink zipname');
                    }
                } else {
                    header('Content-Description: File Transfer');
                    header('Content-disposition: attachment; filename="' . pathinfo($filename, PATHINFO_FILENAME) . '.' . $byteStreamData->type.'"');
                    header('Content-Length: ' . filesize($files[$byteStreamData->title]->location));
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    readfile($files[$byteStreamData->title]->location);

                    if (!$files[$byteStreamData->title]->base) {
                        if (!unlink($files[$byteStreamData->title]->location)) {
                            return array('http-status' => 400, 'message' => 'unable to unlink bystream');
                        }
                    }
                }
            }
            return array('http-status' => 200, 'message' => 'OK');
        }
        return array('http-status' => 400,  'message' => 'Missing instace or mode');
    }

    private function modify($myData, $downloadMode, $instance)
    {
        $imageHelper = __ObjectFactory::createObject('dam.helpers.Image');
        $videoHelper = __ObjectFactory::createObject('dam.helpers.Video');

        $document = pinax_objectFactory::createModel('dam.models.Media');//pinax.dataAccessDoctrine.ActiveRecordDocument');
        $byteStreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy', $instance);
        $mediaProxy = __ObjectFactory::createObject('dam.models.MediaProxy', $instance);

        $typeOfChange = json_decode(__Config::get('dam.typeOfChange'));
        $exchangeableMedia = array();

        foreach ($typeOfChange as $key => $value) {
            $exchangeableMedia[] = $key;
        }

        $byteStreamData = new stdClass();
        if ($document->load($myData->media_id)) {
            if ($document->instance == $instance) {
                if ($document->getType() == 'dam.models.Media') {
                    foreach ($exchangeableMedia as $mediaType) {
                        if ($document->media_type == $mediaType) {
                            $result = 'ok';
                        }
                    }
                } else {
                    return 'error';
                }
            } else {
                return 'error';
            }
        } else {
            return 'error';
        }

        if ($result != 'ok' && $downloadMode=='false') {
            return 'error';
        }

        $allBytestream = array();

        if ($document->media_type == 'IMAGE') {
            switch ($myData->type_of_change) {
                case "crop":
                    $byteStreamData = $imageHelper->cropImage($downloadMode, $myData->media_id, $myData->new_name, $myData->name, $myData->width, $myData->height, $myData->x, $myData->y);
                    break;
                case "resize":
                    $byteStreamData = $imageHelper->resizeImage($downloadMode, $myData->media_id, $myData->new_name, $myData->name, $myData->columns, $myData->rows, $myData->filter, $myData->blur, $myData->bestfit);
                    break;
                case "rotate":
                    $byteStreamData = $imageHelper->rotateImage($downloadMode, $myData->media_id, $myData->new_name, $myData->name, $myData->background, $myData->degrees);
                    break;
                case "setImageFormat":
                    $byteStreamData = $imageHelper->setImageFormat($downloadMode, $myData->media_id, $myData->new_name, $myData->name, $myData->format);
                    break;
                case "flip":
                    $byteStreamData = $imageHelper->flipImage($downloadMode, $myData->media_id, $myData->new_name, $myData->name);
                    break;
                case "flop":
                    $byteStreamData = $imageHelper->flopImage($downloadMode, $myData->media_id, $myData->new_name, $myData->name);
                    break;
                case "":
                    $document = $imageHelper->getBytestream($myData->media_id, $myData->name);
                    $byteStreamData->type = $document->type;
                    $byteStreamData->location = $imageHelper->returnUri($document);
                    $byteStreamData->title = $document->title;
                    $byteStreamData->base = true;
                    $byteStreamData->size = $document->size;
                    break;
                default:
                    $document = $imageHelper->getBytestream($myData->media_id, $myData->name);
                    $byteStreamData->type = $document->type;
                    $byteStreamData->location = $imageHelper->returnUri($document);
                    $byteStreamData->title = $document->title;
                    $byteStreamData->base = true;
                    $byteStreamData->size = $document->size;
                    break;
            }
        } else if ($document->media_type == 'VIDEO' && $myData->name == 'original') {
            if ($myData->format) {
                $byteStreamData = $videoHelper->convert($myData->media_id, $myData->name, $myData->new_name, $myData->format);
            } else {
                $document = $videoHelper->getBytestream($myData->media_id, $myData->name);
                $byteStreamData->type = $document->type;
                $byteStreamData->location = $videoHelper->returnUri($document);
                $byteStreamData->title = $document->title;
                $byteStreamData->base = true;
            }
        } else {
            if ($downloadMode != 'false') {
                // per i tipi di versi da immagine
                // utilizza l'helper immagine pre recuperare i dati del bytestream
                $document = $imageHelper->getBytestream($myData->media_id, $myData->name);
                $byteStreamData->type = $document->type;
                $byteStreamData->location = $imageHelper->returnUri($document);
                $byteStreamData->title = $document->title;
                $byteStreamData->base = true;
                $byteStreamData->size = $document->size;
            }
        }

        if ($byteStreamData != 'error' && $downloadMode == 'false') {
            $objBytestream = $byteStreamProxy->add($byteStreamData, 'Inserito ByteStream ' . $byteStreamData->name, $myData->media_id);
            $allBytestream[] = $objBytestream;
            $mediaProxy->append(null, $allBytestream, $myData->media_id, false);
        } else if ($byteStreamData == 'error') {
            return 'error';
        }
        return $byteStreamData;
    }
}

