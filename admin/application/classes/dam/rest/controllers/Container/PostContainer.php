<?php
use Ramsey\Uuid\Uuid;

class dam_rest_controllers_Container_PostContainer extends pinax_rest_core_CommandRest
{
    function execute($instance)
    {
        try {
            if ($instance) {
                $data = json_decode(__Request::get('__postBody__'));

                if ($data->MainData) {
                    $container = __ObjectFactory::createModel("dam.models.Media");
                    $container->instance =$instance;
                    $container->uuid = Uuid::uuid4()->toString();
                    $container->media_type = "CONTAINER";

                    $mainDataProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', 'MainData');
                    $mainDataId = $mainDataProxy->publish($instance, $container->uuid, $data->MainData);
                    $container->datastream = array($mainDataId);

                    if ($data->mediaId) {
                        $fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');
                        $bytestreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy');
                        $bytestreamAr = $bytestreamProxy->getBytestreamByName($instance, $data->mediaId, 'original');

                        $filePath = $bytestreamProxy->streamPath($bytestreamAr);
                        $pathInfo = pathinfo($filePath);
                        $fileName = sprintf('%s%s.%s', $fileSystemHelper->getUploadDir(), md5($filePath.time()), $pathInfo['extension']);
                        $fileSystemHelper->copy($filePath, $fileName);
                        $data->bytestream = $fileName;
                    }

                    if ($data->bytestream) {
                        $bytestream = new stdClass();
                        $bytestream->uri = $data->bytestream;
                        $bytestreamHelper = __ObjectFactory::createModel("dam.helpers.ByteStream");
                        $bytestreamPath = $bytestreamHelper->existsBytestream($bytestream);

                        $fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');

						if ($fileSystemHelper->has($bytestreamPath)) {
                            $bytestream = $bytestreamHelper->addBytestream($instance, $bytestreamPath, $container->uuid, false);

                            $bytestreamId = array();
                            foreach ($bytestream as $b) {
                                $bytestreamId[] = $b->getId();
                            }
                            $container->bytestream = $bytestreamId;
                        }
                    }

                    $container->publish();

                    $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                    $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

                    $solrDocument = $solrMapperHelper->mapMediaToSolr($container);
                    $solrService->publish($solrDocument);

                    $response = new stdClass();
                    $response->id = $container->uuid;
                    $response->httpStatus = 201;
                    return $response;
                } else {
                    throw new dam_exceptions_BadRequest("Missing parameters");
                }
            } else {
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        } catch(Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}
