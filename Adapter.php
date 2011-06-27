<?php

namespace ext\activedocument;
use CComponent;

abstract class Adapter extends CComponent {
    protected $_drivers;
    protected $_errorCode;
    protected $_errorInfo;
    protected $_runningTransaction=false;
    
    abstract public function createContainer($name);
    abstract public function loadContainer($name);
    abstract public function loadContainers($containers=array());
    abstract public function saveContainer($name, $data);
    abstract public function saveContainers($name, $containers=array());
    abstract public function deleteContainer($name);
    abstract public function deleteContainers($containers=array());
    abstract public function createDataObject($container, $key, $data);
    abstract public function loadDataObject($container, $key);
    abstract public function saveDataObject($container, $key, $data);
    abstract public function saveDataObjects($container, $dataObjects=array());
    abstract public function deleteDataObject($container, $key);
    abstract public function deleteDataObjects($container, $keys=array());
    abstract public function lastInsertId();
    
    public function __construct() {
    }
    
    public function beginTransaction() {
        $this->_runningTransaction=true;
    }
    
    public function commit() {
        $this->_runningTransaction=false;
    }
    
    public function getErrorCode() {
        return $this->_errorCode;
    }
    
    public function getErrorInfo() {
        return $this->_errorInfo;
    }
    
    public function exec() {
    }
    
    public function getAvailableDrivers() {
        if(is_array($this->_drivers))
            return $this->_drivers;
        $this->_drivers=scandir(realpath(__DIR__.'/drivers/'));
        $this->_drivers=array_filter($this->_drivers, function($f){if(preg_match('/^\.|\.\.$/', $f)) return false; return true;});
        return $this->_drivers;
    }
    
    public function getInTransaction() {
        return $this->_runningTransaction;
    }
    
    public function prepare() {
        ;
    }
    
    public function query() {
        ;
    }
    
    public function quote() {
        ;
    }
    
    public function rollBack() {
        ;
    }
}