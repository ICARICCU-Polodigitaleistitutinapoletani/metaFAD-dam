<?php
use Intervention\Image\Image;

interface dam_interfaces_WatermarkRules
{
    /**
     * @param Intervention\Image\Image $image
     * @param string $watermarkPath
     */
    public function addWatermark(Intervention\Image\Image $img, $watermarkPath);
}
