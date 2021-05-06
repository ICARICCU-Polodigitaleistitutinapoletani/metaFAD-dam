<?php
class dam_rest_models_vo_AutocompleteVO
{
    public $field;
    public $value;

    function __construct($field, $values)
    {
        $this->field = $field;
        $this->value = $values;
    }

}