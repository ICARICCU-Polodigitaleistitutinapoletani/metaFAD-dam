<?php
class dam_helpers_ByteStream extends PinaxObject
{
    private $fileSystemHelper;
    private $bytestream_data;
    private $storeByteStreamTypes;

    function __construct()
    {
        $this->fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');
        $this->storeByteStreamTypes = __Config::get('dam.store.byteStreamTypes');
    }

    /**
     * @param $baseName
     * @param $name
     * @param $uri
     * @return stdClass
     */
    public function initBytestreamData($baseName, $name, $uri)
    {
        $bytestream = new stdClass();
        $bytestream->uri = ($baseName) ?: $uri;
        $bytestream->name = $name;
        return $bytestream;
    }

    public function addByteStreamType($instance, $name)
    {
        if (!$this->storeByteStreamTypes) return;

        $byteStreamType =__ObjectFactory::createModel('dam.models.ByteStreamType');
        $exists = $byteStreamType->find(array(
            'bytestream_type_instance' => $instance,
            'bytestream_type_name' => $name
        ));

        if (!$exists) {
            $byteStreamType->bytestream_type_instance = $instance;
            $byteStreamType->bytestream_type_name = $name;
            $byteStreamType->save();
        }
    }

    public function addBytestream($instance, $path, $mediaId, $insFromMod, &$datastream = array())
    {
        $mimeTypeHelper = __ObjectFactory::createObject('dam.helpers.MimeType');
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mediaType = strtoupper($mimeTypeHelper->getMediaTypeFromMime($extension));

        $byteStreamData = new stdClass();

        if (!$insFromMod) {
            $byteStreamData->uri = '/get/' . $mediaId . '/original';
            $byteStreamData->name = 'original';
        } else {
            $byteStreamData->uri = '/get/' . $mediaId . '/' . $this->bytestream_data->uri;
            if ($this->bytestream_data->name) {
                $byteStreamData->name = $this->bytestream_data->name;
            } else {
                $byteStreamData->name = $this->bytestream_data->uri;
            }
        }

        $this->addByteStreamType($instance, $byteStreamData->name);

        // NOTA
        // c'è un errore nell'implementazione in questo metodo $this->bytestream_data è valido solo se viene chiamato
        // prima existsBytestream
        // quindi ci sono casi in cui può fallire
        // per raggirare il problema senza modificare l'implementazione è stato aggiunto il controllo
        //
        if (!$this->bytestream_data) {
            $this->bytestream_data = new stdClass();
            $this->bytestream_data->uri = pathinfo($path, PATHINFO_BASENAME);
        }
        $mediaPath = $this->getByteStreamPath(date('Y-m-d'), $mediaId, $this->bytestream_data->uri);

        if(!$this->fileSystemHelper->has($mediaPath)){
            $this->fileSystemHelper->rename($path, $mediaPath);
        }

        $byteStreamData->title = $this->bytestream_data->uri;
        $byteStreamData->filename = $this->bytestream_data->uri;
        $byteStreamData->md5 = $this->fileSystemHelper->md5($mediaPath);
        $byteStreamData->size = $this->fileSystemHelper->getSize($mediaPath);
        $byteStreamData->type = strtolower(pathinfo($byteStreamData->filename, PATHINFO_EXTENSION));
        $byteStreamData->media_id = $mediaId;

        $mediaProxy = __ObjectFactory::createObject('dam.models.MediaProxy', $instance);
        $byteStreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy', $instance);

        if ($mediaType == "IMAGE" && !$insFromMod) {
            list($width, $height) = getimagesize($mediaPath);
            $byteStreamData->width = $width;
            $byteStreamData->height = $height;

            if (function_exists('exif_read_data')) {
                //Fatto perché l'error suppression sembrava non funzionare sull'errore "E_WARNING: File not supported"
                set_error_handler(function(){return true;}, E_WARNING);
                $exifData = @exif_read_data($mediaPath, 'EXIF');
                restore_error_handler();
            }

            $imageHelper = __ObjectFactory::createObject('dam.helpers.Image');
            $imageHelper->originalResize($this->bytestream_data->uri, $mediaId);
            $byteStreamThumbnailData = $imageHelper->thumbnailManipulation($this->bytestream_data->uri, $mediaId);

            if ($exifData) {
                $exifProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', 'Exif');

                if ($exifProxy && $exifProxy->isAssociatedToBytestream()) {
                    // id è null perchè ancora non è stato creato il bytestream
                    $exifDataStreamId = $exifProxy->saveCurrentPublished($instance, null, $exifData);
                    $byteStreamData->datastream = array('Exif' => $exifDataStreamId);

                    $exifThumbnailProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', 'Exif');
                    // id è null perchè ancora non è stato creato il bytestream
                    $exifThumbnailDataStreamId = $exifThumbnailProxy->saveCurrentPublished($instance, null, $exifData);
                    $byteStreamThumbnailData->datastream = array('Exif' => $exifThumbnailDataStreamId);
                } else {
                    $datastream[] = $exifProxy->saveCurrentPublished($instance, $mediaId, $exifData);
                }
            }

            $this->addByteStreamType($instance, $byteStreamThumbnailData->name);

            $obj = $byteStreamProxy->add($byteStreamThumbnailData, 'Inserito ByteStream Thumbnail', $mediaId);
            $byteStreamData->thumbnail_id = $obj->document_id;
            $byteStreamData->thumbnail_detail_id = $obj->document_detail_id;
            $allBytestream[] = $obj;

            if ($exifProxy && $exifThumbnailProxy && $exifThumbnailProxy->isAssociatedToBytestream()) { //TODO: non so perché exifThumbnailProxy === null
                $exifThumbnailProxy->saveCurrentPublished($instance, $obj->getId());
            }
        }

        $obj = $byteStreamProxy->add($byteStreamData, 'Inserito ByteStream', $mediaId);
        $allBytestream[] = $obj;

        if ($exifProxy && $exifProxy->isAssociatedToBytestream()) {
            $exifProxy->saveCurrentPublished($instance, $obj->getId());
        }

        if ($mediaType == "VIDEO" && !$insFromMod) {
            $pathForShell = str_replace(" ", '\ ', $mediaPath);
            // $exifData = @exif_read_data($mediaPath, 'EXIF');
            exec("exiftool " . $pathForShell, $output, $ret);

            $videoHelper = __ObjectFactory::createObject('dam.helpers.Video');
            $byteStreamThumbnailData = $videoHelper->thumbnailManipulation($this->bytestream_data->uri, $mediaId);

            if ($ret == 0) {
                $nisoVideo = new stdClass();
                foreach ($output as $out) {
                    $ar = explode(':', $out);
                    $ar[0] = preg_replace('/[ ]{2,}/', "", $ar[0]);
                    $ar[1] = substr($ar[1], 1);
                    if (!strpos($ar[0], 'Date/Time')) {
                        $nisoVideo->$ar[0] = $ar[1];
                    } else {
                        $nisoVideo->$ar[0] = $ar[1] . ':' . $ar[2] . ':' . $ar[3] . ':' . $ar[4] . ':' . $ar[5] . ':' . $ar[6];
                    }
                }

                $nisoVideoProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', 'NisoVideo');

                if ($nisoVideoProxy && $nisoVideoProxy->isAssociatedToBytestream()) {
                    // id è null perchè ancora non è stato creato il bytestream
                    $nisoDataStreamId = $nisoVideoProxy->saveCurrentPublished($instance, null, $nisoVideo);
                    $byteStreamData->datastream = array('NisoVideo' => $nisoDataStreamId);

                    $nisoThumbnailVideoProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', 'NisoVideo');
                    // id è null perchè ancora non è stato creato il bytestream
                    $nisoThumbnailDataStreamId = $nisoThumbnailVideoProxy->saveCurrentPublished($instance, null, $nisoVideo);
                    $byteStreamThumbnailData->datastream = array('NisoVideo' => $nisoThumbnailDataStreamId);
                } else {
                    $datastream[] = $nisoVideoProxy->saveCurrentPublished($instance, $mediaId, $nisoVideo);
                }
            }

            $obj = $byteStreamProxy->add($byteStreamThumbnailData, 'Inserito ByteStream Thumbnail', $mediaId);
            $allBytestream[] = $obj;

            if ($nisoVideoProxy && $nisoVideoProxy->isAssociatedToBytestream()) {
                $nisoVideoProxy->save($instance, $obj->getId());
            }
        }
        return $allBytestream;
    }

    public function existsBytestream($byteStreamData)
    {
        $this->bytestream_data = $byteStreamData;

        if ($this->fileSystemHelper->has($byteStreamData->uri)) {
            return $byteStreamData->uri;
        }

        $bytestreamURI = $byteStreamData->uri;

        if ($byteStreamData->path) {
            $unique = uniqid();
            $path = $byteStreamData->path . '/' . $bytestreamURI;
            $this->bytestream_data->uri = $unique . $bytestreamURI;
        } else {
            $tempUploadsDir = $this->fileSystemHelper->getUploadDir();
            $path = $tempUploadsDir . $bytestreamURI;
        }

        if ($this->fileSystemHelper->has($path)) {
            return $path;
        } else {
            return null;
        }
    }

    public function delete($id)
    {
        $byteStream = __objectFactory::createModel('dam.models.ByteStream');

        if ($byteStream->load($id)) {
            $this->deleteByAr($byteStream);
        }
    }

    public function deleteAll($byteStreamIds)
    {
        $byteStream = __objectFactory::createModel('dam.models.ByteStream');

        $pathsToDelete = array();

        foreach ($byteStreamIds as $byteStreamId) {
            if ($byteStream->load($byteStreamId)) {
                $pathsToDelete[$this->getByteStreamMediaPath($byteStream)] = true;
                $this->deleteByAr($byteStream);
            }
        }

        foreach (array_keys($pathsToDelete) as $path) {
            pinax_helpers_Files::deleteDirectory($path, null, true);
        }
    }

    public function deleteByAr($byteStream)
    {
        $path = $this->getByteStreamPathByAr($byteStream);

        if ($this->fileSystemHelper->has($path)) {
            if (__Config::get('dam.trash.enabled')) {
                $trashPath = $this->getByteStreamTrashPath($byteStream);
                $this->fileSystemHelper->rename($path, $trashPath);
            } else {
                $this->fileSystemHelper->delete($path);
            }
        }

        if ($byteStream->datastream){
            $dataStream = __objectFactory::createModel('dam.instance.models.DataStream');

            foreach ($byteStream->datastream as $schemaName => $datastreamId){
                $dataStream->delete($datastreamId);
            }
        }

        $byteStream->delete();
    }

    public function getByteStreamPathByAr($byteStream)
    {
        $date = substr($byteStream->getRawData()->document_creationDate, 0, 10);
        return $this->getByteStreamPath($date, $byteStream->media_id, $byteStream->title);
    }

    public function getByteStreamMediaPath($byteStream)
    {
        $date = substr($byteStream->getRawData()->document_creationDate, 0, 10);
        return __Config::get('UPLOAD_DIR') . '/' . $date . '/' . $byteStream->media_id;
    }

    public function getByteStreamPath($date, $mediaId, $title)
    {
        return __Config::get('UPLOAD_DIR') . '/' . $date . '/' . $mediaId . '/' . $title;
    }

    public function getMediaTrashPath($mediaId)
    {
        return __Config::get('UPLOAD_DIR') . '/' . __Config::get('TRASH_DIR') . '/' . $mediaId;
    }

    public function getByteStreamTrashPath($byteStream)
    {
        return __Config::get('UPLOAD_DIR') . '/' . __Config::get('TRASH_DIR') . '/' . date("Y-m-d").'/'.$byteStream->media_id . '/' .(md5(time())).'_'.$byteStream->title;
    }

}
