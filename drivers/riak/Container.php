<?php

namespace ext\activedocument\drivers\riak;

class Container extends \ext\activedocument\Container {

    /**
     * @var \riiak\Bucket
     */
    protected $_containerInstance;

    /**
     * @return \riiak\Bucket
     */
    protected function loadContainerInstance() {
        return $this->_adapter->getStorageInstance()->bucket($this->_name);
    }

    public function setProperty($key, $value) {
        $this->_containerInstance->setProperty($key, $value);
        $this->_properties[$key] = $value;
    }

    public function getProperty($key) {
        if (!array_key_exists($key, $this->_properties))
            $this->_properties[$key] = $this->_containerInstance->getProperty($key);
        return $this->_properties[$key];
    }

    protected function loadProperties() {
        return $this->_containerInstance->getProperties();
    }

    /**
     * Overriding default setProperties method, as Riiak supports massively saving properties
     *
     * @param array $properties 
     */
    public function setProperties(array $properties) {
        $this->_containerInstance->setProperties($properties);
        $this->_properties = array_merge($this->_properties, $properties);
    }

    public function delete() {
        return $this->deleteKeys($this->getKeys());
    }

    /**
     * @return array 
     */
    public function getKeys() {
        return $this->_containerInstance->getKeys();
    }

    public function deleteKeys(array $keys) {
        array_map(function(&$object){$object->delete();},$this->getObjects($keys));
        return true;
    }

    /**
     * @param string $key
     * @param mixed $data
     * @param bool $new
     * @return \ext\activedocument\drivers\riak\Object
     */
    public function getObject($key=null, $data=null, $new=false) {
        return new Object($this, $key, $data, $new);
    }

    /**
     * @todo Move bulk of logic to Adapter, to allow fetching of objects across mult containers
     * 
     * @param array $keys
     * @return array \ext\activedocument\drivers\riak\Object
     */
    public function getObjects(array $keys) {
	if(empty($keys)) return array();
        $containerInstance = $this->_containerInstance;
        $objectInstances = array_map(function($key)use(&$containerInstance) {
                    return $containerInstance->newObject($key);
                }, $keys);
        $objectInstances = \riiak\Object::reloadMulti($objectInstances);

        $container = $this;
        return array_map(function(\riiak\Object &$objectInstance)use(&$container) {
                            return new Object($container, $objectInstance->key, $objectInstance->getData());
                        }, $objectInstances);
    }

}
