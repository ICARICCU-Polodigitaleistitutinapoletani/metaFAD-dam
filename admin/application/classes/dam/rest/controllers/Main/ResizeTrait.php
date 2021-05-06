<?php
use Intervention\Image\Image;

trait dam_rest_controllers_Main_ResizeTrait
{
    private $w;
    private $h;
    private $f;
    private $c;
    private $co;

    /**
     * @return void
     */
    private function readParams()
    {
        $this->w = __Request::get('w', '*');
        $this->h = __Request::get('h', '*');
        $this->force = __Request::get('f', false);
        $this->crop = __Request::get('c', false);
        $this->cropOffset = __Request::get('co', 0);
    }

    /**
     * @param  string $instance
     * @param  string $mediaId
     * @param  string $bytestreamName
     * @return [string, string]
     */
    private function streamPathForResize($instance, $mediaId, $bytestreamName)
    {
        $bytestreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy');
        $bytestreamAr = $bytestreamProxy->getBytestreamByName($instance, $mediaId, $bytestreamName);

        // TODO controllare che il bytestream sia un'immagine
        $filePath = $bytestreamProxy->streamPath($bytestreamAr);
        $destFilePath = $bytestreamProxy->streamPathForResize($filePath, $this->w, $this->h, $this->crop, $this->cropOffset, $this->force);
        return [$filePath, $destFilePath];
    }

    /**
     * @param  string  $filePath
     * @param  string  $destFilePath
     * @return boolean
     */
    private function hasValidCache($filePath, $destFilePath)
    {
        // verifica se il file di destinazione esiste già
        // in questo caso confronta le date dei due file
        // per essere certi che l'immagine originale non è stata modificata
        // NOTE: il controllo della data non viene fatto con dam.helpers.FileSystem
        // perché non c'è possibilità di settare il timestamp
        $fileSystemHelper = __ObjectFactory::createObject('dam.helpers.FileSystem');
        $sourceMTime = filemtime($filePath);
        if ($fileSystemHelper->has($destFilePath)) {
            if ($sourceMTime==filemtime($destFilePath)) {
               return true;
            }
            $fileSystemHelper->delete($destFilePath);
        }

        return false;
    }

    /**
     * @param  string $filePath
     * @param  string $destFilePath
     * @return void
     */
    private function resizeImage($filePath, $destFilePath)
    {
        if ($this->hasValidCache($filePath, $destFilePath)) {
            return;
        }

        $imageHelper = __ObjectFactory::createObject('dam.helpers.ImageNew');
        $imageHelper->resizeImage($filePath, $destFilePath, $this->w, $this->h, $this->crop, $this->cropOffset, $this->force);
        $sourceMTime = filemtime($filePath);
        touch($destFilePath, $sourceMTime);
    }
}
