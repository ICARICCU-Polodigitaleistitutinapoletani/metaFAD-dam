<?php


class dam_rest_controllers_Container_PutContainer extends pinax_rest_core_CommandRest
{

    function execute($instance, $containerId, $modelName, $modelId)
    {
        try {
            if ($instance) {
                $container = __ObjectFactory::createModel("dam.models.Media");
                if ($container->load($containerId) && $container->instance == $instance) {
                    $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                    $data = json_decode(__Request::get('__postBody__'));
                    if ($modelName == "MainData") {
                        $mainDataProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', $modelName);

                        if ($mainDataProxy->load($modelId) && $mainDataProxy->getAr()->fk_id == $container->uuid) {
                            $mainDataProxy->publish($instance, $container->uuid, $data);

                            $document = $solrMapperHelper->mapMediaToSolr($container);
                            $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
                            $solrService->publish($document);
                            return $mainDataProxy->getDataStreamVO();
                        }
                    } else if ($modelName == "bytestream") {
                        if (property_exists($data, 'bytestreamId')) {
                            $this->setCoverByMediaId($instance, $container, $data->bytestreamId);
                            return array('http-status' => '200', 'message' => 'success');
                        } else if (property_exists($data, 'bytestream')) {
                            $this->setCoverByUpload($instance, $container, $data->bytestream);
                            return array('http-status' => '200', 'message' => 'success');
                        }

                        throw new dam_exceptions_BadRequest("Missing bytestream in request payload");

                    } else{
                        throw new dam_exceptions_BadRequest("Resource not exist for the media");
                    }
                } else{
                    throw new dam_exceptions_BadRequest("Media doesn't exist in the instance");
                }
            } else{
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        } catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }

    /**
     * @param string $instance
     * @param dam.models.Media $container
     * @param string $mediaId
     */
    private function setCoverByMediaId($instance, $container, $mediaId)
    {
        $bytestreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy');
        $bytestreamAr = $bytestreamProxy->getBytestreamByName($instance, $mediaId, 'thumbnail');

        $fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');
        $filePath = $bytestreamProxy->streamPath($bytestreamAr);
        $pathInfo = pathinfo($filePath);
        $fileName = sprintf('%s%s.%s', $fileSystemHelper->getUploadDir(), md5($filePath.time()), $pathInfo['extension']);
        $fileSystemHelper->copy($filePath, $fileName);
        $this->setCover($instance, $container, $fileName);
    }

    /**
     * @param string $instance
     * @param dam.models.Media $container
     * @param string $path
     */
    private function setCoverByUpload($instance, $container, $path)
    {
        $bytestreamHelper = __ObjectFactory::createModel("dam.helpers.ByteStream");
        $fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');
        $bytestream = new stdClass();
        $bytestream->uri = $path;
        $bytestreamPath = $bytestreamHelper->existsBytestream($bytestream);

        if (!$fileSystemHelper->has($bytestreamPath)) {
            throw dam_exceptions_MediaException::byteStreamFileNotFound($path);
        }

        $this->setCover($instance, $container, $bytestreamPath);
    }

    /**
     * @param string $instance
     * @param dam.models.Media $container
     * @param string $bytestreamPath
     */
    private function setCover($instance, $container, $bytestreamPath)
    {
        $bytestreamHelper = __ObjectFactory::createModel("dam.helpers.ByteStream");
        $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

        $bytestream = $bytestreamHelper->addBytestream($instance, $bytestreamPath, $container->uuid, false);

        $bytestreamArray = array();
        foreach ($bytestream as $b) {
            $bytestreamArray[] = $b->getId();
        }

        // if ($container->bytestream && is_array($container->bytestream)) {
        //     $bytestreamHelper = __ObjectFactory::createModel("dam.helpers.ByteStream", $container);
        //     $bytestreamHelper->deleteAll($container->bytestream);
        // }
        $container->bytestream = $bytestreamArray;
        $container->publish();
        $document = $solrMapperHelper->mapMediaToSolr($container);
        $solrService->publish($document);
    }
}
