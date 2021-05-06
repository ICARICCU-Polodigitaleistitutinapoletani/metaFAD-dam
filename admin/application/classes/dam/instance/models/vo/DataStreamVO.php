<?php
class dam_instance_models_vo_DataStreamVO
{
    public function __construct($ar, $statusCode = 200)
    {
        $this->id = $ar->getId();
        $values = $ar->getValuesAsArray(false, true, false, false);
        foreach ($values as $key => $value) {
            if (!in_array($key, array('instance', 'fk_id'))) {
                $this->{$key} = $value;
            }
        }

        if ($statusCode) {
            $this->httpStatus = $statusCode;
        }
    }
}