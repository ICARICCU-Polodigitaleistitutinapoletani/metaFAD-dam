swagger: '2.0'
info:
  title: DAM
  description: Servizi
  version: "1.0.0"
host: localhost

schemes:
  - http
basePath: /meta_dam
produces:
  - application/json

paths:
<?php
  include('yaml/search_paths.yaml'); echo PHP_EOL;
  include('yaml/info.yaml'); echo PHP_EOL;
  include('yaml/collection_folder.yaml'); echo PHP_EOL;
  include('yaml/media.yaml'); echo PHP_EOL;
  include('yaml/container.yaml'); echo PHP_EOL;
  include('yaml/bytestreams.yaml'); echo PHP_EOL;
  include('yaml/datastreams.yaml'); echo PHP_EOL;
  include('yaml/batch.yaml'); echo PHP_EOL;
  include('yaml/autocomplete.yaml'); echo PHP_EOL;
?>