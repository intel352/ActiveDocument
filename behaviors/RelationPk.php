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
        $owner->attachEventHandler('onGetMissingAttribute',array($this,'fetchRelationPk'));
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
        $owner->detachEventHandler('onGetMissingAttribute',array($this,'fetchRelationPk'));
        parent::detach($owner);
    }

    /**
     * @param \ext\activedocument\events\Magic $event
     * @return void
     */
    public function fetchRelationPk(Magic $event) {
        if(strcasecmp($event->name, 'encodedpk')===0 || strlen($event->name)<=2
            || substr_compare($event->name, 'pk', -2, 2, true)!==0 || $this->relations === array())
            return;
        elseif(($name=substr($event->name, 0, -2)) && in_array($name, $this->relations)) {
            $event->result = $this->getOwner()->getRelatedKeys($name);
            $event->handled = true;
        }
    }

}