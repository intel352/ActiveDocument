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
 *
 * @property-read \ReflectionClass $reflectionClass
 * @property-read \ArrayObject $attributes
 * @property-read array $attributeDefaults
 * @property-read \ArrayObject $classMeta
 * @property-read \ArrayObject $properties
 * @property-read \ArrayObject $relations
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
    protected $_docPropertyRegex = '/\@(?<propVar>property(?:\-(?<access>read|write))?|var)\s+(?<realType>[^\s]+)(?:\s+(?:\$(?<name>[\w][[:alnum:]][\w\d]*))(?:\s*(?<description>.+))?)?/';

    /**
     * @var \ArrayObject
     */
    protected $_classMeta;
    protected $_docAttributeRegex = '/\@(?<attribute>\w+)(?<!property|property-read|property-write|var)\s+(?:(\$)\w+\:\s)?\s*(?<value>[^\s]+)\s+\2?\s*(?<comment>.*)?/';

    /**
     * @var \ArrayObject
     */
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

    public function getAttributes($asArray = false) {
        return $this->getProperties($asArray);
    }

    /**
     * Returns the default values for each attribute
     *
     * @return array
     */
    public function getAttributeDefaults() {
        if ($this->_attributeDefaults === null) {
            $this->_attributeDefaults = array();
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
        $reflectionClass = $this->getReflectionClass();

        /**
         * @todo Temporarily disabling, need logic so this only executes when required
        $properties = $relations = array();
        $parentClass = $reflectionClass->getParentClass();
        if($parentClass->getNamespaceName()!='ext\activedocument') {
        $properties = Document::model($parentClass->getName())->getMetaData()->getProperties();
        $relations = Document::model($parentClass->getName())->getMetaData()->getRelations();
        }
         */
        $this->_classMeta = new \ArrayObject(array(
            'properties' => new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS),
            'relations' => new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS),
        ), \ArrayObject::ARRAY_AS_PROPS);

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
                    $this->_classMeta->properties->{$prop->name} = new schema\Property;

                $this->_classMeta->properties->{$prop->name}->name         = $prop->name;
                $this->_classMeta->properties->{$prop->name}->defaultValue = $propDefaults[$prop->name];
                $this->_classMeta->properties->{$prop->name}->class        = $prop->class;

                $this->parsePhpDoc($prop->getDocComment(), $prop);
            }
        }

        /**
         * Add relations (and filters properties that are actually relations)
         */
        foreach ($this->_model->relations() as $name => $config)
            $this->addRelation($name, $config);

        /**
         * Initialize all properties
         */
        foreach ($this->_classMeta->properties as $property)
            $property->init();

        return $this->_classMeta;
    }

    /**
     * @param bool $asArray
     *
     * @return array|\ArrayObject
     */
    public function getProperties($asArray = false) {
        if ($asArray)
            return (array)$this->getClassMeta()->properties;
        return $this->getClassMeta()->properties;
    }

    /**
     * Performs overall cleaning and parsing of class & property phpdoc
     *
     * @param string                   $phpdoc
     * @param null|\ReflectionProperty $property
     */
    protected function parsePhpDoc($phpdoc, \ReflectionProperty $property = null) {
        $phpdoc = $this->cleanPhpDoc($phpdoc);
        if (!empty($phpdoc)) {
            /**
             * Parse class meta
             */
            if ($property === null)
                array_walk($phpdoc, array($this, 'parsePhpDocAttributes'));
            array_walk($phpdoc, array($this, 'parsePhpDocProperties'), $property);
        }
    }

    /**
     * Cleans phpdoc string and returns as an array
     *
     * @param string $phpdoc
     *
     * @return array
     */
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

    /**
     * Parses phpdoc text to find general pattern of "attribute value comment"
     *
     * @param string $string Line of phpdoc
     * @param int    $index
     */
    protected function parsePhpDocAttributes($string, $index) {
        if (preg_match($this->_docAttributeRegex, $string, $matches)) {
            $matches = array_intersect_key($matches, array('attribute' => null, 'value' => null, 'comment' => null));

            if (!array_key_exists($matches['attribute'], $this->_classMeta))
                $this->_classMeta->{$matches['attribute']} = new \ArrayObject($matches, \ArrayObject::ARRAY_AS_PROPS);
        }
    }

    /**
     * Parses phpdoc text to identify class property definitions
     *
     * @param string                   $string Line of phpdoc
     * @param int                      $index
     * @param null|\ReflectionProperty $property
     */
    protected function parsePhpDocProperties($string, $index, \ReflectionProperty $property = null) {
        if (preg_match($this->_docPropertyRegex, $string, $matches)) {
            if (empty($matches['name']) && $property !== null)
                $matches['name'] = $property->name;

            if (($varName = ($property !== null) ? $property->name : $matches['name']))
                if (!$this->hasProperty($varName))
                    $this->addProperty($varName, $matches);
                else
                    $this->_classMeta->properties->$varName->setData($matches);
        }
    }

    /**
     * @param string $name
     * @param array  $config
     * @param bool   $force
     *
     * @return MetaData
     * @throws Exception
     */
    public function addProperty($name, array $config, $force = false) {
        if (!$force && ($this->hasRelation($name) || $this->hasProperty($name))) {
            throw new Exception(
                strtr('Unable to add property "{prop}", relation or property of same name exists!', array('{prop}' => $name))
            );
        }
        $this->getClassMeta()->properties->$name = new schema\Property($config);
        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasProperty($name) {
        return isset($this->getClassMeta()->properties->$name);
    }

    /**
     * @param bool $asArray
     *
     * @return array|\ArrayObject
     */
    public function getRelations($asArray = false) {
        if ($asArray)
            return (array)$this->getClassMeta()->relations;
        return $this->getClassMeta()->relations;
    }

    /**
     * Adds a relation.
     *
     * $config is an array with the following elements:
     * relation type, the related active document class
     *
     * @param string $name   Name of the relation.
     * @param array  $config Relation parameters.
     *
     * @return MetaData
     * @throws Exception
     */
    public function addRelation($name, array $config) {
        if (isset($config[0], $config[1])) {
            $this->getClassMeta()->relations[$name] = new $config[0]($name, $config[1], array_slice($config, 2));
            /**
             * Remove property collisions, which occur from a relation being listed as a property in the model's PHPDOC
             */
            if (isset($this->getClassMeta()->properties[$name]))
                unset($this->getClassMeta()->properties[$name]);
        } else
            throw new Exception(Yii::t('yii', 'Active document "{class}" has an invalid configuration for relation "{relation}". It must specify the relation type and the related active document class.', array('{class}' => get_class($this->_model), '{relation}' => $name)));
        return $this;
    }

    /**
     * Checks if there is a relation with specified name defined.
     *
     * @param string $name Name of the relation.
     *
     * @return boolean
     */
    public function hasRelation($name) {
        return isset($this->getClassMeta()->relations[$name]);
    }

    /**
     * Deletes a relation with specified name.
     *
     * @param string $name
     *
     * @return MetaData
     */
    public function removeRelation($name) {
        unset($this->getClassMeta()->relations[$name]);
        return $this;
    }

}