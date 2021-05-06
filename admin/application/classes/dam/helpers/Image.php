<?php

class dam_helpers_Image extends PinaxObject
{
    private $uri;
    private $title;
    private $filename;
    private $name;
    private $md5;
    private $size;
    private $type;
    private $location;

    public function thumbnailManipulation($bytestreamURI, $mediaId, $date = null, $oldTitle = null)
    {
        $uploadsDir = (__Config::get('UPLOAD_DIR'));

        if ($date) {
            $image = new Imagick($uploadsDir . '/' . $date . '/' . $mediaId . '/' . $bytestreamURI);
        } else {
            $image = new Imagick($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $bytestreamURI);
        }

        try {
            // resize the image to a width of 300 and constrain aspect ratio (auto width)
            if ($image->getImageWidth() >= $image->getImageHeight()) {
                $image->thumbnailImage(__Config::get('THUMB_WIDTH'), null);
            } // resize the image to a height of 300 and constrain aspect ratio (auto width)
            else {
                $image->thumbnailImage(null, __Config::get('THUMB_HEIGHT'));
            }
        } catch(Exception $e) {
            $image = new Imagick(__Config::get('IMAGE_THUMB'));
        }


        $title = $oldTitle ? : $this->setValue($image, $mediaId, 'thumbnail');
        $date = $date ? : date('Y-m-d');

        // serve per il resampling dell'immagine
        $image->stripimage();

        // cambia l'estensione del thumbanil a JPG
        // e setta la compressione
        $pathInfo = pathinfo($title);
        $title = $pathInfo['filename'].'.jpg';
        $this->setTitle($title);

        $image->setImageCompression(Imagick::COMPRESSION_JPEG);
        $image->setImageCompressionQuality(__Config::get('JPG_COMPRESSION'));
        $image->writeImage($uploadsDir . '/' . $date . '/' . $mediaId . '/' . $title);

        $this->size = filesize($this->location);
        return $this->createObjImg();
    }

    public function originalResize($bytestreamURI, $mediaId, $date = null)
    {
        $uploadsDir = (__Config::get('UPLOAD_DIR'));
        if ($date) {
            $image = new Imagick($uploadsDir . '/' . $date . '/' . $mediaId . '/' . $bytestreamURI);
        } else {
            $image = new Imagick($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $bytestreamURI);
        }

        if ($image->getImageWidth() > __Config::get('IMG_ORIGINAL_MAX_WIDTH') || $image->getImageHeight() > __Config::get('IMG_ORIGINAL_MAX_HEIGHT')) {
            // resize the image to a width of 300 and constrain aspect ratio (auto width)
            if ($image->getImageWidth() >= $image->getImageHeight()) {
                $image->thumbnailImage(__Config::get('IMG_ORIGINAL_MAX_WIDTH'), null);
            } // resize the image to a height of 300 and constrain aspect ratio (auto width)
            else {
                $image->thumbnailImage(null, __Config::get('IMG_ORIGINAL_MAX_HEIGHT'));
            }
        } else {
            return null;
        }

        // serve per il resampling dell'immagine
        $image->stripimage();

        if ($date) {
            $image->writeImage($uploadsDir . '/' . $date . '/' . $mediaId . '/' . $bytestreamURI);
        } else {
            $image->writeImage($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $bytestreamURI);
        }
        return null;
    }

    public function cropImage($downloadMode, $mediaId, $name, $bytestreamName, $width, $height, $startX, $startY)
    {
        $uploadsDir = (__Config::get('UPLOAD_DIR'));
        $mimeTypeHelper = __ObjectFactory::createObject('dam.helpers.MimeType');
        if (!$name) {
            $name = 'cropped_' . $startX . '_' . $startY;
        }

        if ($this->nameControll($mediaId, $name) && $downloadMode == 'false') {
            return 'error';
        }

        $document = $this->getBytestream($mediaId, $bytestreamName);

        $mediaType = strtoupper($mimeTypeHelper->getMediaTypeFromMime($document->type));
        if ($mediaType != 'IMAGE') {
            return 'error';
        }

        $original = $this->returnUri($document);

        if ($original == 'error') {
            return 'error';
        }

        $image = new Imagick($original);

        $return_original = str_replace($document->title, 'return_' . $document->title, $original);

        if (file_exists($return_original)) {
            $returnImage = new Imagick($return_original);

            $ratio = $image->getImageWidth() / $returnImage->getImageWidth();

            $returnImage->cropImage($width * $ratio, $height * $ratio, $startX * $ratio, $startY * $ratio);

            if ($name) {
                $newTitle = $this->setValue($returnImage, $mediaId, $name);
            } else {
                $newTitle = $this->setValue($returnImage, $mediaId, 'cropped_' . $startX * $ratio . '_' . $startY * $ratio);
            }

            if ($downloadMode == 'true') {
                $returnImage->writeImage($uploadsDir . '/' . $newTitle);
                $this->location = $uploadsDir . '/' . $newTitle;
            } else {
                if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId)) {
                    if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId, 0777, true)) {
                        die('Failed to create folders...');
                    }
                }
                $returnImage->writeImage($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle);
                $this->location = $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle;
            }
        } else {
            $image->cropImage($width, $height, $startX, $startY);

            if ($name) {
                $newTitle = $this->setValue($image, $mediaId, $name);
            } else {
                $newTitle = $this->setValue($image, $mediaId, 'cropped_' . $startX . '_' . $startY);
            }

            if ($downloadMode == 'true') {
                $image->writeImage($uploadsDir . '/' . $newTitle);
                $this->location = $uploadsDir . '/' . $newTitle;
            } else {
                if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId)) {
                    if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId, 0777, true)) {
                        die('Failed to create folders...');
                    }
                }
                $image->writeImage($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle);
                $this->location = $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle;
            }
        }

        $this->size = filesize($this->location);

        return $this->createObjImg();
    }

    public function flipImage($downloadMode, $mediaId, $name, $bytestreamName)
    {
        $uploadsDir = (__Config::get('UPLOAD_DIR'));
        $mimeTypeHelper = __ObjectFactory::createObject('dam.helpers.MimeType');

        if (!$name) {
            $name = 'flip';
        }

        if ($this->nameControll($mediaId, $name) && $downloadMode == 'false') {
            return 'error';
        }

        $document = $this->getBytestream($mediaId, $bytestreamName);

        $mediaType = strtoupper($mimeTypeHelper->getMediaTypeFromMime($document->type));
        if ($mediaType != 'IMAGE') {
            return 'error';
        }

        $original = $this->returnUri($document);

        if ($original == 'error') {
            return 'error';
        }

        $image = new Imagick($original);
        $image->flipImage();

        if ($name) {
            $newTitle = $this->setValue($image, $mediaId, $name);
        } else {
            $newTitle = $this->setValue($image, $mediaId, 'flip');
        }

        if ($downloadMode == 'true') {
            $image->writeImage($uploadsDir . '/' . $newTitle);
            $this->location = $uploadsDir . '/' . $newTitle;
        } else {
            if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId)) {
                if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId, 0777, true)) {
                    die('Failed to create folders...');
                }
            }
            $image->writeImage($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle);
            $this->location = $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle;
        }
        $this->size = filesize($this->location);

        return $this->createObjImg();
    }

    public function flopImage($downloadMode, $mediaId, $name, $bytestreamName)
    {
        $uploadsDir = (__Config::get('UPLOAD_DIR'));
        $mimeTypeHelper = __ObjectFactory::createObject('dam.helpers.MimeType');

        if (!$name) {
            $name = 'flop';
        }

        if ($this->nameControll($mediaId, $name) && $downloadMode == 'false') {
            return 'error';
        }

        $document = $this->getBytestream($mediaId, $bytestreamName);

        $mediaType = strtoupper($mimeTypeHelper->getMediaTypeFromMime($document->type));
        if ($mediaType != 'IMAGE') {
            return 'error';
        }

        $original = $this->returnUri($document);

        if ($original == 'error') {
            return 'error';
        }

        $image = new Imagick($original);
        $image->flopImage();

        if ($name) {
            $newTitle = $this->setValue($image, $mediaId, $name);
        } else {
            $newTitle = $this->setValue($image, $mediaId, 'flip');
        }

        if ($downloadMode == 'true') {
            $image->writeImage($uploadsDir . '/' . $newTitle);
            $this->location = $uploadsDir . '/' . $newTitle;
        } else {
            if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId)) {
                if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId, 0777, true)) {
                    die('Failed to create folders...');
                }
            }
            $image->writeImage($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle);
            $this->location = $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle;
        }
        $this->size = filesize($this->location);

        return $this->createObjImg();
    }

    public function rotateImage($downloadMode, $mediaId, $name, $bytestreamName, $background, $degrees)
    {
        $uploadsDir = (__Config::get('UPLOAD_DIR'));
        $mimeTypeHelper = __ObjectFactory::createObject('dam.helpers.MimeType');

        if (!$name) {
            $name = 'rotated_' . $degrees;
        }

        if ($this->nameControll($mediaId, $name) && $downloadMode == 'false') {
            return 'error';
        }

        $document = $this->getBytestream($mediaId, $bytestreamName);

        $mediaType = strtoupper($mimeTypeHelper->getMediaTypeFromMime($document->type));

        if ($mediaType != 'IMAGE') {
            return 'error';
        }

        $original = $this->returnUri($document);

        if ($original == 'error') {
            return 'error';
        }

        $image = new Imagick($original);
        $image->rotateImage($background, $degrees);

        $newTitle = $this->setValue($image, $mediaId, $name);

        if ($downloadMode == 'true') {
            $image->writeImage($uploadsDir . '/' . $newTitle);
            $this->location = $uploadsDir . '/' . $newTitle;
        } else {
            if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId)) {
                if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId, 0777, true)) {
                    die('Failed to create folders...');
                }
            }
            $image->writeImage($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle);
            $this->location = $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle;
        }
        $this->size = filesize($this->location);
        return $this->createObjImg();
    }

    public function resizeImage($downloadMode, $mediaId, $name, $bytestreamName, $width, $height, $filterType, $blur, $bestFit)
    {
        $uploadsDir = (__Config::get('UPLOAD_DIR'));
        $mimeTypeHelper = __ObjectFactory::createObject('dam.helpers.MimeType');

        if (!$name) {
            $name = 'resized_' . $width . '_' . $height;
        }

        if ($this->nameControll($mediaId, $name) && $downloadMode == 'false') {
            return 'error';
        }

        $document = $this->getBytestream($mediaId, $bytestreamName);

        $mediaType = strtoupper($mimeTypeHelper->getMediaTypeFromMime($document->type));

        if ($mediaType != 'IMAGE') {
            return 'error';
        }

        $original = $this->returnUri($document);

        if ($original == 'error') {
            return 'error';
        }

        $image = new Imagick($original);

        $return_original = str_replace($document->title, 'return_' . $document->title, $original);

        if (file_exists($return_original)) {
            $returnImage = new Imagick($return_original);

            $ratio = $image->getImageWidth() / $returnImage->getImageWidth();

            $returnImage->resizeImage($width * $ratio, $height * $ratio, $filterType, $blur, $bestFit);

            if ($name) {
                $newTitle = $this->setValue($returnImage, $mediaId, $name);
            } else {
                $newTitle = $this->setValue($returnImage, $mediaId, 'resized_' . $width * $ratio . '_' . $height * $ratio);
            }

            if ($downloadMode == 'true') {
                $returnImage->writeImage($uploadsDir . '/' . $newTitle);
                $this->location = $uploadsDir . '/' . $newTitle;
            } else {
                if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId)) {
                    if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId, 0777, true)) {
                        die('Failed to create folders...');
                    }
                }
                $returnImage->writeImage($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle);
                $this->location = $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle;
            }

        } else {
            $image->resizeImage($width, $height, $filterType, $blur, $bestFit);

            if ($name) {
                $newTitle = $this->setValue($image, $mediaId, $name);
            } else {
                $newTitle = $this->setValue($image, $mediaId, 'resized_' . $width . '_' . $height);
            }

            if ($downloadMode == 'true') {
                $image->writeImage($uploadsDir . '/' . $newTitle);
                $this->location = $uploadsDir . '/' . $newTitle;
            } else {
                if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId)) {
                    if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId, 0777, true)) {
                        die('Failed to create folders...');
                    }
                }
                $image->writeImage($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle);
                $this->location = $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle;
            }
        }

        $this->size = filesize($this->location);

        return $this->createObjImg();
    }

    public function setImageFormat($downloadMode, $mediaId, $name, $bytestreamName, $format)
    {
        $uploadsDir = (__Config::get('UPLOAD_DIR'));
        $mimeTypeHelper = __ObjectFactory::createObject('dam.helpers.MimeType');

        if (!$name) {
            $name = 'newFormat_' . $format;
        }
        if ($this->nameControll($mediaId, $name) && $downloadMode == 'false') {
            return 'error';
        }

        $document = $this->getBytestream($mediaId, $bytestreamName);

        $mediaType = strtoupper($mimeTypeHelper->getMediaTypeFromMime($document->type));
        if ($mediaType != 'IMAGE') {
            return 'error';
        }

        $original = $this->returnUri($document);

        if ($original == 'error') {
            return 'error';
        }

        $image = new Imagick($original);
        $image->setImageFormat($format);

        if ($name) {
            $newTitle = $this->setValue($image, $mediaId, $name);
        } else {
            $newTitle = $this->setValue($image, $mediaId, 'newFormat_' . $format);
        }

        if ($downloadMode == 'true') {
            $image->writeImage($uploadsDir . '/' . $newTitle);
            $this->location = $uploadsDir . '/' . $newTitle;
        } else {
            if (!file_exists($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId)) {
                if (!mkdir($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId, 0777, true)) {
                    die('Failed to create folders...');
                }
            }
            $image->writeImage($uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle);
            $this->location = $uploadsDir . '/' . date('Y-m-d') . '/' . $mediaId . '/' . $newTitle;
        }
        $this->size = filesize($this->location);

        return $this->createObjImg();
    }

    public function getBytestream($mediaId, $bytestreamName)
    {
        if ($mediaId && $bytestreamName) {
            $document = __ObjectFactory::createModel('dam.models.Media');

            if ($document->load($mediaId)) {
                if ($document->getType() == 'dam.models.Media') {
                    foreach ($document->bytestream as $id) {
                        $bytestreamAr = __ObjectFactory::createModel('dam.models.ByteStream');
                        if ($bytestreamAr->load($id)) {
                            if ($bytestreamName == $bytestreamAr->name) {
                                return $bytestreamAr;
                            }
                        } else {
                            return 'error';
                        }
                    }
                }
            }
        }
        return 'error';
    }

    public function returnUri($document)
    {
        $uploadDir = __Config::get('UPLOAD_DIR');

        $dateTime = $document->getRawData()->document_creationDate;
        $media_id = $document->media_id;
        $title = $document->title;

        return $uploadDir . '/' . substr($dateTime, 0, 10) . '/' . $media_id . '/' . $title;

    }

    private function createObjImg()
    {
        $objImg = new stdClass();

        $objImg->uri = $this->uri;
        $objImg->title = $this->title;
        $objImg->filename = $this->title;
        $objImg->name = $this->name;
        $objImg->md5 = $this->md5;
        $objImg->size = $this->size;
        $objImg->type = $this->type;
        $objImg->location = $this->location;

        return $objImg;
    }

    private function setValue($image, $mediaId, $name)
    {
        list($_, $type) = explode('/', strtolower($image->getImageMimeType()));
        $myExtension = substr($type, 0, 3);
        if ($myExtension=='jpe') $myExtension = 'jpg';
        $unique = uniqid();
        $newTitle = $name . '_' . $unique . '.' . $myExtension;

        $this->uri = 'get/' . $mediaId . '/' . $name;
        $this->title = $newTitle;
        $this->name = $name;
        $this->md5 = md5($newTitle);
        $this->type = $myExtension;

        return $newTitle;
    }

    private function setTitle($title)
    {
        $this->title = $title;
    }

    public function nameControll($mediaId, $name)
    {
        if ($mediaId && $name) {
            $document = pinax_objectFactory::createModel('dam.models.Media');

            if ($document->load($mediaId)) {
                if ($document->getType() == 'dam.models.Media') {
                    foreach ($document->bytestream as $id) {
                        $document2 = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');
                        if ($document2->load($id)) {
                            if ($name == $document2->name) {
                                return true;
                            }
                        }
                    }
                    return false;
                }
            }
        }
        return true;
    }
}
