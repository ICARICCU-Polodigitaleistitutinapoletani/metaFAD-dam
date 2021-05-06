<?php
use Intervention\Image\Image;

class dam_rest_controllers_Main_ResizeBytestream extends pinax_rest_core_CommandRest
{
    use dam_rest_controllers_Main_ResizeTrait;

    /**
     * @param  string $instance       [description]
     * @param  string $mediaId        [description]
     * @param  string $bytestreamName
     * @return mixed
     */
    public function execute($instance, $mediaId, $bytestreamName)
    {
        $this->readParams();

        try{
            if (!$instance || !$mediaId || !$bytestreamName || (!$this->w || !$this->h)) {
                throw new dam_exceptions_BadRequest("Missing parameters");
            }

            list($filePath, $destFilePath) = $this->streamPathForResize($instance, $mediaId, $bytestreamName);
            $this->resizeImage($filePath, $destFilePath);
            pinax_helpers_FileServe::serve($destFilePath);

        } catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}
