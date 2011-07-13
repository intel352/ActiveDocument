<?php

namespace ext\activedocument\drivers\riak;

class CommandBuilder extends \ext\activedocument\CommandBuilder {
    
    /**
     * Not sure I'm going to keep this code here...
     * @param bool $reset
     * @return \riiak\MapReduce
     */
    /*public function getMapReduce($reset=false) {
        return $this->getAdapter()->getMapReduce($reset);
    }
    
    public function addCountPhase() {
        $mr = $this->getMapReduce();
        $mr->map('function(){return [1]}');
        $mr->reduce('Riak.reduceSum');
        return $this;
    }*/
    
    public function createFindCommand($container, $criteria) {
        ;
    }

    public function createCountCommand($container, $criteria) {
        $this->ensureContainer($container);
        $criteria = new \ext\activedocument\Criteria($criteria);
        $criteria->container=$container;
        $criteria->addMapPhase('function(){return [1]}');
        $criteria->addReducePhase('Riak.reduceSum');
        // new Command ?;
    }

    public function createDeleteCommand($container, $criteria) {
        ;
    }

    public function createInsertCommand($container, $data) {
        ;
    }

    public function createUpdateCommand($container, $data, $criteria) {
        ;
    }

    public function createUpdateCounterCommand($container, $counters, $criteria) {
        ;
    }

}