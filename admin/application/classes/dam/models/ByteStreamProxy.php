<?php

class dam_models_ByteStreamProxy extends dam_models_AbstractDocumentProxy
{
    CONST MODEL_NAME = 'dam.models.ByteStream';

    public function modify($id, $data, $comment = '', $mediaId = null, $publish = true, $forceNew = false)
    {
        if ($this->validate($data)) {

            $document = $this->createModel($id, self::MODEL_NAME);
            $document->instance = $this->instance;

            foreach ($data as $key => $value) {
                $document->$key = $value;
            }
            $fields = json_decode($document->getRawData()->document_detail_object);
            if($fields){
                foreach ($fields as $field => $value) {
                    if(!$data->$field && $field != 'instance'){
                        $document->$field = '';
                    }
                }
            }

            if ($mediaId) {
                $document->media_id = $mediaId;
            }

            try {
                $document->publish(null, $comment, $forceNew);
                return $document;
            } catch (pinax_validators_ValidationException $e) {
                return $e->getErrors();
            }
        } else {
            // TODO
        }
    }

    public function validate($data)
    {
        return true;
    }

    /**
     * Restituisce il document di un bytestream
     * @param  string $instance Nome dell'istanza del DAM
     * @param  string $id       id del model da caricare
     * @param  string $streamName Nome dello stream
     * @return pinax_dataAccessDoctrine_ActiveRecordDocument
     */
    public function getBytestreamByName($instance, $id, $streamName)
    {
        $media = $this->createModelNew($instance, dam_models_MediaProxy::MODEL_NAME, $id);
        if ($media->bytestream) {
            $bytestreamDocument = __ObjectFactory::createModel("dam.models.ByteStream");
            foreach ($media->bytestream as $bytestreamId) {
                $bytestreamDocument->emptyRecord();
                $bytestreamDocument->load($bytestreamId);
                if ($bytestreamDocument->name == $streamName){
                    return $bytestreamDocument;
                }
            }
        }

        throw dam_exceptions_MediaException::byteStreamNotFound($streamName);
    }

    /**
     * Restituisce il path dello stream
     * @param  pinax_dataAccessDoctrine_ActiveRecordDocument $document
     * @return string path del bytestream
     */
    public function streamPath(pinax_dataAccessDoctrine_ActiveRecordDocument $document)
    {
        $creationDate = substr($document->getRawData()->document_creationDate, 0, 10);
        $title = $document->title;
        $mediaId = $document->media_id;
        $uploadDir = __Config::get('UPLOAD_DIR');

        $filePath = $uploadDir . '/' . $creationDate . '/' . $mediaId . '/' . $title;
        $fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');

        if (!$fileSystemHelper->has($filePath)) {
            throw dam_exceptions_MediaException::byteStreamFileNotFound($filePath);
        }

        return $filePath;
    }

    /**
     * Restituisce il path del fiel di cache del bytestrema ridimensionato
     * @param  string $sourcePath
     * @param  int $w
     * @param  int $h
     * @param  int|boolean $crop
     * @param  int $cropOffset
     * @param  int|boolean $force
     * @return string
     */
    public function streamPathForResize($sourcePath, $w, $h, $crop, $cropOffset, $force)
    {
        $pathInfo = pathinfo($sourcePath);
        $fileName = md5($sourcePath.$w.'_'.$h.'_'.$crop.'_'.$cropOffset.'_'.$force).'.'.$pathInfo['extension'];
        $uploadDir = rtrim(__Config::get('UPLOAD_DIR'), '/').'/cache/'.pinax_nestedCachePath($fileName);
        @mkdir($uploadDir, 0777, true);
        $filePath = $uploadDir.$fileName;
        return $filePath;
    }

    public function existsByMd5($md5, $byteStreamName)
    {
        $it = __ObjectFactory::createModelIterator("dam.models.ByteStream")
            ->where('instance', $this->instance)
            ->where('md5', $md5);

        $result = array();
        foreach($it as $ar) {
            //Media load viene fatto perché a quanto pare l'indice può essere sporco talvolta
            $media = __ObjectFactory::createModel("dam.models.Media");
            if ($ar->name==$byteStreamName && $media->load($ar->media_id)) {
                $result[] = $ar->media_id;
            }
        }
        return $result;
    }
}
