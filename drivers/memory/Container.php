<?php

namespace ext\activedocument\drivers\memory;

/**
 * Container for Memory driver
 */
class Container extends \ext\activedocument\Container {

    protected function loadContainerInstance() {
        return new \ArrayObject(
            array(
                'properties' => array(),
                'objects' => new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS)
            ), \ArrayObject::ARRAY_AS_PROPS);
    }

    protected function loadProperties() {
        return $this->_containerInstance->properties;
    }

    public function setProperty($key, $value) {
        $this->_properties[$key] = $this->_containerInstance->properties[$key] = $value;
    }

    public function delete() {
        $this->initContainer();
        return true;
    }

    /**
     * @return array
     */
    public function getKeys() {
        return array_keys((array)$this->_containerInstance->objects);
    }

    public function deleteKeys(array $keys) {
        foreach ($keys as $key)
            unset($this->_containerInstance->objects[$key]);
        return true;
    }

    /**
     * @param string $key
     * @param mixed $data
     * @param bool $new
     * @return \ext\activedocument\drivers\memory\Object
     */
    public function getObject($key = null, $data = null, $new = false) {
        return new Object($this, $key, $data, $new);
    }

}