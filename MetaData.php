<?php

namespace ext\activedocument;
use \Yii, \CComponent;

/**
 * @todo Build validation rules based on types, defaults, etc. Need support for custom type validation as well
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
    /**
     * @var \ArrayObject
     */
    protected $_properties;
    protected $_propertySchema = array('propVar'=>null,'access'=>null,'type'=>null,'name'=>null,'description'=>null,'defaultValue'=>null,'class'=>null);
    protected $_propertyRegex = '/\@(?<propVar>property(?:\-(?<access>read|write))?|var)\s+(?<type>[^\s]+)(?:\s+(?:\$(?<name>[\w][[:alnum:]][\w\d]*))(?:\s*(?<description>.+))?)?/';
    
    protected $_attributeDefaults;

    public function __construct(Document $model) {
        $this->_model = $model;

        if ($model->getContainer() === null)
            throw new Exception(Yii::t('yii', 'The container "{container}" for document class "{class}" cannot be found in the storage media.', array('{class}' => get_class($model), '{container}' => $model->getContainerName())));
    }
    
    public function getReflectionClass() {
        if($this->_reflectionClass===null)
            return $this->_reflectionClass = new \ReflectionClass(get_class($this->_model));
        return $this->_reflectionClass;
    }
    
    public function getAttributes() {
        return $this->getProperties()->getArrayCopy();
    }
    
    public function getAttributeDefaults() {
        if($this->_attributeDefaults===null) {
            $this->_attributeDefaults = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
            array_walk($this->getProperties(), function($v,$k,&$attrDefs){
                if($v->defaultValue!==null)
                    $attrDefs[$k]=$v->defaultValue;
            }, $this->_attributeDefaults);
        }
        return $this->_attributeDefaults;
    }
    
    /**
     *
     * @return \ArrayObject
     */
    public function getProperties() {
        if($this->_properties!==null)
            return $this->_properties;
        $this->_properties = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);

        $reflectionClass = $this->getReflectionClass();
        $this->parsePhpDoc($reflectionClass->getDocComment());
        
        $props = $reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC);
        if(!empty($props)) {
            $propDefaults = $reflectionClass->getDefaultProperties();
            foreach($props as $prop) {
                /**
                 * Exclude public static properties
                 */
                if($prop->isStatic())
                    continue;

                if(!array_key_exists($prop->name, $this->_properties))
                    $this->_properties->{$prop->name} = new \ArrayObject($this->_propertySchema, \ArrayObject::ARRAY_AS_PROPS);

                $this->parsePhpDoc($prop->getDocComment(), $prop);

                $this->_properties->{$prop->name}->defaultValue = $propDefaults[$prop->name];
                $this->_properties->{$prop->name}->class = $prop->class;
            }
        }
        return $this->_properties;
    }
    
    protected function parsePhpDoc($phpdoc, \ReflectionProperty $property=null) {
        $phpdoc = $this->cleanPhpDoc($phpdoc);
        if(!empty($phpdoc))
            array_walk($phpdoc, array($this,'parsePhpDocProperties'), $property);
    }
    
    protected function cleanPhpDoc($phpdoc) {
        /**
         * Split by newlines
         */
        $phpdoc = preg_split('/[\n\r]/', $phpdoc, null, PREG_SPLIT_NO_EMPTY);
        /**
         * Filter down to only lines with alphanumeric chars
         */
        if(!empty($phpdoc))
            $phpdoc = preg_grep('/[\w\d]+/', $phpdoc);
        /**
         * Trim out whitespace & asterisks
         */
        if(!empty($phpdoc))
            array_walk($phpdoc, function(&$var){$var=trim($var, " \t\r\n\0\x0B*");});
        return $phpdoc;
    }
    
    protected function parsePhpDocProperties($string, $index, \ReflectionProperty $property=null) {
        if(!preg_match($this->_propertyRegex, $string, $matches))
            return false;
        $matches = array_merge($this->_propertySchema, array_intersect_key($matches, $this->_propertySchema));
        
        if(empty($matches['name'])) {
            if($property===null)
                return false;
            $matches['name'] = $property->name;
        }

        $varName=($property!==null)?$property->name:$matches['name'];
        if(!array_key_exists($varName, $this->_properties))
            $this->_properties->$varName = new \ArrayObject($this->_propertySchema, \ArrayObject::ARRAY_AS_PROPS);

        array_walk($matches, function($v,$k,&$prop){
            if(!isset($prop->$k) || ($prop->$k===null && $v!==null))
                $prop->$k = $v;
        }, $this->_properties->$varName);
    }
    
}