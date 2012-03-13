<?php

namespace ext\activedocument\behaviors;

class Timestamp extends \ext\activedocument\Behavior {

    /**
     * @var mixed The name of the attribute to store the creation time.  Set to null to not
     * use a timstamp for the creation attribute.  Defaults to 'createdAt'
     */
    public $createAttribute = 'createdAt';

    /**
     * @var mixed The name of the attribute to store the modification time.  Set to null to not
     * use a timstamp for the update attribute.  Defaults to 'updatedAt'
     */
    public $updateAttribute = 'updatedAt';

    /**
     * @var bool Whether to set the update attribute to the creation timestamp upon creation.
     * Otherwise it will be left alone.  Defaults to false.
     */
    public $setUpdateOnCreate = false;

    /**
     * @var mixed The expression that will be used for generating the timestamp.
     * This can be a string representing a PHP expression (e.g. 'time()').
     * Defaults to null, meaning that it will fall back to using the current UNIX timestamp
     */
    public $timestampExpression;

    public function attach($owner) {
        parent::attach($owner);

        if (!$this->createAttribute && !$this->updateAttribute) {
            $msg = 'Model "{owner}" must define an count createAttribute and/or updateAttribute for it\'s "{self}" behavior!';
            throw new \ext\activedocument\Exception(strtr($msg, array(
                '{owner}' => get_class($this->getOwner()),
                '{self}' => get_class($this),
            )));
        }

        /**
         * Only create attributes on finder initialization (when metadata is generated)
         */
        if ($this->getOwner()->getScenario() === '') {
            $props = array();
            if ($this->createAttribute)
                $props[] = $this->createAttribute;
            if ($this->updateAttribute)
                $props[] = $this->updateAttribute;

            array_map(array($this, 'createTimestampAttribute'), $props);
        }
    }

    protected function createTimestampAttribute($propName) {
        if (!$this->getOwner()->metaData->hasProperty($propName)) {
            $this->getOwner()->metaData->addProperty($propName, array(
                'name' => $propName,
                'realType' => 'datetime',
            ));
            $this->getOwner()->metaData->properties->$propName->init();
        }
    }

    /**
     * Responds to {@link \ext\activedocument\Document::onBeforeSave} event.
     * Sets the values of the creation or modified attributes as configured
     *
     * @param \CEvent $event event parameter
     */
    public function beforeSave(\CEvent $event) {
        if ($this->getOwner()->getIsNewRecord() && ($this->createAttribute !== null))
            $this->getOwner()->{$this->createAttribute} = $this->getTimestamp();
        if ((!$this->getOwner()->getIsNewRecord() || $this->setUpdateOnCreate) && ($this->updateAttribute !== null))
            $this->getOwner()->{$this->updateAttribute} = $this->getTimestamp();
    }

    /**
     * Gets a timestamp
     *
     * @return mixed timestamp (eg unix timestamp or php function)
     */
    protected function getTimestamp() {
        if ($this->timestampExpression !== null)
            return Yii::app()->evaluateExpression('return ' . $this->timestampExpression . ';');
        return time();
    }

}