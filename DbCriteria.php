<?php

namespace ext\activedocument;

use \CComponent;

class DbCriteria extends CComponent {

    public $container;
    public $inputs = array();
    public $phases = array();

    public function __construct($data=array()) {
        foreach ($data as $name => $value)
            $this->$name = $value;
    }
    
    public function addInput($container, $key=null, $data=null) {
        $this->inputs[] = array($container, $key, $data);
    }

    public function addMapPhase($function, $args=array()) {
        return $this->addPhase('map', $function, $args);
    }

    public function addReducePhase($function, $args=array()) {
        return $this->addPhase('reduce', $function, $args);
    }

    public function addPhase($phase, $function, $args=array()) {
        $this->phases[] = array($phase, $function, $args);
        return $this;
    }

    public function mergeWith($criteria) {
        if (is_array($criteria))
            $criteria = new self($criteria);

        $this->inputs = array_merge((array) $this->inputs, (array) $criteria->inputs);
        
        $this->phases = array_merge((array) $this->phases, (array) $criteria->phases);
    }

    public function toArray() {
        $result = array();
        foreach (array('inputs','phases') as $name)
            $result[$name] = $this->$name;
        return $result;
    }

}