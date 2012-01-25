<?php

namespace ext\activedocument\schema;

use \CComponent;

class Property extends CComponent {
    /**
     * @var string The phpdoc property indicator (eg. property|var)
     */
    public $propVar;
    /**
     * @var string Access level of property (eg. read|write)
     */
    public $access;
    /**
     * @var string Class that this property belongs to.
     */
    public $class;
    /**
     * @var string Name of this property.
     */
    public $name;
    /**
     * @var string Description of this property.
     */
    public $description;
    /**
     * @var boolean Whether this property can be null.
     */
    public $allowNull;
    /**
     * @var string The real type of this property.
     */
    public $realType;
    /**
     * @var string The translated PHP type of this property.
     */
    public $type;
    /**
     * @var mixed Default value of this property
     */
    public $defaultValue;
    /**
     * @var integer Size of the property.
     */
    public $size;
    /**
     * @var integer Precision of the property data, if it is numeric.
     */
    public $precision;
    /**
     * @var integer Scale of the property data, if it is numeric.
     */
    public $scale;

    /**
     * @var array Conversion map of generic type (keys) to realType (values)
     */
    protected $_typeMap = array(
        'boolean' => array('bool', 'boolean'),
        'integer' => array('int', 'integer', 'timestamp'),
        'double' => array('float', 'double', 'number'),
        'string' => array('string', 'date', 'time', 'datetime', 'mixed'),
    );

    public function __construct(array $data = array()) {
        if ($data !== array())
            $this->setData($data);
    }

    /**
     * Initializes a Property instance, which sets realType & type
     */
    public function init() {
        if ($this->realType === null && $this->defaultValue !== null)
            $this->extractRealType();
        $this->extractType();
    }

    /**
     * Describe a Property using a k=>v array
     *
     * @param array $data
     */
    public function setData(array $data = array()) {
        $properties = array_flip(array('propVar', 'access', 'class', 'name', 'description', 'allowNull', 'realType', 'type', 'defaultValue', 'size', 'precision', 'scale'));
        foreach (array_intersect_key($data, $properties) as $name => $value)
            if (!isset($this->$name) || ($this->$name === null && $value !== null))
                $this->$name = $value;
    }

    /**
     * Determines realType (if not defined) using defaultValue (if defined)
     *
     * @todo Detect type of value content, if string (eg. datetime)
     */
    protected function extractRealType() {
        /**
         * Unset type when determining realType
         */
        $this->type = null;
        if (is_scalar($this->defaultValue)) {
            if (is_numeric($this->defaultValue)) {
                if (is_float($this->defaultValue))
                    $this->realType = 'float';
                elseif (is_int($this->defaultValue))
                    $this->realType = 'integer';
                else
                    $this->realType = 'number';
            } elseif (is_bool($this->defaultValue)) {
                $this->realType = 'boolean';
            } else {
                $this->realType = 'string';
            }
        }
        elseif (is_array($this->defaultValue))
            $this->realType = 'array';
        elseif (is_object($this->defaultValue))
            $this->realType = 'object';
        elseif (is_resource($this->defaultValue))
            $this->realType = 'resource';
    }

    /**
     * Sets a property's type, based on it's realType value. Defaults to "string" for unknown or advanced types
     *
     * @todo Support realType such as "string|int", and size/lengths of values (somehow)
     * @todo Example supported types: http://www.icosaedro.it/phplint/phpdoc.html#types
     */
    protected function extractType() {
        if ($this->realType === null)
            $this->type = 'string';

        if ($this->type === null)
            foreach ($this->_typeMap as $type => $map) {
                if (in_array($this->realType, $map)) {
                    $this->type = $type;
                    break;
                }
            }

        if ($this->type === null) {
            /**
             * Check for array type
             */
            if (substr_compare($this->realType, 'array', 0) == 0) {
                /**
                 * @todo We're hard-coding arrays as strings for now...
                 */
                $this->type = 'string';
                /**
                 * @todo Implement advanced array rule matching. Rudimentary start below
                 * @todo Structure of array could imply a selection would occur, and even perform value type validation
                 */
                #$property->type = 'array';
                #preg_match('/array(\[\]((\[\])*[\w\\\\]+)*)*/', $property->realType, $matches);
            } else
                /**
                 * @todo Support object/resource types specifically, for validation at the least
                 */
                $this->type = 'string';
        }
    }

}