<?php

namespace ext\activedocument;

/**
 * Behavior
 *
 * @property \ext\activedocument\Document $owner
 */
class Behavior extends \CModelBehavior {

    /**
     * @return \ext\activedocument\Document
     */
    public function getOwner() {
        return parent::getOwner();
    }

    /**
     * Declares events and the corresponding event handler methods.
     * If you override this method, make sure you merge the parent result to the return value.
     * @return array events (array keys) and the corresponding event handler methods (array values).
     * @see \CBehavior::events
     */
    public function events() {
        return array_merge(parent::events(), array(
                    'onBeforeSave' => 'beforeSave',
                    'onAfterSave' => 'afterSave',
                    'onBeforeDelete' => 'beforeDelete',
                    'onAfterDelete' => 'afterDelete',
                    'onBeforeFind' => 'beforeFind',
                    'onAfterFind' => 'afterFind',
                ));
    }

    /**
     * Responds to {@link \ext\activedocument\Document::onBeforeSave} event.
     * Override this method if you want to handle the corresponding event of the {@link \CBehavior::owner owner}.
     * You may set {@link \ext\activedocument\Event::isValid} to be false to quit the saving process.
     * @param \ext\activedocument\Event $event event parameter
     */
    public function beforeSave(Event $event) {

    }

    /**
     * Responds to {@link \ext\activedocument\Document::onAfterSave} event.
     * Override this method if you want to handle the corresponding event of the {@link \CBehavior::owner owner}.
     * @param \CEvent $event event parameter
     */
    public function afterSave(\CEvent $event) {

    }

    /**
     * Responds to {@link \ext\activedocument\Document::onBeforeDelete} event.
     * Override this method if you want to handle the corresponding event of the {@link \CBehavior::owner owner}.
     * You may set {@link \ext\activedocument\Event::isValid} to be false to quit the deletion process.
     * @param \ext\activedocument\Event $event event parameter
     */
    public function beforeDelete(Event $event) {

    }

    /**
     * Responds to {@link \ext\activedocument\Document::onAfterDelete} event.
     * Override this method if you want to handle the corresponding event of the {@link \CBehavior::owner owner}.
     * @param \CEvent $event event parameter
     */
    public function afterDelete(\CEvent $event) {

    }

    /**
     * Responds to {@link \ext\activedocument\Document::onBeforeFind} event.
     * Override this method if you want to handle the corresponding event of the {@link \CBehavior::owner owner}.
     * @param \CEvent $event event parameter
     */
    public function beforeFind(\CEvent $event) {

    }

    /**
     * Responds to {@link \ext\activedocument\Document::onAfterFind} event.
     * Override this method if you want to handle the corresponding event of the {@link \CBehavior::owner owner}.
     * @param \CEvent $event event parameter
     */
    public function afterFind(\CEvent $event) {

    }

}