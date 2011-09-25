<?php

namespace ext\activedocument;

/**
 * DocumentBehavior
 *
 * @version $Version$
 * @author $Author$
 */
class DocumentBehavior extends \CModelBehavior {

    /**
     * Declares events and the corresponding event handler methods.
     * If you override this method, make sure you merge the parent result to the return value.
     * @return array events (array keys) and the corresponding event handler methods (array values).
     * @see CBehavior::events
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
     * Responds to {@link CActiveRecord::onBeforeSave} event.
     * Overrides this method if you want to handle the corresponding event of the {@link CBehavior::owner owner}.
     * You may set {@link CModelEvent::isValid} to be false to quit the saving process.
     * @param \ext\activedocument\Event $event event parameter
     */
    public function beforeSave(Event $event) {

    }

    /**
     * Responds to {@link CActiveRecord::onAfterSave} event.
     * Overrides this method if you want to handle the corresponding event of the {@link CBehavior::owner owner}.
     * @param \ext\activedocument\Event $event event parameter
     */
    public function afterSave(Event $event) {

    }

    /**
     * Responds to {@link CActiveRecord::onBeforeDelete} event.
     * Overrides this method if you want to handle the corresponding event of the {@link CBehavior::owner owner}.
     * You may set {@link CModelEvent::isValid} to be false to quit the deletion process.
     * @param CEvent $event event parameter
     */
    public function beforeDelete(\CEvent $event) {

    }

    /**
     * Responds to {@link CActiveRecord::onAfterDelete} event.
     * Overrides this method if you want to handle the corresponding event of the {@link CBehavior::owner owner}.
     * @param CEvent $event event parameter
     */
    public function afterDelete(\CEvent $event) {

    }

    /**
     * Responds to {@link CActiveRecord::onBeforeFind} event.
     * Overrides this method if you want to handle the corresponding event of the {@link CBehavior::owner owner}.
     * @param CEvent $event event parameter
     * @since 1.0.9
     */
    public function beforeFind(\CEvent $event) {

    }

    /**
     * Responds to {@link CActiveRecord::onAfterFind} event.
     * Overrides this method if you want to handle the corresponding event of the {@link CBehavior::owner owner}.
     * @param CEvent $event event parameter
     */
    public function afterFind(\CEvent $event) {

    }

}