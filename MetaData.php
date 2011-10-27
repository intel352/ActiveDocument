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
    protected $_propertySchema = array('propVar' => null, 'access' => null, 'type' => null, 'realType' => null, 'size' => null, 'name' => null, 'description' => null, 'defaultValue' => null, 'class' => null);
    protected $_docPropertyRegex = '/\@(?<propVar>property(?:\-(?<access>read|write))?|var)\s+(?<type>[^\s]+)(?:\s+(?:\$(?<name>[\w][[:alnum:]][\w\d]*))(?:\s*(?<description>.+))?)?/';

    /**
     * @var array Array containing relations, properties. attributes is an alias for properties.
     */
    protected $_classMeta;
    protected $_docAttributeRegex = '/\@(?<attribute>\w+)(?<!property|property-read|property-write|var)\s+(?:(\$)\w+\:\s)?\s*(?<value>[^\s]+)\s+\2?\s*(?<comment>.*)?/';

    /**
     * @var array
     */
    protected $_attributeDefaults;

    public function __construct(Document $model) {
        $this->_model = $model;

        if ($model->getContainer() === null)
            throw new Exception(Yii::t('yii', 'The container "{container}" for document class "{class}" cannot be found in the storage media.', array('{class}' => get_class($model), '{container}' => $model->getContainerName())));
    }

    public function &__get($name) {
        switch($name) {
            case 'relations':
                $cm =& $this->getClassMeta();
                return $cm['relations'];
                break;
            case 'properties':
            case 'attributes':
                $cm =& $this->getClassMeta();
                return $cm['properties'];
                break;
        }
        
        $return =& parent::__get($name);
        return $return;
    }

    public function getReflectionClass() {
        if ($this->_reflectionClass === null)
            return $this->_reflectionClass = new \ReflectionClass(get_class($this->_model));
        return $this->_reflectionClass;
    }

    public function &getAttributeDefaults() {
        if ($this->_attributeDefaults === null) {
            $this->_attributeDefaults = array();
            array_walk($this->properties, function($v, $k, &$attrDefs) {
                        if ($v['defaultValue'] !== null)
                            $attrDefs[$k] = $v['defaultValue'];
                    }, $this->_attributeDefaults);
        }
        return $this->_attributeDefaults;
    }

    /**
     * @return array
     */
    public function &getClassMeta() {
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
        $this->_classMeta = array(
            'properties' => array(),
            'relations' => array(),
        );

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

                if (!array_key_exists($prop->name, $this->_classMeta['properties']))
                    $this->_classMeta['properties'][$prop->name] = $this->_propertySchema;

                $this->parsePhpDoc($prop->getDocComment(), $prop);

                $this->_classMeta['properties'][$prop->name]['defaultValue'] = $propDefaults[$prop->name];
                $this->_classMeta['properties'][$prop->name]['class'] = $prop->class;
            }
        }

        foreach ($this->_model->relations() as $name => $config)
            $this->addRelation($name, $config);

        return $this->_classMeta;
    }

    protected function parsePhpDoc($phpdoc, \ReflectionProperty $property = null) {
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
            $this->_classMeta[$matches['attribute']] = $matches;
    }

    protected function parsePhpDocProperties($string, $index, \ReflectionProperty $property = null) {
        if (!preg_match($this->_docPropertyRegex, $string, $matches))
            return false;
        $matches = array_merge($this->_propertySchema, array_intersect_key($matches, $this->_propertySchema));

        if (empty($matches['name'])) {
            if ($property === null)
                return false;
            $matches['name'] = $property->name;
        }

        $varName = ($property !== null) ? $property->name : $matches['name'];
        if (!array_key_exists($varName, $this->_classMeta['properties']))
            $this->_classMeta['properties'][$varName] = $this->_propertySchema;

        array_walk($matches, function($v, $k, &$prop) {
                    if (!isset($prop[$k]) || ($prop[$k] === null && $v !== null))
                        $prop[$k] = $v;
                }, $this->_classMeta['properties'][$varName]);
    }

    /**
     * Adds a relation.
     *
     * $config is an array with the following elements:
     * relation type, the related active document class
     *
     * @throws Exception
     * @param string $name $name Name of the relation.
     * @param array $config $config Relation parameters.
     * @return void
     */
    public function addRelation($name, $config) {
        $cm =& $this->getClassMeta();
        
        /**
         * @todo Quick fix for numeric relations (nested)
         */
        if (is_int($config[0]))
            return;
        if (isset($config[0], $config[1])) {
            $cm['relations'][$name] = new $config[0]($name, $config[1], array_slice($config, 2));
            /**
             * Remove property collisions, which occur from a relation being listed as a property in the model's PHPDOC
             */
            if (isset($cm['properties'][$name]))
                unset($cm['properties'][$name]);
        } else
            throw new Exception(Yii::t('yii', 'Active document "{class}" has an invalid configuration for relation "{relation}". It must specify the relation type and the related active document class.', array('{class}' => get_class($this->_model), '{relation}' => $name)));
    }

    /**
     * Checks if there is a relation with specified name defined.
     *
     * @param string $name $name Name of the relation.
     * @return boolean
     */
    public function hasRelation($name) {
        $cm =& $this->getClassMeta();
        return isset($cm['relations'][$name]);
    }

    /**
     * Deletes a relation with specified name.
     *
     * @param string $name $name
     * @return void
     */
    public function removeRelation($name) {
        $cm =& $this->getClassMeta();
        unset($cm['relations'][$name]);
    }

}