<?php

namespace ext\activedocument;

use \CComponent;

class Criteria extends CComponent {

    public $container;
    public $inputs = array();
    public $phases = array();
    public $params = array();
	/**
	 * @var integer maximum number of records to be returned. If less than 0, it means no limit.
	 */
	public $limit=-1;
	/**
	 * @var integer zero-based offset from where the records are to be returned. If less than 0, it means starting from the beginning.
	 */
	public $offset=-1;

    public function __construct(array $data=array()) {
        if(!empty($data))
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

		if($criteria->limit>0)
			$this->limit=$criteria->limit;

		if($criteria->offset>=0)
			$this->offset=$criteria->offset;
    }

    public function toArray() {
        $result = array();
        foreach (array('inputs','phases') as $name)
            $result[$name] = $this->$name;
        return $result;
    }

}