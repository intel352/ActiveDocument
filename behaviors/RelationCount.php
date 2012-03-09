<?php

namespace ext\activedocument\behaviors;

/**
 * This behavior can be used to maintain a count of objects per HAS_MANY/MANY_MANY relation
 */
class RelationCount extends \ext\activedocument\Behavior {

    /**
     * @var array Relations you want to limit this behavior to. Default applies to all
     */
    public $relations = array();

    /**
     * @var string The string to prepend to relation column name, where the count is stored
     */
    public $countPrefix = '';

    /**
     * @var string The string to append to relation column name, where the count is stored
     */
    public $countSuffix = '_count';

    protected $_countVars = array();

    public function attach($owner) {
        parent::attach($owner);

        if ($this->countPrefix === '' && $this->countSuffix === '') {
            $msg = 'Model "{owner}" must define a count column prefix or suffix for it\'s "{self}" behavior!';
            throw new \ext\activedocument\Exception(strtr($msg, array(
                '{owner}' => get_class($this->getOwner()),
                '{self}' => get_class($this),
            )));
        }

        $relations = $this->getOwner()->getMetaData()->getRelations(true);
        if ($this->relations !== array())
            $relations = array_intersect_key($relations, array_flip($this->relations));
        $relations = array_filter($relations, function($r) {
            return $r instanceof \ext\activedocument\HasManyRelation;
        });
        array_map(array($this, 'createCountAttribute'), array_keys($relations));
    }

    protected function createCountAttribute($relationName) {
        $propName                        = $this->countPrefix . $relationName . $this->countSuffix;
        $this->_countVars[$relationName] = $propName;

        /**
         * Only create attributes on finder initialization (when metadata is generated)
         */
        if ($this->getOwner()->getScenario() === '') {
            $this->getOwner()->metaData->addProperty($propName, array(
                'name' => $propName,
                'realType' => 'integer',
            ));
            $this->getOwner()->metaData->properties->$propName->init();
        }
    }

    /**
     * Responds to {@link \ext\activedocument\Document::onBeforeSave} event.
     *
     * @param \CEvent $event event parameter
     */
    public function beforeSave(\CEvent $event) {
        array_walk($this->_countVars, array($this, 'countRelated'));
    }

    /**
     * Responds to {@link \ext\activedocument\Document::onBeforeSave} event.
     *
     * @param \CEvent $event event parameter
     */
    public function afterFind(\CEvent $event) {
        array_walk($this->_countVars, array($this, 'countRelated'));
    }

    protected function countRelated($countVar, $relationName) {
        $owner = $this->getOwner();
        if ($owner->metaData->hasRelation($relationName)) {
            if (!isset($owner->getObject()->data[$relationName]))
                $owner->$countVar = 0;
            else
                $owner->$countVar = count($owner->getObject()->data[$relationName]);
        }
    }

}