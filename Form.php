<?php

namespace ext\activedocument;

use \CActiveForm;

class Form extends CActiveForm {
    
	public function error($model,$attribute,$htmlOptions=array(),$enableAjaxValidation=true,$enableClientValidation=true) {
        /**
         * Determine input id
         */
		$id=CHtml::activeId($model,$attribute);
		$inputID=isset($htmlOptions['inputID']) ? $htmlOptions['inputID'] : $id;
        
        /**
         * Let widget process as normal
         */
        $html = parent::error($model, $attribute, $htmlOptions, $enableAjaxValidation, $enableClientValidation);
        
        /**
         * If inputid exists, update status field as necessary
         */
		if($model instanceof Document && !$model->isNewRecord && isset($this->attributes[$inputID]))
			$this->attributes[$inputID]['status']=1;
        return $html;
    }
    
}