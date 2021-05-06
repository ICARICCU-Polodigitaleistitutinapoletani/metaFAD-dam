<?php

class dam_helpers_CollectionFolder extends PinaxObject
{
    public function addCollectionFolder($data, $instance)
    {
        $document = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');
        $document2 = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');
        $mediaProxy = __ObjectFactory::createObject('dam.models.MediaProxy', $instance);
        //$solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

        //Validazione dei dati
        $collectionFolderProxy = __ObjectFactory::createObject('dam.models.CollectionFolderProxy', $instance);
        if (!$collectionFolderProxy->validate($data) && $data) {
            return array('http-status' => 400);
        }

        //controllo se è una cartella e che non vi sia già un'altra cartella relativa al media e inoltre controllo che il media_id esista
        // e che sia effettivamente relativo al media

        if ($data->media_id) {
            foreach ($data->media_id as $id) {
                if ($document2->load($id)) {
                    if ($document2->getType() == 'dam.models.Media') {
                        if ($data->id) {
                            if ($document->load($data->id)) {
                                if ($document->getType() == 'dam.models.CollectionFolder') {
                                    if ($document->type == 'folder') {
                                        if ($mediaProxy->folderControll($id, $data->id)) {
                                            return array('http-status' => 400);
                                        }
                                    }
                                }
                            }
                        } else {
                            if ($data->type == 'folder') {
                                if ($mediaProxy->folderControll($id, $data->id)) {
                                    return array('http-status' => 400);
                                }
                            }
                        }
                    } else {
                        return array('http-status' => 400);
                    }
                } else {
                    return array('http-status' => 400);
                }
            }
        }

        //controllo che l'id parent collection sia effettivamente esistente e che sia appunto una cartella o una collezione

        if ($data->id_parent_collectionFolder) {
            foreach ($data->id_parent_collectionFolder as $id) {
                if ($document2->load($id)) {
                    if ($document2->getType() == 'dam.models.CollectionFolder') {
                    } else {
                        return array('http-status' => 400);
                    }
                } else {
                    return array('http-status' => 400);
                }
            }
        }


        if ($data->id) {
            if ($document->load($data->id)) {
                if ($document->getType() == 'dam.models.CollectionFolder') {
                    $doc = $collectionFolderProxy->modify($data->id, $data, 'Modificata collezione', null);

                    if ($document->getRawData()->id_parent_collectionFolder && $data->id_parent_collectionFolder) {
                        $diffParent = array_diff($document->id_parent_collectionFolder, $data->id_parent_collectionFolder);
                        foreach ($diffParent as $parentId) {
                            $collectionFolderProxy->deleteChildFromCollectionFolder($data->id, $parentId);
                        }
                    }
                    if ($document->getRawData()->media_id) {
                        if($data->media_id){
                            $diff = array_diff($document->media_id, $data->media_id);
                        }
                        else{
                            $diff = $document->media_id;
                        }
                        foreach ($diff as $mediaId) {
                            $mediaProxy->deleteCollectionFolder($data->id, $mediaId);
                        }
                    }

                    if ($data->media_id) {
                        foreach ($data->media_id as $id) {
                            $mediaProxy->appendCollectionFolder($doc->document_id, $id);
                        }
                    }
                    if ($data->id_parent_collectionFolder) {
                        foreach ($data->id_parent_collectionFolder as $id) {
                            $collectionFolderProxy->appendCollectionFolderChild($doc->document_id, $id);
                        }
                    }

                    //$solrService->fromDocCollectionFolderToSolrCollectionFolder($doc);
                    return $doc;
                } else
                    return array('http-status' => 400);
            } else {
                return array('http-status' => 400);
            }
        } else {
            $doc = $collectionFolderProxy->add($data, 'Inserita ' . $data->type, null);
            if ($data->media_id) {
                foreach ($data->media_id as $id) {
                    $mediaProxy->appendCollectionFolder($doc->document_id, $id);
                }
            }
            if ($data->id_parent_collectionFolder) {
                foreach ($data->id_parent_collectionFolder as $id) {
                    $collectionFolderProxy->appendCollectionFolderChild($doc->document_id, $id);
                }
            }

            //$solrService->fromDocCollectionFolderToSolrCollectionFolder($doc);
            return $doc;
        }
    }
}
