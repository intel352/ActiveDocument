<?php

namespace ext\activedocument\drivers\riak;

class Adapter extends \ext\activedocument\Adapter {
    public function __construct() {
        parent::__construct();
    }
    
    public function createContainer($name) {
        ;
    }
    
    public function loadContainer($name) {
        ;
    }
    
    public function loadContainers($containers=array()) {
        ;
    }
    
    public function saveContainer($name, $data) {
        ;
    }
    
    public function saveContainers($name, $containers=array()) {
        ;
    }
    
    public function deleteContainer($name) {
        ;
    }
    
    public function deleteContainers($containers=array()) {
        ;
    }
    
    public function createDataObject($container, $key, $data) {
        ;
    }
    
    public function loadDataObject($container, $key) {
        ;
    }
    
    public function saveDataObject($container, $key, $data) {
        ;
    }
    
    public function saveDataObjects($container, $dataObjects=array()) {
        ;
    }
    
    public function deleteDataObject($container, $key) {
        ;
    }
    
    public function deleteDataObjects($container, $keys=array()) {
        ;
    }
    
    public function lastInsertId() {
        ;
    }
    
}