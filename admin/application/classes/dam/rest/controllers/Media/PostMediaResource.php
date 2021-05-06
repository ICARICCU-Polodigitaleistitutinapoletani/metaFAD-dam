<?php
class dam_rest_controllers_Media_PostMediaResource extends pinax_rest_core_CommandRest
{
    function execute($instance, $mediaId, $modelName)
    {
        try {
            $media = __ObjectFactory::createModel("dam.models.Media");
            if ($instance) {
                if ($media->load($mediaId) && $media->instance == $instance) {
                    $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                    $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
                    $data = json_decode(__Request::get('__postBody__'));
                    if ($modelName == "RelatedMedia") {
                        $relatedMedia = __ObjectFactory::createModel("dam.models.Media");
                        $relatedMediaIds = $data->addMedias;
                        if (is_array($media->media_child)) {
                            $childArray = $media->media_child;
                        } else {
                            $childArray = array();
                        }

                        foreach ($relatedMediaIds as $relatedMediaId) {
                            if ($relatedMedia->load($relatedMediaId) && $relatedMedia->instance == $instance) {
                                if (is_array($relatedMedia->media_parent)) {
                                    $parentArray = $relatedMedia->media_parent;
                                } else {
                                    $parentArray = array();
                                }
                                if (!in_array($media->uuid, $parentArray)) {
                                    $parentArray[] = $media->uuid;
                                    $relatedMedia->media_parent = $parentArray;
                                    $relatedMedia->publish();
                                }
                                $document = $solrMapperHelper->mapMediaToSolr($relatedMedia);
                                $solrService->publish($document);
                                if (!in_array($relatedMediaId, $childArray)) {
                                    $childArray[] = $relatedMediaId;
                                }
                            }
                        }
                        $media->media_child = $childArray;
                    } else if ($modelName == "bytestream") {
                        $fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');
                        $mimeTypeHelper = __ObjectFactory::createObject('dam.helpers.MimeType');
                        $bytestreamHelper = __ObjectFactory::createModel("dam.helpers.ByteStream");
                        if ($data->addBytestream && is_array($data->addBytestream)) {
                            if ($media->bytestream && is_array($media->bytestream)) {
                                $bytestreamId = $media->bytestream;
                            } else {
                                $bytestreamId = array();
                            }
                            foreach ($data->addBytestream as $bytestreamToAdd) {

                                if (strpos($bytestreamToAdd->path, __Config::get('dam.allowedRoot')) === 0) {
                                    //md5 per basename univoco se aggiungo bytestream da path (per evitare ambiguità)
                                    $baseName = md5($bytestreamToAdd->path) . pathInfo($bytestreamToAdd->path, PATHINFO_BASENAME);
                                    $destPath = $fileSystemHelper->getUploadDir().$baseName;
                                    @mkdir($fileSystemHelper->getUploadDir());
                                    @unlink($destPath);
                                    if (__Config::get('dam.allowedRoot.createSymLink')===true) {
                                        symlink($bytestreamToAdd->path , $destPath);
                                    } else {
                                        copy($bytestreamToAdd->path, $destPath);
                                    }
                                }

                                $bytestream = new stdClass();
                                $bytestream->uri = ($baseName) ?: $bytestreamToAdd->url;
                                $bytestream->name = $bytestreamToAdd->name;
                                $bytestreamPath = $bytestreamHelper->existsBytestream($bytestream);
                                if ($fileSystemHelper->has($bytestreamPath)) {
                                    $bytestream = $bytestreamHelper->addBytestream($instance, $bytestreamPath, $media->uuid, true);
                                    foreach ($bytestream as $b) {
                                        $bytestreamId[] = $b->getId();
                                    }
                                }
                            }
                            $media->bytestream = $bytestreamId;
                        }
                    } else {
                        $dataStreamProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', $modelName);
                        $dataStreamId = $dataStreamProxy->publish($instance, $mediaId, $data);

                        $arr = $media->datastream;
                        $arr[] = $dataStreamId;
                        $media->datastream = $arr;
                    }

                    $media->publish();
                    $document = $solrMapperHelper->mapMediaToSolr($media);
                    $solrService->publish($document);
                    if ($dataStreamProxy) {
                        return $dataStreamProxy->getDataStreamVO(201);
                    } else {
                        return array("http-status" => 201, 'message' => 'Resource creted');
                    }
                } else {
                    throw new dam_exceptions_BadRequest("Media doesn't exist in the instance");
                }
            } else {
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        } catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}
