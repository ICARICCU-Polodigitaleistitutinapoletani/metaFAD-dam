<?php

class dam_helpers_SolrMapper extends PinaxObject{

    public function mapMediaToSolr($media){
        $treePathHelper = __ObjectFactory::createObject("dam.helpers.TreePath");
        $collectionFolder = __ObjectFactory::createModel("dam.models.CollectionFolder");

        if ($media->collection && is_array($media->collection)) {
            $collectionPathArray = array();
            foreach ($media->collection as $collectionId) {
                $collectionFolder->load((int)$collectionId);
                $collectionPathArray[] = $treePathHelper->getPath($collectionFolder);
            }
            $media->collection = $collectionPathArray;
        }
        if ($media->folder) {
            $collectionFolder->load((int)$media->folder);
            $media->folder = $treePathHelper->getPath($collectionFolder);;
        }

        $streams = array();
        $childDocument = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');

        if ($media->datastream && $media->bytestream) {
            $idsChild = array_merge($media->datastream, $media->bytestream);
        } else if ($media->datastream && !$media->bytestream) {
            $idsChild = $media->datastream;
        } else if ($media->bytestream && !$media->datastream) {
            $idsChild = $media->bytestream;
        }

        $originalSize = 0;
        foreach ($idsChild as $childDocumentID) {
            if ($childDocument->load($childDocumentID)) {
                if ($childDocument->getType() == 'dam.models.ByteStream' && $childDocument->getRawData()->name == 'original') {
                    $streams[$childDocument->getType()] = $childDocument->getRawData();
                    $originalSize = $childDocument->size;
                } else if ($childDocument->getType() != 'dam.models.ByteStream') {
                    $streams[$childDocument->getType()] = $childDocument->getRawData();
                }
            }
        }

        $application = pinax_ObjectValues::get('org.pinax', 'application' );
        $schemaManagerService = $application->retrieveProxy('dam.services.SchemaManagerService');
        $solrDocument = $schemaManagerService->getSolrModelMap();
        foreach ($solrDocument as $k => $v) {
            list($model, $field) = explode(':', $v);
            if ($model == 'self' && $media->{$field}) {
                $solrDocument->{$k} = $media->{$field};
            } else if ($model == 'dam.models.Media' && $media->{$field}) {
                $solrDocument->{$k} = $media->{$field};
            } else if ($streams[$model] && $streams[$model]->{$field}) {
                $solrDocument->{$k} = $streams[$model]->{$field};
            } else {
                if(substr($k, -3, 3) == '_ii'){
                    $solrDocument->{$k} = array();
                }
                else{
                    $solrDocument->{$k} = '';
                }
            }
        }

        $solrDocument->size_i = $originalSize;
        if ($media->datastream) {
            $solrDocument->number_of_datastream_i = count($media->datastream);
        }
        if ($media->bytestream) {
            $solrDocument->number_of_bytestream_i = count($media->bytestream);
        }
        $updateDateTime = new DateTime();
        $solrDocument->update_at_s = $updateDateTime->format('Y-m-d H:i:s');

        return $solrDocument;
    }

}
