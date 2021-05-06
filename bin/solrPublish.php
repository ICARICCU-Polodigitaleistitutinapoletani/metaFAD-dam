<?php
/*
re-index a range of medias to SOLR
*/

if (count($argv)!=3) {
    die('Usage: php solrPublish.php from num'.PHP_EOL);
}

require_once("import_pinax.php");
ini_set('memory_limit', '1024M');

echo "Publishing media to SOLR...</br>";

$iterator = __ObjectFactory::createModelIterator("dam.models.Media");
$solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
$solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
$totalMedia = $iterator->count();

$from = (int)$argv[1];
$num = (int)$argv[2];

$from = ($from * $num) + 1;

echo $num . " media to publish.".PHP_EOL;
echo 'from '. $from. ' to '. ($from+$num-1) .PHP_EOL;

$counter = 0;
foreach ($iterator as $media) {
    if ($counter < $from) {
        $counter++;
        continue;
    }

    if ($counter > $from+$num) {
        break;
    }

    $solrDocument = $solrMapperHelper->mapMediaToSolr($media);
    $solrService->publish($solrDocument);

    $counter++;
    echo number_format($counter*100/$totalMedia, 2) . "%: media with id " .  $media->uuid . " published.</br>".PHP_EOL;
}

echo "Publishing finished";
