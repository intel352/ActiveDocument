<?php

namespace ext\activedocument;

use \Yii,
    \CComponent;

/**
 * @todo Build validation rules based on types, defaults, etc. Need support for custom type validation as well
 * @todo Provide model upgrade support, by checking class version against object version, using version_compare()
 * 
 * For adding validator rules:
 * $this->getModel()->getValidatorList()->add(CValidator::createValidator($validatorName,$this,$attributes,$otherParams));
 */
class MetaData extends CComponent {

    /**
     * @var \ext\activedocument\Document
     */
    protected $_model;
    /**
     * @var \ReflectionClass
     */
    protected $_reflectionClass;
    protected $_propertySchema = array('propVar' => null, 'access' => null, 'type' => null, 'name' => null, 'description' => null, 'defaultValue' => null, 'class' => null);
    protected $_docPropertyRegex = '/\@(?<propVar>property(?:\-(?<access>read|write))?|var)\s+(?<type>[^\s]+)(?:\s+(?:\$(?<name>[\w][[:alnum:]][\w\d]*))(?:\s*(?<description>.+))?)?/';
    /**
     * @var \ArrayObject
     */
    protected $_classMeta;
    protected $_docAttributeRegex = '/\@(?<attribute>\w+)(?<!property|property-read|property-write|var)\s+(?:(\$)\w+\:\s)?\s*(?<value>[^\s]+)\s+\2?\s*(?<comment>.*)?/';
    protected $_attributeDefaults;

    public function __construct(Document $model) {
        $this->_model = $model;

        if ($model->getContainer() === null)
            throw new Exception(Yii::t('yii', 'The container "{container}" for document class "{class}" cannot be found in the storage media.', array('{class}' => get_class($model), '{container}' => $model->getContainerName())));
    }

    public function getReflectionClass() {
        if ($this->_reflectionClass === null)
            return $this->_reflectionClass = new \ReflectionClass(get_class($this->_model));
        return $this->_reflectionClass;
    }

    public function getAttributes() {
        return $this->getProperties()->getArrayCopy();
    }

    public function getAttributeDefaults() {
        if ($this->_attributeDefaults === null) {
            $this->_attributeDefaults = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
            array_walk($this->getProperties(), function($v, $k, &$attrDefs) {
                    if ($v->defaultValue !== null)
                        $attrDefs[$k] = $v->defaultValue;
                }, $this->_attributeDefaults);
        }
        return $this->_attributeDefaults;
    }

    /**
     * @return \ArrayObject
     */
    public function getClassMeta() {
        if ($this->_classMeta !== null)
            return $this->_classMeta;
        $this->_classMeta = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
        $this->_classMeta->properties = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);

        $reflectionClass = $this->getReflectionClass();
        /**
         * Parse class phpdoc (also detects magic properties)
         */
        $this->parsePhpDoc($reflectionClass->getDocComment());

        /**
         * Get physical class properties & metadata from phpdoc if available
         */
        $props = $reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC);
        if (!empty($props)) {
            $propDefaults = $reflectionClass->getDefaultProperties();
            foreach ($props as $prop) {
                /**
                 * Exclude public static properties
                 */
                if ($prop->isStatic())
                    continue;

                if (!array_key_exists($prop->name, $this->_classMeta->properties))
                    $this->_classMeta->properties->{$prop->name} = new \ArrayObject($this->_propertySchema, \ArrayObject::ARRAY_AS_PROPS);

                $this->parsePhpDoc($prop->getDocComment(), $prop);

                $this->_classMeta->properties->{$prop->name}->defaultValue = $propDefaults[$prop->name];
                $this->_classMeta->properties->{$prop->name}->class = $prop->class;
            }
        }
        return $this->_classMeta;
    }

    /**
     * @return \ArrayObject
     */
    public function getProperties() {
        return $this->getClassMeta()->properties;
    }

    protected function parsePhpDoc($phpdoc, \ReflectionProperty $property=null) {
        $phpdoc = $this->cleanPhpDoc($phpdoc);
        if (empty($phpdoc))
            return false;

        /**
         * Parse class meta
         */
        if ($property === null)
            array_walk($phpdoc, array($this, 'parsePhpDocAttributes'));
        array_walk($phpdoc, array($this, 'parsePhpDocProperties'), $property);
    }

    protected function cleanPhpDoc($phpdoc) {
        /**
         * Split by newlines
         */
        $phpdoc = preg_split('/[\n\r]/', $phpdoc, null, PREG_SPLIT_NO_EMPTY);
        /**
         * Filter down to only lines with alphanumeric chars
         */
        if (!empty($phpdoc))
            $phpdoc = preg_grep('/[\w\d]+/', $phpdoc);
        /**
         * Trim out whitespace & asterisks
         */
        if (!empty($phpdoc))
            array_walk($phpdoc, function(&$var) {
                    $var = trim($var, " \t\r\n\0\x0B*");
                });
        return $phpdoc;
    }

    protected function parsePhpDocAttributes($string, $index) {
        if (!preg_match($this->_docAttributeRegex, $string, $matches))
            return false;
        $matches = array_intersect_key($matches, array('attribute' => null, 'value' => null, 'comment' => null));

        if (!array_key_exists($matches['attribute'], $this->_classMeta))
            $this->_classMeta->{$matches['attribute']} = new \ArrayObject($matches, \ArrayObject::ARRAY_AS_PROPS);
    }

    protected function parsePhpDocProperties($string, $index, \ReflectionProperty $property=null) {
        if (!preg_match($this->_docPropertyRegex, $string, $matches))
            return false;
        $matches = array_merge($this->_propertySchema, array_intersect_key($matches, $this->_propertySchema));

        if (empty($matches['name'])) {
            if ($property === null)
                return false;
            $matches['name'] = $property->name;
        }

        $varName = ($property !== null) ? $property->name : $matches['name'];
        if (!array_key_exists($varName, $this->_classMeta->properties))
            $this->_classMeta->properties->$varName = new \ArrayObject($this->_propertySchema, \ArrayObject::ARRAY_AS_PROPS);

        array_walk($matches, function($v, $k, &$prop) {
                if (!isset($prop->$k) || ($prop->$k === null && $v !== null))
                    $prop->$k = $v;
            }, $this->_classMeta->properties->$varName);
    }

}