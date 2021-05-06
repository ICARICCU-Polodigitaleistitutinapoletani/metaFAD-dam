<?php

class dam_rest_controllers_Main_GetBytestream extends pinax_rest_core_CommandRest
{
    function execute($instance, $mediaId, $bytestreamName)
    {

        try{
            if (!$instance){
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }

            $media = __ObjectFactory::createModel("dam.models.Media");
            if(!$media->load($mediaId) || !($media->instance == $instance || $instance == '*')){
                throw new dam_exceptions_BadRequest("Media not exist in the instance");
            }
            $tmp = explode('?', $bytestreamName);
            $bytestreamName = $tmp[0];
            if($media->bytestream){
                $bytestreamDocument = __ObjectFactory::createModel("dam.models.ByteStream");
                foreach($media->bytestream as $bytestreamId){
                    if($bytestreamDocument->load($bytestreamId) && $bytestreamDocument->name == $bytestreamName){
                        $this->returnFile($mediaId, null, $bytestreamDocument, $bytestreamName == 'original' ? $media->media_type : null);
                    }
                }
            }

            $this->returnFile($mediaId, __Config::get($media->media_type . '_THUMB'), $media);
        }
        catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }

    /**
     * @param  string $mediaId
     * @param  string $staticFile
     * @param  pinax_dataAccessDoctrine_ActiveRecordDocument $document   [description]
     * @param  string $mediaType
     */
    private function returnFile($mediaId, $staticFile, $document = null, $mediaType = null)
    {
        list($file, $filename) = $this->resolveFile($mediaId, $staticFile, $document);

        if(__Request::get('redirect') == 'true')
        {
            //Funzionalità per integrazione con Image Server Cantaloupe
            //eventualmente migliorabile
            $file = str_replace('/opt/data/dam_storage/', 'http://dam/dam_storage',$file);
            header('Location: ' . $file);
            exit;
        }

        if ($mediaType == 'VIDEO') {
            $streamingHelper = __ObjectFactory::createObject('dam.helpers.VideoStream', $file);
            $streamingHelper->start();
            exit;
        }

        $etag = $this->generateEtag($document);
        $lastModifiedTime = filemtime($file);
        $this->checkEtag($etag, $lastModifiedTime);

        $fp = fopen($file, 'rb');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = finfo_file($finfo, $file);
        finfo_close($finfo);


        if ($filename) {
            $disposition = in_array($type, array('application/pdf', 'image/gif', 'image/png', 'image/jpeg')) ? 'inline' : 'attachment';
            header('Content-Disposition: '.$disposition.'; filename=' . $filename);
        }

        header("Content-Type: " . $type);
        header("Content-Length: " . filesize($file));
        header('Cache-Control: private, max-age=120');
        header("Pragma:");
        header("Expires:");
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastModifiedTime).' GMT');
        header('Etag: '.$etag);

        fpassthru($fp);
        exit();
    }

    /**
     * @param  string $mediaId
     * @param  string $staticFile
     * @param  pinax_dataAccessDoctrine_ActiveRecordDocument $document   [description]
     * @return array()
     */
    private function resolveFile($mediaId, $staticFile, $document)
    {
        if ($staticFile) {
            $file = $staticFile;
            $filename = null;
        } else {
            $rowData = $document->getRawData();
            $creationDate = $rowData->document_creationDate;
            $title = $rowData->title;
            $filename = $rowData->filename;
            $mediaId = $rowData->media_id;
            $uploadDir = __Config::get('UPLOAD_DIR');

            if (file_exists($uploadDir . '/' . substr($creationDate, 0, 10) . '/' . $mediaId . '/return_' . $title)) {
                $file = $uploadDir . '/' . substr($creationDate, 0, 10) . '/' . $mediaId . '/return_' . $title;
            } else {
                $file = $uploadDir . '/' . substr($creationDate, 0, 10) . '/' . $mediaId . '/' . $title;
            }

            if (!file_exists($file)) {
                $file = __Config::get('IMAGE_THUMB');
            }
        }

        return array($file, $filename);
    }

    /**
     * @param  pinax_dataAccessDoctrine_ActiveRecordDocument $document
     * @return string
     */
    private function generateEtag($document)
    {
        return md5($document->document_id.$document->instance.$document->document_detail_modificationDate);
    }

    /**
     * @param  string $etag
     * @param  int $lastModifiedTime
     */
    private function checkEtag($etag, $lastModifiedTime)
    {
        $ifModifiedSince = (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false);
        $etagHeader = (isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);

        if (($lastModifiedTime && $ifModifiedSince == $lastModifiedTime) || ($etagHeader == $etag)) {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }
    }
}
