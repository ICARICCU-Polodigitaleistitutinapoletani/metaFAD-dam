<?php

class dam_helpers_Batch extends dam_jobmanager_service_JobService
{
    private $instance;
    private $media_id;
    private $uri;
    private $title;
    private $name;
    private $new_name;
    private $md5;
    private $size;
    private $type;
    private $image;

    public function run()
    {
        set_error_handler($this->errorHandler());
        try{
            $param = $this->params;
            $data = $param['data'];
            $uploadsDir = (__Config::get('UPLOAD_DIR'));
            $media = __ObjectFactory::createModel('dam.models.Media');
            $document2 = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');
            $imageHelper = __ObjectFactory::createObject('dam.helpers.Image');

            $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

            $changedOriginalBytestream = false;

            $this->updateStatus(dam_jobmanager_JobStatus::RUNNING);
            $this->save();

            if ($data->media_type == 'IMAGE') {
                $count = 0;
                $progressMedia = 100 / (count((array)$data) - 2);
                foreach ($data as $key => $value) {

                    if ($key == 'instance') {
                        $this->instance = $value;
                    }
                    if ($key == 'media_id') {
                        $this->media_id = $value;
                        $media->load($this->media_id);
                    }
                    if ($key == 'name') {
                        $this->name = $value;
                    }
                    if ($key == 'new_name') {
                        $this->new_name = $value;
                    }
                    if ($key != 'instance' && $key != 'media_id' && $key != 'name' && $key != 'new_name' && $key != "media_type") {
                        if ($count == 0) {
                            $keyName = uniqid();
                            $bytestream = $this->getBytestream($this->media_id, $this->name);
                            if (!$bytestream) {
                                throw new Exception('No bytestream found');
                            }
                            $original = $this->returnUri($bytestream);
                            $this->image = new Imagick($original);

                            $return_original = str_replace($bytestream->title, 'return_' . $bytestream->title, $original);

                            //Se esiste l'immagine original ridimensionata!

                            if (file_exists($return_original)) {
                                $returnOriginal = new Imagick($return_original);
                                $ratio = $this->image->getImageWidth() / $returnOriginal->getImageWidth();
                            }

                        }
                        if ($key == 'setImageFormat') {
                            $keyName = 'conversion_' . $keyName;
                        } else if ($key == 'resampleImage') {
                            $keyName = 'DPI_' . $keyName;
                        } else {
                            $keyName = $key . '_' . $keyName;
                        }

                        switch ($key) {
                            case "crop":
                                if (file_exists($return_original)) {
                                    $this->image->cropImage($value->width * $ratio, $value->height * $ratio, $value->x * $ratio, $value->y * $ratio);
                                } else {
                                    $this->image->cropImage($value->width, $value->height, $value->x, $value->y);
                                }
                                $count++;
                                break;
                            case "resize":
                                if (!$value->height) {
                                    $value->height = ($value->width / $this->image->getImageWidth()) * $this->image->getImageHeight();
                                    $this->image->resizeImage($value->width, $value->height, imagick::FILTER_LANCZOS, 1, true);
                                } else if (!$value->width) {
                                    $value->width = ($value->height / $this->image->getImageHeight()) * $this->image->getImageWidth();
                                    $this->image->resizeImage($value->width, $value->height, imagick::FILTER_LANCZOS, 1, true);
                                } else {
                                    $this->image->resizeImage($value->width, $value->height, imagick::FILTER_LANCZOS, 1, false);
                                }
                                $count++;
                                break;
                            case "rotate":
                                $this->image->rotateImage("white", $value->degrees);
                                $count++;
                                break;
                            case "setImageFormat":
                                $this->image->setImageFormat($value->format);
                                $count++;
                                break;
                            case "flip":
                                $this->image->flipImage();
                                $count++;
                                break;
                            case "flop":
                                $this->image->flopImage();
                                $count++;
                                break;
                            case "resampleImage":
                                if (!property_exists($value, 'resize')) {
                                    $value->resize = true;
                                }
                                if (!property_exists($value, 'maintainAspect') || !$value->maintainAspect) {
                                    $value->maintainAspect = true;
                                }
                                $originalResolution = $this->image->getImageResolution();
                                if (!$originalResolution['x']) $originalResolution['x'] = 72;
                                if (!$originalResolution['y']) $originalResolution['y'] = 72;
                                $newResolution = array('x' => $value->xResolution, 'y' => $value->yResolution);
                                $this->image->setImageResolution($newResolution['x'], $newResolution['y']);
                                if ($value->resize) {
                                    $this->image->resizeImage(  round($this->image->getImageWidth()/($originalResolution['x']/$newResolution['x'])),
                                                                round($this->image->getImageHeight()/($originalResolution['y']/$newResolution['y'])),
                                                                imagick::FILTER_LANCZOS,
                                                                1,
                                                                $value->maintainAspect);
                                }

                                $count++;
                                break;
                            default:
                                throw new dam_exceptions_InternalServerError("Batch param operation problem");
                        }

                        $solrService->createSolrDocument($media, $progressMedia * $count, $this->name);
                    }
                }

                $mediaProxy = __ObjectFactory::createObject('dam.models.MediaProxy', $this->instance);
                $byteStreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy', $this->instance);

                if ($this->new_name) {
                    $newTitle = $this->setValueImage($this->image, $this->media_id, $keyName, $this->new_name);
                } else {
                    $newTitle = $this->setValueImage($this->image, $this->media_id, $keyName, $keyName);
                }

                if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $this->media_id)) {
                    if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $this->media_id, 0777, true)) {
                        die('Failed to create folders...');
                    }
                }

                if ($this->new_name && $imageHelper->nameControll($this->media_id, $this->new_name)) {
                    $bytestream = $this->getBytestream($this->media_id, $this->new_name);

                    if (!$bytestream) {
                        throw new Exception('No bytestream found');
                    }

                    $creationDate = date("Y-m-d", strtotime($bytestream->getRawData()->document_creationDate));
                    $title = $bytestream->getRawData()->title;

                    if($this->new_name == 'original' || $this->new_name == 'thumbnail'){
                        $changedOriginalBytestream = true;
                    }

                    if ($this->new_name == 'original') {
                        $this->image->writeImage($uploadsDir . '/' . $creationDate . '/' . $this->media_id . '/' . $newTitle);
                        $imageHelper->originalResize($newTitle, $this->media_id, $creationDate);
                        $this->location = $uploadsDir . '/' . $creationDate . '/' . $this->media_id . '/' . $newTitle;
                        $this->size = filesize($this->location);

                        if ($media->load($this->media_id)) {
                            if ($media->bytestream) {
                                foreach ($media->bytestream as $bytestream) {
                                    $document2->load($bytestream);
                                    if ($document2->getRawData()->name && $document2->name == 'original') {
                                        $originalId = $document2->document_id;
                                    }
                                    if ($document2->getRawData()->name && $document2->name == 'thumbnail') {
                                        $thumbnailId = $document2->document_id;
                                    }
                                }
                            }
                        } else {
                            throw new Exception('No media found');
                        }
                        $thumbnailData = $imageHelper->thumbnailManipulation($newTitle, $this->media_id, $creationDate);
                        $obj = $byteStreamProxy->modify($thumbnailId, $thumbnailData, 'Modificato ByteStream Thumbnail ', $this->media_id);
                        $originalData = $this->createObjImg();
                        $originalData->thumbnail_id = $obj->document_id;
                        $originalData->thumbnail_detail_id = $obj->document_detail_id;
                        $byteStreamProxy->modify($originalId, $originalData, 'Modificato ByteStream ' . $this->name, $this->media_id);
                    } else {
                        //checking if file exists
                        if (file_exists($uploadsDir . '/' . $creationDate . '/' . $this->media_id . '/' . $title)) {
                            unlink($uploadsDir . '/' . $creationDate . '/' . $this->media_id . '/' . $title);
                        }
                        //Place it into your "uploads" folder now using the move_uploaded_file() function
                        $this->image->writeImage($uploadsDir . '/' . $creationDate . '/' . $this->media_id . '/' . $title);

                        if (file_exists($uploadsDir . '/' . $creationDate . '/' . $this->media_id . '/return_' . $title)) {
                            unlink($uploadsDir . '/' . $creationDate . '/' . $this->media_id . '/return_' . $title);
                            $imageHelper->originalResize($title, $this->media_id, $creationDate);
                        }
                        $this->location = $uploadsDir . '/' . $creationDate . '/' . $this->media_id . '/' . $title;
                        $this->size = filesize($this->location);
                    }
                } else {
                    $this->image->writeImage($uploadsDir . '/' . date('Y-m-d') . '/' . $this->media_id . '/' . $newTitle);
                    $imageHelper->originalResize($newTitle, $this->media_id);
                    $this->location = $uploadsDir . '/' . date('Y-m-d') . '/' . $this->media_id . '/' . $newTitle;
                    $this->size = filesize($this->location);

                    $byteStreamData = $this->createObjImg();
                    $objBytestream = $byteStreamProxy->add($byteStreamData, 'Inserito ByteStream ' . $byteStreamData->name, $this->media_id);
                    $allBytestream[] = $objBytestream;
                    $mediaProxy->append(null, $allBytestream, $this->media_id, false, $changedOriginalBytestream);
                    $this->setMessage('Terminata azione batch modifica immagine: ' . $this->name . ' del media: ' . $this->media_id);

                }
            }

            if ($data->media_type == 'VIDEO') {
                foreach ($data as $key => $value) {
                    if ($key == 'media_id') {
                        $this->media_id = $value;
                        $media->load($this->media_id);
                    }

                    if ($key == 'name') {
                        $this->name = $value;
                    }
                    if ($key != 'media_id' && $key != 'name') {
                        $bytestream = $this->getBytestream($this->media_id, $this->name);
                        $original = $this->returnUri($bytestream);
                        if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $this->media_id)) {
                            if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $this->media_id, 0777, true)) {
                                throw new Exception('Failed to create folders...');
                            }
                        }
                        switch ($key) {
                            case "setVideoFormat":
                                $newTitle = $this->setValueVideo($this->media_id, 'videoConvertito', $value);
                                $original = str_replace(" ", '\ ', $original);
                                $pathForShell = str_replace(" ", '\ ', $uploadsDir . '/' . date('Y-m-d') . '/' . $this->media_id . '/' . $newTitle . '.' . $value);
                                exec("avconv -i " . $original . " -strict experimental -vcodec libx264 " . $pathForShell);
                                break;
                        }
                    }

                    $solrService->createSolrDocument($media, 100, $this->name);
                }

                $byteStreamData = $this->createObjImg();
                $objBytestream = $byteStreamProxy->add($byteStreamData, 'Inserito ByteStream ' . $byteStreamData->name, $this->media_id);
                $allBytestream[] = $objBytestream;
                $mediaProxy->append(null, $allBytestream, $this->media_id, false);
                $this->setMessage('Terminata azione batch conversione video del media: ' . $this->media_id);
            }

            $this->updateStatus(dam_jobmanager_JobStatus::COMPLETED);
            $this->updateProgress(100);
            if($changedOriginalBytestream){
                $mediaDocument = __ObjectFactory::createModel('dam.models.Media');
                $mediaDocument->load($this->media_id);
                $mediaDocument->bytestream_last_update = time();
                $mediaDocument->publish();
            }
            $media->load($this->media_id);
            $solrService->createSolrDocument($media, 100, $this->name);
            $this->save();
        }
        catch(Exception $e){
            $this->setMessage($e->getMessage());
            $this->updateStatus(dam_jobmanager_JobStatus::ERROR);
            $this->save();
        }

    }

    public function getBytestream($mediaId, $bytestreamName)
    {
        if ($mediaId && $bytestreamName) {
            $media = __ObjectFactory::createModel('dam.models.Media');

            if ($media->load($mediaId)) {
                foreach ($media->bytestream as $id) {
                    $byteStream = __ObjectFactory::createModel('dam.models.ByteStream');
                    if ($byteStream->load($id)) {
                        if ($bytestreamName == $byteStream->name) {
                            return $byteStream;
                        }
                    } else {
                        throw new Exception('Error loading bytestream');
                    }
                }
            }
        } else {
            throw new Exception('Missing $mediaId $bytestreamName parameters');
        }
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
        $objImg->name = $this->name;
        $objImg->md5 = $this->md5;
        $objImg->size = $this->size;
        $objImg->type = $this->type;
        $objImg->location = $this->location;

        return $objImg;
    }

    private function setValueImage($image, $mediaId, $title, $newName)
    {
        list($_, $type) = explode('/', strtolower($image->getImageMimeType()));
        $myExtension = substr($type, 0, 3);
        if ($myExtension=='jpe') $myExtension = 'jpg';
        $newTitle = $title . '.' . $myExtension;

        $this->uri = 'get/' . $mediaId . '/' . $newName;
        $this->title = $newTitle;
        $this->name = $newName;
        $this->md5 = md5($newTitle);
        $this->type = $myExtension;

        return $newTitle;
    }

    private function setValueVideo($mediaId, $name, $format)
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

    public function nameControll($mediaId, $name)
    {
        if ($mediaId && $name) {
            $media = __ObjectFactory::createModel('dam.models.Media');

            if ($media->load($mediaId)) {
                foreach ($document->bytestream as $id) {
                    $bytestream = __ObjectFactory::createModel('dam.models.ByteStream');
                    if ($bytestream->load($id)) {
                        if ($name == $bytestream->name) {
                            return true;
                        }
                    } else {
                        return true;
                    }
                }
                return false;
            }
        }
        return true;
    }

    public function errorHandler(){
        $error = error_get_last();
        if ($error['type'] === E_ERROR) {
            $this->updateStatus(dam_jobmanager_JobStatus::ERROR);
            $this->save();
            die;
        }

    }
}
