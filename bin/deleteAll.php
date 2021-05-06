<?php
require_once("import_pinax.php");

echo "Deleting all medias...".PHP_EOL;

deleteAllFromDB();
deleteAllFromSOLR();

$baseDir = __Paths::get('ROOT').__Config::get('UPLOAD_DIR');

foreach (glob($baseDir.'/*') as $subDir) {
    if (pathinfo($subDir, PATHINFO_BASENAME) !== 'cache') {
        pinax_helpers_Files::deleteDirectory($subDir);
    }
}

echo "Medias deleted";

function deleteAllFromDB()
{
    $conn = pinax_dataAccessDoctrine_DataAccess::getconnection(0);
    $sql = <<<EOD
TRUNCATE TABLE documents_tbl;
TRUNCATE TABLE documents_detail_tbl;
TRUNCATE TABLE documents_index_date_tbl;
TRUNCATE TABLE documents_index_datetime_tbl;
TRUNCATE TABLE documents_index_fulltext_tbl;
TRUNCATE TABLE documents_index_int_tbl;
TRUNCATE TABLE documents_index_text_tbl;
TRUNCATE TABLE documents_index_time_tbl;
EOD;
    $conn->exec($sql);
}

function deleteAllFromSOLR()
{
    $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
    $solrService->delete('*');
}