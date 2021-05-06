<?php
use Ramsey\Uuid\Uuid;
/*
Reindex folder for uuid
*/

require_once("import_pinax.php");
ini_set('memory_limit', '1024M');

$mainDataProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', 'MainData');
$it = $mainDataProxy->getAr()->createRecordIterator();

$count = 0;
foreach ($it as $d) {
  $mediaId = $d->media_id;
  $mediaModel = __ObjectFactory::createModelIterator("dam.models.Media");
  $m = $mediaModel->where('document_id', $mediaId)->first();
  if($m)
  {
    $d->media_id = $m->uuid;
    $d->publish();
    $count++;
  }
}

echo "Reindicizzati ".$count." record.";
