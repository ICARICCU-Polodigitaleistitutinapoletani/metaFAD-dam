<?php
use Intervention\Image\Image;

class dam_rest_controllers_Main_ResizeAndCreateBytestream extends pinax_rest_core_CommandRest
{
    use dam_rest_controllers_Main_ResizeTrait;

    private $datastream;

    /**
     * @param  string $instance
     * @param  string $mediaId
     * @param  string $bytestreamName
     * @param  string $newBytestreamName
     * @return mixed
     */
    public function execute($instance, $mediaId, $bytestreamName, $newBytestreamName)
    {
        $this->readParams();
        $this->datastream = explode(',', __Request::get('datastream'));

        $this->serveByteStream($instance, $mediaId, $newBytestreamName);

        try{
            if (!$instance || !$mediaId || !$bytestreamName || (!$this->w || !$this->h) || !$newBytestreamName) {
                throw new dam_exceptions_BadRequest("Missing parameters");
            }

            list($filePath, $destFilePath) = $this->streamPathForResize($instance, $mediaId, $bytestreamName);
            $this->resizeImage($filePath, $destFilePath);

            __Request::set('__postBody__', json_encode(['addBytestream' => [['name' => $newBytestreamName, 'path' => $destFilePath]]]));
            $r = $this->application->executeCommand('dam.rest.controllers.Media.PostMediaResource', $instance, $mediaId, 'bytestream');
            $this->serveByteStream($instance, $mediaId, $newBytestreamName);

        } catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }

    /**
     * @param  string $instance
     * @param  string $mediaId
     * @param  string $newBytestreamName
     * @return void
     */
    private function serveByteStream($instance, $mediaId, $newBytestreamName)
    {
        try {
            $bytestreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy');
            $bytestreamAr = $bytestreamProxy->getBytestreamByName($instance, $mediaId, $newBytestreamName);
        } catch (dam_exceptions_MediaException $e) {
            return;
        }

        if (!count($this->datastream)) {
            $this->serveFile($bytestreamAr);
        }
        $this->serveMetadata($mediaId, $bytestreamAr);
    }

    /**
     * @param  dam_models_ByteStreamProxy $bytestreamAr
     * @return void
     */
    private function serveFile($bytestreamAr)
    {
        $bytestreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy');
        $filePath = $bytestreamProxy->streamPath($bytestreamAr);
        pinax_helpers_FileServe::serve($filePath);
        exit;
    }

    /**
     * @param  string $mediaId
     * @param  dam_models_ByteStreamProxy $bytestreamAr
     * @return void
     */
    private function serveMetadata($mediaId, $bytestreamAr)
    {
        $bytestreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy');
        $filePath = $bytestreamProxy->streamPath($bytestreamAr);

        $response = [];

        if (in_array('MainData', $this->datastream)) {
            $media = __ObjectFactory::createModel("dam.models.Media");
            if($media->load($mediaId)){
                $mediaDetailVO = __ObjectFactory::createObject("dam.rest.models.vo.MediaDetailVO", $media, ['MainData' => true]);
                $response['MainData'] = $mediaDetailVO->MainData;
            }
        }

        foreach($this->datastream as $datastreamName) {
            $dataStreamProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', $datastreamName);
            if (isset($bytestreamAr->datastream[$datastreamName])) {
                $dataStreamProxy->load($bytestreamAr->datastream[$datastreamName]);
            }
            $response[$datastreamName] = $dataStreamProxy->getDataStreamVO(null, $filePath);
        }

        echo json_encode($response);
        exit;
    }
}
