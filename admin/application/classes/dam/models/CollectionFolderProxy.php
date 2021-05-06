<?php

class dam_models_CollectionFolderProxy extends dam_models_AbstractDocumentProxy
{
    CONST MODEL_NAME = 'dam.models.CollectionFolder';

    public function modify($id, $data, $comment = '', $mediaId = null, $publish = true, $forceNew = false)
    {
        if ($this->validate($data)) {
            $document = $this->createModel($id, SELF::MODEL_NAME);

            $document->instance = $this->instance;

            foreach ($data as $key => $value) {
                $document->$key = $value;
            }
            $fields = json_decode($document->getRawData()->document_detail_object);
            if ($fields) {
                foreach ($fields as $field => $value) {
                    if (!$data->$field && $field != 'instance') {
                        $document->$field = '';
                    }
                }
            }

            $document->media_id = $data->media_id;


            try {
                $document->publish(null, $comment, $forceNew);
                return $document;
            } catch (pinax_validators_ValidationException $e) {
                return $e->getErrors();
            }
        } else {
            // TODO
        }
    }

    public function appendMedia($mediaId, $collectionFolderId)
    {
        $document = $this->createModel($collectionFolderId, self::MODEL_NAME);
        $comment = "Aggiunto Media: " . $mediaId;

        if ($mediaId) {
            if ($document->getRawData()->media_id) {
                foreach ($document->media_id as $related_collectionFolder) {
                    if ($related_collectionFolder == $mediaId) {
                        return null;
                    }
                }
                $document->media_id = array_merge($document->media_id, (array)$mediaId);
            } else {
                $document->media_id = (array)$mediaId;
            }
            $document->publish(null, $comment);
        }

        //$solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
        //return $solrService->fromDocCollectionFolderToSolrCollectionFolder($document);
        return array("http-status" => 200);
    }

    public function appendCollectionFolderChild($collectionFolderChild, $collectionFolderParent)
    {
        $document = $this->createModel($collectionFolderParent, self::MODEL_NAME);
        $comment = "Aggiunta Collezione/Cartella Figlia: " . $collectionFolderChild;

        if ($collectionFolderChild) {
            if ($document->getRawData()->id_child_collectionFolder) {
                foreach ($document->id_child_collectionFolder as $related_collectionFolder) {
                    if ($related_collectionFolder == $collectionFolderChild) {
                        return null;
                    }
                }
                $document->id_child_collectionFolder = array_merge($document->id_child_collectionFolder, (array)$collectionFolderChild);
            } else {
                $document->id_child_collectionFolder = (array)$collectionFolderChild;
            }
            $document->publish(null, $comment);
        }

        //$solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
        //return $solrService->fromDocCollectionFolderToSolrCollectionFolder($document);
        return array("http-status" => 200);
    }

    public function deleteMediaFromCollectionFolder($mediaId, $collectionFolderId)
    {
        $document = $this->createModel($collectionFolderId, self::MODEL_NAME);
        $myArrayMedia = $document->media_id;
        if (($key = array_search($mediaId, (array)$myArrayMedia)) !== false) {
            $comment = 'Eliminato Media: ' . $mediaId;
            unset($myArrayMedia [$key]);
            $document->media_id = array_values($myArrayMedia);
            $document->publish(null, $comment);
        }

        //$solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
        //return $solrService->fromDocCollectionFolderToSolrCollectionFolder($document);
        return array("http-status" => 200);
    }

    public function deleteChildFromCollectionFolder($collectionFolderChild, $collectionFolderId)
    {
        $document = $this->createModel($collectionFolderId, self::MODEL_NAME);
        $myArrayMedia = $document->id_child_collectionFolder;

        if (($key = array_search($collectionFolderChild, $myArrayMedia)) !== false) {
            $comment = 'Eliminata Collezione/Cartella Figlia: ' . $collectionFolderChild;
            unset($myArrayMedia [$key]);
            $document->id_child_collectionFolder = array_values($myArrayMedia);
            $document->publish(null, $comment);
        }

        //$solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
        //return $solrService->fromDocCollectionFolderToSolrCollectionFolder($document);
        return array("http-status" => 200);
    }

    public function validate($data)
    {
        return true;
    }

}
