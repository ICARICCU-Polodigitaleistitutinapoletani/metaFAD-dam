<?php
use Ramsey\Uuid\Uuid;
/*
Reindex folder for uuid
*/

require_once("import_pinax.php");
ini_set('memory_limit', '1024M');

$byteStreamIt = __ObjectFactory::createModelIterator("dam.models.ByteStream");

$count = 0;
foreach ($byteStreamIt as $b) {
  $mediaId = $b->media_id;
  $mediaModel = __ObjectFactory::createModelIterator("dam.models.Media");
  $m = $mediaModel->where('document_id', $mediaId)->first();
  if($m)
  {
    $b->media_id = $m->uuid;
    $b->publish();
    $count++;
  }
}

echo "Reindicizzati ".$count." record.";
