<?php
use Ramsey\Uuid\Uuid;

class dam_rest_controllers_Media_PostMedia extends pinax_rest_core_CommandRest
{

    function execute($instance, $uuid)
    {
        try {
            if ($instance) {
                $data = json_decode(__Request::get('__postBody__'));
                $medias = $data->addMedias;

                if($data->nomenclature){
                    $nomenclatureData = $data->nomenclature;
                    if (preg_match('/[a-zA-Z]*(#)+[a-zA-Z]*/', $nomenclatureData->pattern)) {
                        $digit = substr_count($nomenclatureData->pattern, '#');
                        $str_max = str_repeat('9', $digit);
                        $max = intval($str_max);
                        $numMaxMedia = count($medias) * $nomenclatureData->step;
                        $mediaCounter = $nomenclatureData->start;
                        if ($numMaxMedia > $max || $nomenclatureData->start + $numMaxMedia - 1 > $nomenclatureData->end) {
                           throw new dam_exceptions_BadRequest("Nomenclature parameter error");
                        }

                        foreach ($medias as $media) {
                            $number = str_pad($mediaCounter, $digit, '0', STR_PAD_LEFT);
                            $newName = preg_replace("/(#)+/", $number, $nomenclatureData->pattern);
                            if($media->MainData){
                                $extension = pathinfo($media->MainData->filename, PATHINFO_EXTENSION);

                                if (pathinfo($media->MainData->filename, PATHINFO_FILENAME)== $media->MainData->title) {
                                    $media->MainData->title = $newName;
                                }
                                $media->MainData->filename = $newName.'.'.$extension;
                            }
                            $mediaCounter += $nomenclatureData->step;
                        }
                    }
                }

                $bytestreamHelper = __ObjectFactory::createModel("dam.helpers.ByteStream");
                $fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');
                $mimeTypeHelper = __ObjectFactory::createObject('dam.helpers.MimeType');
                $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

                $responseId = array();

                foreach ($medias as $media) {
                    $mainDataProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', 'MainData');
                    $data->instance = $instance;

                    $mediaDocument = __ObjectFactory::createModel("dam.models.Media");

                    if (strpos($media->bytestream, __Config::get('dam.allowedRoot')) === 0) {
                        $baseName = pathInfo($media->bytestream, PATHINFO_BASENAME);
                        $destPath = $fileSystemHelper->getUploadDir().$baseName;
                        if ($media->MainData->filename==$media->bytestream) {
                            $media->MainData->filename = $baseName;
                        }

                        if (!is_dir($fileSystemHelper->getUploadDir())) mkdir($fileSystemHelper->getUploadDir());
                        if (file_exists($destPath)) unlink($destPath);
                        if (__Config::get('dam.allowedRoot.createSymLink')===true) {
                            symlink($media->bytestream , $destPath);
                        } else {
                            // per risolvere problemi di lentezza su cartelle montate in NFS
                            // copy($media->bytestream, $destPath);
                            $startPath = escapeshellarg($media->bytestream);
                            $destPath = escapeshellarg($destPath);
                            exec("cp $startPath $destPath");
                        }

                        $media->bytestream = $baseName;
                    }

                    $bytestream = new stdClass();
                    $bytestream->uri = $media->bytestream;
                    $bytestreamPath = $bytestreamHelper->existsBytestream($bytestream);

                    if ($media->bytestream && $fileSystemHelper->has($bytestreamPath)) {
                        // Creation of Media document
                        $mediaDocument->instance = $instance;

                        if (!$uuid) {
                            // Generate a version 4 (random) UUID object
                            $mediaDocument->uuid = Uuid::uuid4()->toString();
                            $mediaId = $mediaDocument->uuid;
                        } else {
                            $mediaDocument->uuid = $uuid;
                            $mediaId = $uuid;
                        }

                        $dataStreamId = $mainDataProxy->publish($instance, $mediaId, $media->MainData);
                        $datastream = array($dataStreamId);

                        // Creation of the bytestream
                        $bytestream = $bytestreamHelper->addBytestream($instance, $bytestreamPath, $mediaId, false, $datastream);
                        $bytestreamId = array();
                        foreach($bytestream as $b){
                            $bytestreamId[] = $b->getId();
                        }

                        // Setting collection and folder if exists
                        if($media->collection && is_array($media->collection)){
                            $collectionDocument = __ObjectFactory::createModel("dam.models.CollectionFolder");
                            foreach($media->collection as $collectionID){
                                if($collectionDocument->load((int)$collectionID)){
                                    $arr = $collectionDocument->media_id;
                                    $arr[] = (string) $mediaId;
                                    $collectionDocument->media_id = $arr;
                                    $collectionDocument->publish();
                                }
                            }
                            $mediaDocument->collection = $media->collection;
                        }
                        if($media->folder){
                            $folderDocument = __ObjectFactory::createModel("dam.models.CollectionFolder");
                            if($folderDocument->load((int)$media->folder)){
                                $arr = $folderDocument->media_id;
                                $arr[] = (string) $mediaId;
                                $folderDocument->media_id = $arr;
                                $folderDocument->publish();
                            }
                            $mediaDocument->folder = $media->folder;
                        }

                        if($media->containerId){
                            $container = __ObjectFactory::createModel("dam.models.Media");
                            if($container->load($media->containerId)){
                                if($container->media_child && is_array($container->media_child)){
                                    $arr = $container->media_child;
                                }
                                else{
                                    $arr = array();
                                }
                                $arr[] = $mediaId;
                                $container->media_child = $arr;
                                $container->publish();
                                $solrDocument = $solrMapperHelper->mapMediaToSolr($container);
                                $solrService->publish($solrDocument);
                                $mediaDocument->is_contained = true;
                                $mediaDocument->media_parent = array($container->uuid);
                            }
                        }

                        $extension = pathinfo($bytestreamPath, PATHINFO_EXTENSION);
                        $mediaType = strtoupper($mimeTypeHelper->getMediaTypeFromMime($extension));

                        $mediaDocument->datastream = $datastream;
                        $mediaDocument->bytestream = $bytestreamId;
                        $mediaDocument->media_type = $mediaType;
                        $mediaDocument->bytestream_last_update = time();
                        $mediaDocument->saveCurrentPublished();

                        $responseId[] = (string) $mediaDocument->uuid;

                        $solrDocument = $solrMapperHelper->mapMediaToSolr($mediaDocument);
                        $solrService->publish($solrDocument);

                        // Aggiunta degli altri datastream
                        if ($media->datastream) {
                            $controller = pinax_ObjectFactory::createObject('dam.rest.controllers.Media.PostMediaResource', $this->application);
                            foreach ($media->datastream as $dataStreamName => $dataStreamObject) {
                                __Request::set('__postBody__', json_encode($dataStreamObject));
                                try {
                                    $result = $controller->execute($instance, $mediaId, $dataStreamName, '');
                                } catch (dam_exceptions_BadRequest $e) {
                                    throw $e;
                                }
                            }
                        }
                    }
                    else{
                       throw new dam_exceptions_BadRequest("Bytestream doesn't exist");
                    }
                }

                $response = new stdClass();
                $response->httpStatus = 201;
                $response->ids = $responseId;
                return $response;
            }
            else{
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        }
        catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}
