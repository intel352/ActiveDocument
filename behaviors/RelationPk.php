<?php

namespace ext\activedocument\behaviors;

use \ext\activedocument\Document,
\ext\activedocument\events\Magic,
\ext\activedocument\BaseRelation,
\ext\activedocument\Relation;

/**
 * RelationPk answers a model call to non-existing {relation}Pk attribute, and returns the pk(s) of the related model(s)
 * Acts as a proxy to Document::getRelatedKeys
 */
class RelationPk extends \ext\activedocument\Behavior {

    public $relations = array();

    /**
     * @param \ext\activedocument\Document $owner
     */
    public function attach($owner) {
        parent::attach($owner);
        $owner->attachEventHandler('onGetMissingAttribute',array($this,'handleGet'));
        $owner->attachEventHandler('onIssetMissingAttribute',array($this,'handleIsset'));
        $this->relations = array_keys(array_filter($owner->getMetaData()->getRelations(true),
            function(BaseRelation $relation){
                return ($relation instanceof Relation);
            }
        ));
    }

    /**
     * @param \ext\activedocument\Document $owner
     */
    public function detach($owner) {
        $owner->detachEventHandler('onGetMissingAttribute',array($this,'handleGet'));
        $owner->detachEventHandler('onIssetMissingAttribute',array($this,'handleIsset'));
        parent::detach($owner);
    }

    /**
     * @param \ext\activedocument\events\Magic $event
     * @return array|bool|mixed|null False if not relevant, otherwise mixed from getRelatedKeys()
     */
    protected function fetchKeys(Magic $event) {
        #\Yii::trace('RelationPk handler called for '. get_class($this->owner) .'.'. $event->name, 'ext.activedocument.behaviors.RelationPk');
        if(strcasecmp($event->name, 'encodedpk')===0 || strlen($event->name)<=2
            || substr_compare($event->name, 'pk', -2, 2, true)!==0 || $this->relations === array())
            return false;
        elseif(($name=substr($event->name, 0, -2)) && in_array($name, $this->relations)) {
            return $this->getOwner()->getRelatedKeys($name);
        }
        return false;
    }

    /**
     * @param \ext\activedocument\events\Magic $event
     */
    public function handleGet(Magic $event) {
        if (($keys=$this->fetchKeys($event))!==false) {
            $event->result = $keys;
            $event->handled = true;
            #\Yii::trace('Handler(get) for '. get_class($this->owner) .'.'. $event->name
            #    .' is returning value '. \CVarDumper::dumpAsString($event->result), 'ext.activedocument.behaviors.RelationPk');
        }
    }

    /**
     * @param \ext\activedocument\events\Magic $event
     */
    public function handleIsset(Magic $event) {
        if (($keys=$this->fetchKeys($event))!==false) {
            $event->result = ($keys !== null && $keys !== array());
            $event->handled = true;
            #\Yii::trace('Handler(isset) for '. get_class($this->owner) .'.'. $event->name
            #    .' is returning value '. \CVarDumper::dumpAsString($event->result), 'ext.activedocument.behaviors.RelationPk');
        }
    }

}