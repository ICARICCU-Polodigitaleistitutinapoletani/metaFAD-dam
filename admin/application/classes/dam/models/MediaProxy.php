<?php

class dam_models_MediaProxy extends dam_models_AbstractDocumentProxy
{

    CONST MODEL_NAME = 'dam.models.Media';

    public function modify($id, $data, $comment = '', $mediaId = null, $publish = true, $forceNew = false)
    {
        if ($this->validate($data)) {
            $document = $this->createModel($id, self::MODEL_NAME);
            $document->instance = $this->instance;

            if ($data->mediaType) {
                $document->media_type = $data->mediaType;
            }
            if ($data->DataStream) {
                $document->datastream = $data->DataStream;
            }
            if ($data->ByteStream) {
                $document->bytestream = $data->ByteStream;
            }
            if ($data->related_collectionFolder) {
                $document->related_collectionFolder = $data->related_collectionFolder;
            }
            if ($data->media_child) {
                $document->media_child = $data->media_child;
            }
            try {
                return $document->publish(null, $comment, $forceNew);
            } catch (pinax_validators_ValidationException $e) {
                return $e->getErrors();
            }
        } else {
            // TODO
        }
    }

    public function MediaType($id)
    {
        $document = $this->createModel($id, SELF::MODEL_NAME);
        $it = $document->media_type;
        return $it;
    }

    public function getHistoryIterator($id, $type)
    {
        $it = pinax_objectFactory::createModelIterator('dam.models.Media');
        $it->load('showHistory', array('id' => $id, 'type' => $type));
        return $it;
    }

    public function getAllPerPage($numPerPage, $page)
    {
        $it = pinax_objectFactory::createModelIterator(self::MODEL_NAME);
        $offset = $numPerPage * ($page - 1);
        $it->load('getAllPerPage', array('offset' => $offset, 'num_per_page' => $numPerPage));
        return $it;
    }

    public function getAll()
    {
        $it = pinax_objectFactory::createModelIterator(self::MODEL_NAME);
        $it->load('getAll');
        return $it;
    }

    public function append($allDatastream, $allBytestream, $mediaId, $solrServiceMode, $changedOriginalBytestream = false)
    {
        $document = $this->createModel($mediaId, self::MODEL_NAME);
        $allDatastreamID = array();
        $allBytestreamID = array();
        $allDatastreamSolr = array();
        $allBytestreamSolr = array();

        $editingOriginalBytestream = false;

        if ($allDatastream) {
            foreach ($allDatastream as $datastream) {
                $allDatastreamID[] = $datastream->getId();
                $allDatastreamSolr[$datastream->getType()] = $datastream;
            }
        }
        if ($allBytestream) {
            foreach ($allBytestream as $bytestream) {
                $allBytestreamID[] = $bytestream->getId();
                $allBytestreamSolr[$bytestream->getType()] = $bytestream;
            }
        }

        if ($allDatastreamID) {
            if ($document->getRawData()->datastream) {
                $document->datastream = array_merge($document->datastream, $allDatastreamID);
            } else {
                $document->datastream = $allDatastreamID;
            }
        }
        if ($allBytestreamID) {
            if ($document->getRawData()->bytestream) {
                $document->bytestream = array_merge($document->bytestream, $allBytestreamID);
            } else {
                $document->bytestream = $allBytestreamID;
            }
        }

        $comment = "Aggiornamento ID";
        if ($allDatastream || $allBytestream) {
            $document->publish(null, $comment);
        }

        $solrMapperHelper = __ObjectFactory::createObject('dam.helpers.SolrMapper');
        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
        
        $solrDocument = $solrMapperHelper->mapMediaToSolr($document);
        $solrService->publish($solrDocument);
    }

    public function appendCollectionFolder($collectionFolder, $mediaId)
    {
        $document = $this->createModel($mediaId, self::MODEL_NAME);
        $comment = "Aggiunta Collezione/Cartella: " . $collectionFolder;

        if ($collectionFolder) {
            if ($document->getRawData()->related_collectionFolder) {
                foreach ($document->related_collectionFolder as $related_collectionFolder) {
                    if ($related_collectionFolder == $collectionFolder) {
                        return null;
                    }
                }
                $document->related_collectionFolder = array_merge($document->related_collectionFolder,
                    (array)$collectionFolder);
            } else {
                $document->related_collectionFolder = (array)$collectionFolder;
            }

            $document->publish(null, $comment);
        }

        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

        return $solrService->createSolrDocument($document);
    }


    public function removeCollectionFolder($collectionsFolders, $mediaId)
    {
        $document = $this->createModel($mediaId, self::MODEL_NAME);
        $comment = "Rimossa Collezione/Cartella: " . $collectionsFolders;

        if ($collectionsFolders) {
            $document->related_collectionFolder = $collectionsFolders;
        } else {
            $document->related_collectionFolder = [];
        }
        $document->publish(null, $comment);
        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

        return $solrService->createSolrDocument($document);
    }

    public function appendChild($childId, $mediaId)
    {
        $document = $this->createModel($mediaId, self::MODEL_NAME);
        $comment = "Aggiunto Media Figlio: " . $childId;

        $childId = intval($childId);
        if ($childId) {
            if ($document->getRawData()->media_child) {
                foreach ($document->media_child as $mediaChild) {
                    if ($mediaChild == $childId) {
                        return null;
                    }
                }
                $document->media_child = array_merge($document->media_child, (array)$childId);
            } else {
                $document->media_child = (array)$childId;
            }
            $document->publish(null, $comment);
        }
        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

        return $solrService->createSolrDocument($document);
    }

    public function appendParent($parentId, $mediaId, $isContained = false)
    {
        $document = $this->createModel($mediaId, self::MODEL_NAME);
        $comment = "Aggiunto Media Padre: " . $parentId;

        $parentId = intval($parentId);
        if ($parentId) {
            if ($document->getRawData()->media_parent) {
                foreach ($document->media_parent as $mediaChild) {
                    if ($mediaChild == $parentId) {
                        return null;
                    }
                }
                $document->media_parent = array_merge($document->media_parent, (array)$parentId);
            } else {
                $document->media_parent = (array)$parentId;
            }
            if ($isContained) {
                $document->is_contained = 1;
            }
            $document->publish(null, $comment);
        }
        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

        return $solrService->createSolrDocument($document);
    }

    public function parentControll($id, $parentId = null)
    {
        $document = $this->createModel($id, self::MODEL_NAME);

        if ($document->getRawData()->media_child) {
            foreach ($document->media_child as $mediaChild) {
                if ($mediaChild == $parentId) {
                    return true;
                }
            }
        }
        return false;
    }

    public function deleteId($id, $mediaId)
    {
        $document = $this->createModel($mediaId, self::MODEL_NAME);
        $myArrayData = $document->datastream;
        $myArrayByte = $document->bytestream;

        if (($key = array_search($id, $myArrayData)) !== false) {
            $comment = 'Eliminato datastream: ' . $id;
            unset($myArrayData [$key]);
            $document->datastream = array_values($myArrayData);
            $document->publish(null, $comment);
        }

        if (($key = array_search($id, $myArrayByte)) !== false) {
            $comment = 'Eliminato bytestream: ' . $id;
            unset($myArrayByte[$key]);
            $document->bytestream = array_values($myArrayByte);
            $document->publish(null, $comment);
        }

        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

        return $solrService->createSolrDocument($document);
    }

    public function deleteCollectionFolder($id, $mediaId)
    {
        $document = $this->createModel($mediaId, self::MODEL_NAME);
        $myArrayCollectionFolder = $document->related_collectionFolder;

        if ($myArrayCollectionFolder && ($key = array_search($id, $myArrayCollectionFolder)) !== false) {
            $comment = 'Eliminata collectionFolder: ' . $id;
            unset($myArrayCollectionFolder [$key]);
            $document->related_collectionFolder = array_values($myArrayCollectionFolder);
            $document->publish(null, $comment);
            $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
            $solrService->createSolrDocument($document);
        }

        return;
    }

    public function deleteChildFromParent($childId, $mediaId)
    {
        $document = $this->createModel($mediaId, self::MODEL_NAME);
        $myArrayChild = $document->media_child;

        if ($myArrayChild && ($key = array_search($childId, $myArrayChild)) !== false) {
            $comment = 'Eliminato Figlio: ' . $childId;
            unset($myArrayChild [$key]);
            $document->media_child = array_values($myArrayChild);
            $document->publish(null, $comment);
        }
        
        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

        return $solrService->createSolrDocument($document);
    }

    public function deleteParentFromChild($parentId, $mediaId, $isContained = false)
    {
        $document = $this->createModel($mediaId, self::MODEL_NAME);
        $myArrayParent = $document->media_parent;

        if ($myArrayParent && ($key = array_search($parentId, $myArrayParent)) !== false) {
            $comment = 'Eliminato Padre: ' . $parentId;
            unset($myArrayParent [$key]);
            $document->media_parent = array_values($myArrayParent);
            if ($isContained) {
                $document->is_contained = 0;
            }
            $document->publish(null, $comment);
        }

        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

        return $solrService->createSolrDocument($document);
    }

    public function folderControll($id, $oldId = null)
    {
        $document = $this->createModel($id, self::MODEL_NAME);
        $document2 = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');

        if ($document->getRawData()->related_collectionFolder) {
            foreach ($document->related_collectionFolder as $related_collectionFolder) {
                if ($document2->load($related_collectionFolder)) {
                    if ($document2->getType() == 'dam.models.CollectionFolder') {
                        if ($document2->type == 'folder' && $oldId != $document2->document_id) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function validate($data)
    {
        return true;
    }

}
