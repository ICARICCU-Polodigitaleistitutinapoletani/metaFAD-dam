<?php

class dam_rest_models_vo_HistoryVO
{
    public $id;
    public $detailId;
    public $modificationDate;
    public $comment;
    public $user;

    function __construct($id, $detailId, $modificationDate, $comment, $user)
    {
        $this->id = $id;
        $this->detailId = $detailId;
        $this->modificationDate = $modificationDate;
        $this->comment = $comment;
        $this->user = $user;
    }

    public static function createFromModel($ar)
    {
        return new dam_rest_models_vo_HistoryVO($ar->getId(),
            $ar->document_detail_id,
            $ar->document_detail_modificationDate,
            $ar->document_detail_note,
            $ar->user_firstName . ' ' . $ar->user_lastName);
    }
}

