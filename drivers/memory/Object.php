<?php

namespace ext\activedocument\drivers\memory;

/**
 * Object for Memory driver
 */
class Object extends \ext\activedocument\Object {

    protected function loadObjectInstance($new = true) {
        if ($this->getKey() !== null && !$new && isset($this->_container->getContainerInstance()->objects[$this->getKey()]))
            return $this->_container->getContainerInstance()->objects[$this->getKey()];
        return new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @return bool
     */
    protected function storeInternal() {
        $this->setObjectData($this->data);
        $this->setKey($this->getKey());
        return true;
    }

    /**
     * @return bool
     */
    protected function deleteInternal() {
        if ($this->getKey() !== null)
            unset($this->_container->getContainerInstance()->objects[$this->getKey()]);
        return true;
    }

    /**
     * @return bool
     */
    protected function reloadInternal() {
        $this->_objectInstance = $this->loadObjectInstance(false);
        return true;
    }

    protected function updateObjectPosition($position = null) {
        $oldPosition = array_search($this->_objectInstance, (array)$this->_container->getContainerInstance()->objects, true);
        if ($position !== null && $oldPosition !== false && $position === $oldPosition)
            return $position;

        if ($position !== null)
            $this->_container->getContainerInstance()->objects[$position] = $this->_objectInstance;
        else {
            $this->_container->getContainerInstance()->objects[] = $this->_objectInstance;
            $position = array_search($this->_objectInstance, (array)$this->_container->getContainerInstance()->objects, true);
        }

        /**
         * Remove old key entry, if existed
         */
        if ($oldPosition !== null && $oldPosition !== false)
            unset($this->_container->getContainerInstance()->objects[$oldPosition]);

        return $position;
    }

    public function setKey($key) {
        return parent::setKey($this->updateObjectPosition($key));
    }

    protected function getObjectData() {
        return (array)$this->_objectInstance;
    }

    /**
     * @param mixed $data
     */
    protected function setObjectData($data) {
        $this->_objectInstance->exchangeArray(array_merge((array)$this->_objectInstance, (array)$data));
    }
}