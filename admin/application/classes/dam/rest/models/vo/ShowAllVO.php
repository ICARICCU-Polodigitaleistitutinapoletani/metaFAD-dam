<?php

class dam_rest_models_vo_ShowAllVO
{
    public $id;
    public $detailId;
    public $modificationDate;

    public $detailObject;

    public $comment;
    public $user;

    function __construct($id, $detailId, $modificationDate, $detailObject, $comment, $user)
    {
        $this->id = $id;
        $this->detailId = $detailId;
        $this->modificationDate = $modificationDate;

        $this->detailObject = $detailObject;

        $this->comment = $comment;
        $this->user = $user;
    }

    public static function createFromModel($ar)
    {
        return new dam_rest_models_vo_ShowAllVO($ar->getId(),
            $ar->document_detail_id,
            $ar->document_detail_modificationDate,

            $ar->document_detail_object,

            $ar->document_detail_note,
            $ar->user_firstName . ' ' . $ar->user_lastName);
    }
}

