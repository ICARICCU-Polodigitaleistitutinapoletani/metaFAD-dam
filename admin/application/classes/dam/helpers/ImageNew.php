<?php
use Intervention\Image\ImageManager;
use Intervention\Image\Image;

class dam_helpers_ImageNew extends PinaxObject
{
    private $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(array('driver' => __Config::get('dam.image.driver')));
    }


    public function resizeImage($source, $dest, $w, $h, $c, $co)
    {
        $w = $w!='*' ? (int)$w : null;
        $h = $h!='*' ? (int)$h : null;
        // TODO lanciare un'eccezione in caso di valori w e h sbagliati

        $img = $this->manager->make($source);
        if (!$c) {
            $img->resize($w, $h, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        } else {
            // TODO lanciare un'eccezione in caso di valori w e h  non siano validi
            switch ($co) {
                case 0:
                    $position = 'top-left';
                    break;
                case 2:
                    $position = 'bottom-right';
                    break;
                default:
                    $position = 'center';
            }

            $img->fit($w, $h, function ($constraint) {
                $constraint->upsize();
            }, $position);
        }

        $this->addWatermark($img);
        $img->save($dest, __Config::get('JPG_COMPRESSION'));
    }

    private function addWatermark(Intervention\Image\Image $img)
    {
        $watermarkRulesClass = __Config::get('dam.resize.watermark.rules');

        $watermarkRules = $watermarkRulesClass ? pinax_ObjectFactory::createObject($watermarkRulesClass) : null;
        if (!$watermarkRules) return;

        if (!$watermarkRules instanceof dam_interfaces_WatermarkRules) {
            throw pinax_exceptions_InterfaceException::notImplemented('dam.interfaces.WatermarkRules', $watermarkRules);
        }

        $watermarkRules->addWatermark($img, __Config::get('dam.resize.watermark.path'));
    }
}
