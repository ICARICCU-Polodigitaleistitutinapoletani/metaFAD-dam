<?php

class dam_rest_controllers_ByteStream_ReplaceOriginal extends pinax_rest_core_CommandRest
{
    /** @var  dam_helpers_SolrMapper */
    private $solrMapper;
    /** @var  dam_helpers_SolrService */
    private $solrService;
    /** @var  dam_helpers_FileSystem */
    private $fileSystemHelper;
    /** @var  dam_helpers_ByteStream */
    private $bytestreamHelper;

    private function init()
    {
        $this->solrMapper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
        $this->solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
        $this->fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');
        $this->bytestreamHelper = __ObjectFactory::createModel("dam.helpers.ByteStream");

        set_error_handler(function($a, $b, $c, $d, $e){
            throw new Exception("An error occoured: " . implode(" || ", array_slice(func_get_args(), 0, 4)) . " || context of the error: " . implode("; ", $e));
        }, E_WARNING);
    }

    public function execute($instance, $mediaId)
    {
        try {
            $this->init();

            $payload = $this->getHTTPPayload();

            return $this->replaceOriginal($instance, $mediaId, $payload);
        } catch (Exception $ex) {
            return (object)array(
                "httpStatus" => 400,
                "result" => "KO",
                "message" => $ex->getMessage()
            );
        }
    }

    /**
     * Payload accettabile con le seguenti chiavi:
     * - path/url
     * - desiredFileName
     * @return mixed
     * @throws Exception
     */
    private function getHTTPPayload()
    {
        $ret = json_decode(__Request::get('__postBody__'));

        if (json_last_error()) {
            throw new Exception("During " . __METHOD__ . " call, postBody seems to be json-incompatible: " . json_last_error_msg());
        }

        return $ret;
    }

    private function replaceOriginal($instance, $mediaId, $requestPayload)
    {
        $media = __ObjectFactory::createModel("dam.models.Media");
        $data = $requestPayload;//json_decode(__Request::get('__postBody__'));

        if (!$media->load($mediaId)) {
            throw new Exception("Media with ID $mediaId not found during " . __METHOD__ . " call.");
        }

        $this->controlParameters($instance, $mediaId, $media, $data);

        $bytestreamIDsToRemove = $this->getBytestreamIDsToRemove($media, $data->deleteAll);
        $datastreamIDsToRemove = $this->getDatastreamIDsToRemove($media);

        $baseName = $this->fileSystemHelper->moveFileToUploadDir($data->url);

        $bytestreamIDsToAdd = $this->appendNewOriginalToMedia($instance, $baseName, $data, $media);

        if (!$bytestreamIDsToAdd) {
            throw new Exception(__METHOD__ . " call failed to create new original/thumbnail bytestreams");
        }

        $this->updateMainDataOfMedia($media, $instance, $data);

        $this->removeBytestreamsFromMedia($bytestreamIDsToRemove, $media);
        $this->removeDatastreamsFromMedia($datastreamIDsToRemove, $media);

        $this->saveMedia($media);

        return (object)array(
            "httpStatus" => 200,
            "result" => "OK",
            "message" => "The following bytestreams have been added: " . implode(", ", $bytestreamIDsToAdd)
        );
    }

    /**
     * @param $instance
     * @param $byteStreamData
     * @param $media
     * @return array
     * @throws
     */
    private function addNewOriginal($instance, $byteStreamData, $media)
    {
        $bytestreamPath = $this->bytestreamHelper->existsBytestream($byteStreamData);
        if (!$this->fileSystemHelper->has($bytestreamPath)) {
            throw new Exception("During new original/thumbnail creation, system failed to find $byteStreamData->path");
        }

        //WARNING: la funzione modifica lo stato dell'ultimo parametro passato: aggiorna il campo datastream del media
        $datastreams = $media->datastream;
        $bytestreamIDsToAdd = $this->bytestreamHelper->addBytestream($instance, $bytestreamPath, $media->uuid, false, $datastreams);
        $media->datastream = $datastreams;
        return array_map(function($a){return $a->getId();}, $bytestreamIDsToAdd) ?: array();
    }

    /**
     * @param $instance
     * @param $mediaId
     * @param $media
     * @param $data
     * @throws Exception
     */
    private function controlParameters($instance, $mediaId, $media, $data)
    {
        if (!$instance) {
            throw new Exception("DAM instance not specified during " . __METHOD__ . " call.");
        }

        if ($media->instance != $instance) {
            throw new Exception("There isn't any media with ID $mediaId in DAM instance $instance.");
        }

        if (!$data->url) {
            throw new Exception("During " . __METHOD__ . " call, the request payload doesn't contain any url key. Encoded payload = " . (json_encode($data) ?: '<cannot json_encode>'));
        }
    }

    private function updateMainDataOfMedia($media, $instance, $data)
    {
        //Deduce il filename da mostrare all'utente nel MainData
        $entireFilename = explode("_", end(explode("/", $data->url)));
        array_shift($entireFilename);
        $suggestedFilename = implode("_", $entireFilename);
        $desiredFilename = $data->desiredFileName ?: ($suggestedFilename ?: $data->url);

        $dataStreamProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', "MainData");
        $mediaDetails = __ObjectFactory::createObject("dam.rest.models.vo.MediaDetailVO", $media, array(
            "MainData" => true
        ));

        $currentMainData = $mediaDetails->MainData ?: new stdClass();
        $dataStreamProxy->load($currentMainData->id);
        $currentMainData->filename = $desiredFilename;
        $currentMainData->fk_id = $media->uuid;

        $fkId = $media->uuid;
        $comment = __Request::get('comment', 'Modifica via sostituzione del bytestream (MBD-2125)');
        $dataStreamId = $dataStreamProxy->publish($instance, $fkId, $currentMainData, $comment);
        $media->datastream = $media->datastream ? array_unique(array_merge($media->datastream, array($dataStreamId))) : array($dataStreamId);
        $media->datastream = array_filter($media->datastream);
    }

    /**
     * @param $media
     * @param $deleteAll
     * @return array
     */
    private function getDatastreamIDsToRemove($media, $deleteAll = false)
    {
        $mediaDetails = __ObjectFactory::createObject("dam.rest.models.vo.MediaDetailVO", $media, array(
            "MainData" => true,
            "datastream" => "all"
        ));
        $datastreams = ((array)$mediaDetails->datastream) ?: array();
        $datastreamsToRemove = array_filter(array_map(function ($k, $v) {
            return $k == "Exif" || $k == "NisoVideo" ? $v->id : "";
        }, array_keys($datastreams), $datastreams));

        if ($deleteAll) {
            $datastreamsToRemove = array_unique(array_merge($datastreamsToRemove, $media->datastream));
            return $datastreamsToRemove;
        }
        return $datastreamsToRemove;
    }

    /**
     * @param $media
     * @param $deleteAll
     * @return array
     */
    private function getBytestreamIDsToRemove($media, $deleteAll)
    {
        $mediaDetails = __ObjectFactory::createObject("dam.rest.models.vo.MediaDetailVO", $media, array(
            "MainData" => true,
            "bytestream" => "all"
        ));
        $bytestreams = $mediaDetails->bytestream ?: array();
        $bytestreamIDsToRemove = array_filter(array_map(function ($a) {
            return $a->name == "original" || $a->name == "thumbnail" ? $a->id : "";
        }, $bytestreams));

        if ($deleteAll) {
            $bytestreamIDsToRemove = array_unique(array_merge($bytestreamIDsToRemove, $media->bytestream));
            return $bytestreamIDsToRemove;
        }
        return $bytestreamIDsToRemove;
    }

    /**
     * @param $instance
     * @param $baseName
     * @param $data
     * @param $media
     * @return array
     */
    private function appendNewOriginalToMedia($instance, $baseName, $data, $media)
    {
        $bytestream = $this->bytestreamHelper->initBytestreamData($baseName, "original", $baseName ?: $data->url);
        $bytestreamIDsToAdd = $this->addNewOriginal($instance, $bytestream, $media);
        $media->bytestream = $media->bytestream ? array_unique(array_merge($media->bytestream, $bytestreamIDsToAdd)) : $bytestreamIDsToAdd;
        return $bytestreamIDsToAdd;
    }

    /**
     * @param $media
     */
    private function saveMedia($media)
    {
        $media->bytestream_last_update = time();
        $media->publish();
        $document = $this->solrMapper->mapMediaToSolr($media);
        $this->solrService->publish($document);
    }

    /**
     * @param $bytestreamIDsToRemove
     * @param $media
     */
    private function removeBytestreamsFromMedia($bytestreamIDsToRemove, $media)
    {
        foreach ($bytestreamIDsToRemove as $bytestreamId) {
            $this->bytestreamHelper->delete($bytestreamId);
        }

        $media->bytestream = array_diff($media->bytestream, $bytestreamIDsToRemove);
    }

    /**
     * @param $datastreamsToRemove
     * @param $media
     */
    private function removeDatastreamsFromMedia($datastreamsToRemove, $media)
    {
        $document = __ObjectFactory::createModel("pinax.dataAccessDoctrine.ActiveRecordDocument");

        foreach ($datastreamsToRemove as $datastreamId) {
            if($document->load($datastreamId)) {
                $document->delete();
            }
        }

        $media->datastream = array_diff($media->datastream, $datastreamsToRemove);
    }
}