<?php

namespace ext\activedocument\validators;

use \Yii,
ext\activedocument\Document,
ext\activedocument\Exception,
ext\activedocument\Criteria;

class Unique extends \CUniqueValidator {

    /**
     * Validates the attribute of the object.
     * If there is any error, the error message is added to the object.
     *
     * @param \ext\activedocument\Document $object    the object being validated
     * @param string                       $attribute the attribute being validated
     */
    protected function validateAttribute($object, $attribute) {
        $value = $object->$attribute;
        if ($this->allowEmpty && $this->isEmpty($value))
            return;

        $className     = $this->className === null ? get_class($object) : Yii::import($this->className);
        $attributeName = $this->attributeName === null ? $attribute : $this->attributeName;
        $finder        = Document::model($className);
        $attributes    = $finder->getMetaData()->getAttributes(true);
        if (!isset($attributes[$attributeName]))
            throw new Exception('Container "'.$className.'" does not have an attribute named "'.$attributeName.'".');

        /**
         * @todo Implement solution for case sensitivity search
         */
        $criteria   = new Criteria(array(
            'search' => array(
                array('column' => $attributeName, 'keyword' => $value, 'like' => true, 'escape' => true),
            ),
        ));
        if ($this->criteria !== array())
            $criteria->mergeWith($this->criteria);

        if (!$object instanceof Document || $object->isNewRecord || $object->containerName !== $finder->containerName)
            $exists = $finder->exists($criteria);
        else
        {
            $criteria->limit = 2;
            $objects         = $finder->findAll($criteria);
            $n               = count($objects);
            if ($n === 1) {
                $exists = array_shift($objects)->getPrimaryKey() != $object->getPrimaryKey();
            }
            else
                $exists = $n > 1;
        }

        if ($exists) {
            $message = $this->message !== null ? $this->message : Yii::t('yii', '{attribute} "{value}" has already been taken.');
            $this->addError($object, $attribute, $message, array('{value}' => \CHtml::encode($value)));
        }
    }

}