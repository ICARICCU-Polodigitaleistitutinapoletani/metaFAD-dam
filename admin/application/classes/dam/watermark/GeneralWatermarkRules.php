<?php
use Intervention\Image\ImageManager;
use Intervention\Image\Image;

class dam_watermark_GeneralWatermarkRules implements dam_interfaces_WatermarkRules
{
  /**
     * @param Intervention\Image\Image $image
     * @param string $watermarkPath
     */
    public function addWatermark(Intervention\Image\Image $img, $watermarkPath)
    {
        $width = $img->width();
        $height = $img->height();

        if (!$this->mustApplyWatermark($width, $height)) return;

        $manager = new ImageManager(array('driver' => __Config::get('dam.image.driver')));

        $watermark =  $manager->make($watermarkPath);
        $resizePercentage = 60;
        $watermarkSize = round($width* ((100 - $resizePercentage) / 100), 2);
        $watermark->resize($watermarkSize, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $img->insert($watermark, 'top-left', 20, 20);
        $img->insert($watermark, 'top-right', 20, 20);
        $img->insert($watermark, 'center', 20, 20);
        $img->insert($watermark, 'bottom-left', 20, 20);
        $img->insert($watermark, 'bottom-right', 20, 20);
    }

    private function mustApplyWatermark($width, $height)
    {
        return $width > 400 || $height > 400;
    }
}