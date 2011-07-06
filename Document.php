<?php

namespace ext\activedocument;

use \Yii, \CEvent, \CModelEvent, \CComponent;

abstract class Document extends CComponent {

    /**
     * @var \ext\activedocument\Model
     */
    protected $_model;
    /**
     *
     * @var \ext\activedocument\Object
     */
    protected $_object;
    private $_new = false;
    private $_attributes = array();
    private $_pk;

    public function __sleep() {
        return array_keys((array) $this);
    }

    public function __get($name) {
        if (isset($this->_attributes[$name]))
            return $this->_attributes[$name];
        /* else if (isset($this->getMetaData()->columns[$name]))
          return null;
          else if (isset($this->_related[$name]))
          return $this->_related[$name];
          else if (isset($this->getMetaData()->relations[$name]))
          return $this->getRelated($name); */
        else
            return parent::__get($name);
    }

    public function __set($name, $value) {
        if ($this->setAttribute($name, $value) === false) {
            /* if (isset($this->getMetaData()->relations[$name]))
              $this->_related[$name] = $value;
              else */
            parent::__set($name, $value);
        }
    }

    public function __isset($name) {
        if (isset($this->_attributes[$name]))
            return true;
        /* else if (isset($this->getMetaData()->columns[$name]))
          return false;
          else if (isset($this->_related[$name]))
          return true;
          else if (isset($this->getMetaData()->relations[$name]))
          return $this->getRelated($name) !== null; */
        else
            return parent::__isset($name);
    }

    public function __unset($name) {
        /* if (isset($this->getMetaData()->columns[$name]))
          unset($this->_attributes[$name]);
          else if (isset($this->getMetaData()->relations[$name]))
          unset($this->_related[$name]);
          else */
        parent::__unset($name);
    }

    public function __call($name, $parameters) {
        /* if (isset($this->getMetaData()->relations[$name])) {
          if (empty($parameters))
          return $this->getRelated($name, false);
          else
          return $this->getRelated($name, false, $parameters[0]);
          }

          $scopes = $this->scopes();
          if (isset($scopes[$name])) {
          $this->getDbCriteria()->mergeWith($scopes[$name]);
          return $this;
          } */

        return parent::__call($name, $parameters);
    }

    public function hasAttribute($name) {
        #return isset($this->getMetaData()->columns[$name]);
    }

    public function getAttribute($name) {
        if (property_exists($this, $name))
            return $this->$name;
        else if (isset($this->_attributes[$name]))
            return $this->_attributes[$name];
    }

    public function setAttribute($name, $value) {
        if (property_exists($this, $name))
            $this->$name = $value;
        /* else if (isset($this->getMetaData()->columns[$name]))
          $this->_attributes[$name] = $value; */
        else
            return false;
        return true;
    }

    /**
     * @return \ext\activedocument\Model
     */
    public function getModel() {
        return $this->_model;
    }

    /**
     * @return \ext\activedocument\Object
     */
    public function getObject() {
        return $this->_object;
    }

    /**
     * @return \ext\activedocument\MetaData
     */
    public function getMetaData() {
        return $this->_model->getMetaData();
    }

    public function getAttributes($names=true) {
        $attributes = $this->_attributes;
        foreach ($this->getMetaData()->columns as $name => $column) {
            if (property_exists($this, $name))
                $attributes[$name] = $this->$name;
            else if ($names === true && !isset($attributes[$name]))
                $attributes[$name] = null;
        }
        if (is_array($names)) {
            $attrs = array();
            foreach ($names as $name) {
                if (property_exists($this, $name))
                    $attrs[$name] = $this->$name;
                else
                    $attrs[$name] = isset($attributes[$name]) ? $attributes[$name] : null;
            }
            return $attrs;
        }
        else
            return $attributes;
    }

    public function getIsNewRecord() {
        return $this->_new;
    }

    public function setIsNewRecord($value) {
        $this->_new = $value;
    }

    public function refresh() {
        Yii::trace(get_class($this) . '.refresh()', 'ext.activedocument.' . get_class($this));
        if (!$this->getIsNewRecord() && ($document = $this->findByPk($this->getPrimaryKey())) !== null) {
            $this->_attributes = array();
            $this->_related = array();
            foreach ($this->getMetaData()->columns as $name => $column) {
                if (property_exists($this, $name))
                    $this->$name = $document->$name;
                else
                    $this->_attributes[$name] = $document->$name;
            }
            return true;
        }
        else
            return false;
    }

    public function equals(Document $document) {
        return $this->containerName() === $document->containerName() && $this->getPrimaryKey() === $document->getPrimaryKey();
    }

    public function containerName() {
        return $this->_model->getContainerName();
    }

    public function getPrimaryKey() {
        return $this->_pk;
    }

    public function setPrimaryKey($value) {
        $this->_pk = $value;
    }

}