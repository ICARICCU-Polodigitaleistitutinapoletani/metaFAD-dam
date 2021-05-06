<?php

class dam_models_fallbacks_ImgFallback
{
    private $filePath;
    private $extension;
    private $type;
    private $image;
    private $exifData;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
        $this->extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $this->type = $this->retriveMediaType();
    }

    /**
     * @param  string $command
     * @param  mixed $value
     * @param  dam.instance.models.DataStream $ar
     * @return mixed
     */
    public function apply($command, $value, $ar)
    {
        if (!method_exists($this, $command)) {
            throw dam_exceptions_FallBackException::methodDoesNotExists(get_class($this), $command);
        }
        return call_user_func_array([$this, $command], [$value, $ar]);
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function height($value, $ar)
    {
        if ($value) {
            return $value;
        }

        $this->loadImage();
        return $this->image->getImageHeight();
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function width($value, $ar)
    {
        if ($value) {
            return $value;
        }

        $this->loadImage();
        return $this->image->getImageWidth();
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function x_sampling_frequency($value, $ar)
    {
        if ($value) {
            return $value;
        }

        $this->loadExifData();
        return isset($this->exifData['XResolution']) ? $this->exifData['XResolution'] : '';
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function y_sampling_frequency($value, $ar)
    {
        if ($value) {
            return $value;
        }

        $this->loadExifData();
        return isset($this->exifData['YResolution']) ? $this->exifData['YResolution'] : '';
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function resolution_unit($value, $ar)
    {
        return $value ? $value : 1;
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function sampling_frequency_unit($value, $ar)
    {
        if ($value) {
            return $value;
        }

        $this->loadExifData();
        return isset($this->exifData['ResolutionUnit']) ? $this->exifData['ResolutionUnit'] : 1;
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function sampling_frequency_plane($value, $ar)
    {
        return $value ? $value : 1;
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function bit_per_sample($value, $ar)
    {
        if ($value) {
            return $value;
        }

        $this->loadImage();

        $result = [];
        $depth = $this->image->getImageDepth();
        $colorSpace = $this->image->getImageColorspace();
        switch ($colorSpace) {
            case imagick::COLORSPACE_RGB:
            case imagick::COLORSPACE_SRGB:
                $result = [$depth, $depth, $depth];
                break;
            case imagick::COLORSPACE_CMYK:
                $result = [$depth, $depth, $depth, $depth];
                break;
            case imagick::COLORSPACE_CMY:
                $result = [$depth, $depth, $depth];
                break;
            default:
                $result = [$depth];
                break;
        }

        return implode(',', $result);
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function mime($value, $ar)
    {
        if ($value) {
            return $value;
        }

        $this->loadImage();
        return $this->image->getImageMimeType();
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function size($value, $ar)
    {
        if ($value) {
            return $value;
        }

        $this->loadImage();
        return $this->image->getImageLength();
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function nido_format_name($value, $ar)
    {
        if ($value) {
            return $value;
        }

        return strtoupper($this->extension);
    }


    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function compression($value, $ar)
    {
        if ($value) {
            return $value;
        }

        $this->loadImage();
        $compression = $this->image->getCompression();
        switch ($compression) {
            case imagick::COMPRESSION_NO:
                return 'Uncompressed';
            case imagick::COMPRESSION_LZW:
                return 'LZW';
            case imagick::COMPRESSION_JPEG:
            case imagick::COMPRESSION_JPEG2000:
            case imagick::COMPRESSION_LOSSLESSJPEG:
                return 'JPG';
        }

        $extension = strtoupper($this->extension);
        return is_array(['PNG', 'JPG']) ? $extension : '';
    }

    /**
     * @param  string $value
     * @param  dam.instance.models.DataStream $ar
     * @return int
     */
    private function photometric_interpretation($value, $ar)
    {
        if ($value) {
            return $value;
        }

        $this->loadImage();
        $colorSpace = $this->image->getImageColorspace();
        switch ($colorSpace) {
            case imagick::COLORSPACE_RGB:
            case imagick::COLORSPACE_SRGB:
                return 'RGB';
            case imagick::COLORSPACE_CMYK:
            case imagick::COLORSPACE_CMY:
                return 'CMYK';
            case imagick::COLORSPACE_YCBCR:
                return 'YcbCr';
            case imagick::COLORSPACE_LAB:
                return 'CIELab';
            case imagick::COLORSPACE_TRANSPARENT:
                return 'Transparency Mask';
        }

        return '';
    }

    /**
     * @return string|null
     */
    private function retriveMediaType()
    {
        $fileTypes = json_decode(__Config::get('dam.fileTypes'));
        foreach ($fileTypes as $type=>$extensions) {
            if (in_array(strtolower($this->extension), $extensions)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return void
     */
    private function loadImage()
    {
        if ($this->type==='IMAGE' && !$this->image) {
            $this->image = new Imagick($this->filePath);
        }
    }

    private function loadExifData()
    {
        if ($this->exifData) {
            return;
        }

        $exifData = false;
        if (function_exists('exif_read_data')) {
            set_error_handler(function(){return true;}, E_WARNING);
            $exifData = @exif_read_data($this->filePath, 'EXIF');
            restore_error_handler();
        }

        $this->exifData = $exifData ? $exifData : [];
    }

}
