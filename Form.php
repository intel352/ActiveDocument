<?php

namespace ext\activedocument;

use CHtml, Yii;

/**
 * @todo Make this more dynamic, so that other form classes could be specified for Form to extend from
 */
Yii::registerAutoloader(function($class) {
    if (strcasecmp($class, 'ext\activedocument\FakeInheritForm') === 0) {
        if (Yii::getPathOfAlias('bootstrap.widgets.BootActiveForm')
            && Yii::import('bootstrap.widgets.BootActiveForm', true) && class_exists('BootActiveForm')
        ) {
            class_alias('BootActiveForm', 'ext\activedocument\FakeInheritForm');
        } else {
            class_alias('CActiveForm', 'ext\activedocument\FakeInheritForm');
        }
    }
});

/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
class Form extends \ext\activedocument\FakeInheritForm {

    public function error($model, $attribute, $htmlOptions = array(), $enableAjaxValidation = true, $enableClientValidation = true) {
        /**
         * Determine input id
         */
        $id = CHtml::activeId($model, $attribute);
        $inputID = isset($htmlOptions['inputID']) ? $htmlOptions['inputID'] : $id;

        /**
         * Let widget process as normal
         */
        $html = parent::error($model, $attribute, $htmlOptions, $enableAjaxValidation, $enableClientValidation);

        /**
         * If inputid exists, update status field as necessary
         */
        if ($model instanceof Document && !$model->isNewRecord && isset($this->attributes[$inputID]))
            $this->attributes[$inputID]['status'] = 1;
        return $html;
    }

}