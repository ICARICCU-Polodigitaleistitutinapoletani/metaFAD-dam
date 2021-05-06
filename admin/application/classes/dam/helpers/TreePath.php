<?php

class dam_helpers_TreePath extends PinaxObject
{
    public function getPath($node){
        return implode("/", array_map(function ($a) {
            return $a->title;
        }, $this->getUberNodes($node)));
    }

    public function getIdPath($node){
        return implode("/", array_map(function ($a) {
            return $a->getId();
        }, $this->getUberNodes($node)));
    }

    private function getUberNodes($node, $upward = true){
        $visited = array($node->getId());
        $nodes = array($node);

        $parentId = null;
        $parentId = $node->parent;

        while ($parentId) {
            if (in_array($parentId, $visited)) {
                throw new Exception("Encountered cyclic structure during getUberNodes, IDs got until cycle: " . implode(", ", $visited));
            }

            $parentNode = __ObjectFactory::createModel("dam.models.CollectionFolder");
            $parentNode->load($parentId);

            $visited[] = $parentNode->getId();
            $nodes[] = $parentNode;

            $parentId = $parentNode->parent;
        }

        return $upward ? array_reverse($nodes) : $nodes;
    }

    public function getAllChildNode($node){
        $childIterator = __ObjectFactory::createModelIterator("dam.models.CollectionFolder")
                          ->where("parent", (string)$node->getId());
        if(!$childIterator->count()){
            return array($node);
        }
        $arr = array($node);
        foreach($childIterator as $child){
            $arr = array_merge($arr, $this->getAllChildNode($child));
        }
        return $arr;
    }
}