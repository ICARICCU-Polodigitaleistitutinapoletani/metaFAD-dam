<?php

class dam_exceptions_MediaException extends dam_exceptions_AbstractHttpException
{
    public static function notFound($mediaId)
    {
        return new self('Media not found: '. $mediaId, 404);
    }

    public static function byteStreamNotFound($streamName)
    {
        return new self('Stream not found: '. $streamName, 404);
    }

    public static function byteStreamFileNotFound($filePath)
    {
        return new self('Stream file not found: '. $filePath, 404);
    }

    public static function wrongInstance($mediaId)
    {
        return new self('Wrong instance form media: '. $mediaId, 400);
    }
}
