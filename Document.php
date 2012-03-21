<?php

namespace ext\activedocument;

use \Yii,
\CModel,
\CEvent,
\ext\activedocument\events\Event;

Yii::import('ext.activedocument.Relation', true);

/**
 * Document
 *
 * @todo    Relations are almost in place, need mechanism for determining how keys will be managed
 *
 * @version $Version: 1.0.dev.56 $
 * @author  $Author: intel352 $
 *
 * @property \ext\activedocument\Document $owner
 * @property \ext\activedocument\Object $object
 * @property bool $isNewRecord
 * @property \ext\activedocument\Criteria $criteria
 * @property mixed $primaryKey
 * @property-read bool $isModified
 * @property-read string $encodedPk
 * @property-read \ext\activedocument\Connection $connection
 * @property-read \ext\activedocument\Adapter $adapter
 * @property-read string $containerName
 * @property-read \ext\activedocument\Container $container
 * @property-read \ext\activedocument\MetaData $metaData
 */
abstract class Document extends CModel {

    const BELONGS_TO = '\ext\activedocument\BelongsToRelation';
    const HAS_ONE    = '\ext\activedocument\HasOneRelation';
    const HAS_MANY   = '\ext\activedocument\HasManyRelation';
    const MANY_MANY  = '\ext\activedocument\ManyManyRelation';
    const STAT       = '\ext\activedocument\StatRelation';

    /**
     * Override with component connection name, if not 'conn'
     * Is accessed using Late Static Binding (i.e. - static::$connName)
     *
     * @var string
     */
    public static $connName = 'conn';

    /**
     * Array of connections
     *
     * @var \ext\activedocument\Connection[]
     */
    public static $connections = array();
    private static $_models = array();
    protected $_related = array();

    /**
     * @var \ext\activedocument\MetaData
     */
    private $_md;

    /**
     * @var \ext\activedocument\Criteria
     */
    private $_c;

    /**
     * @var \ext\activedocument\Container
     */
    protected $_container;

    /**
     * @var \ext\activedocument\Object
     */
    protected $_object;
    protected $_new = false;
    protected $_attributes = array();
    protected $_pk;

    /**
     * The columns that have been modified in current object.
     * Tracking modified columns allows us to only update modified columns.
     *
     * @var        array
     */
    protected $_modifiedAttributes = array();

    /**
     * @var \ext\activedocument\Document
     */
    protected $_owner;

    /**
     * @static
     *
     * @param string $className optional
     *
     * @return \ext\activedocument\Document
     */
    public static function model($className = null) {
        if ($className === null)
            $className = get_called_class();
        if (isset(self::$_models[$className]))
            return self::$_models[$className];
        else {
            /**
             * @var \ext\activedocument\Document $document
             */
            $document      = self::$_models[$className] = new $className(null);
            $document->_md = new MetaData($document);
            $document->attachBehaviors($document->behaviors());
            return $document;
        }
    }

    public function __construct($scenario = 'insert') {
        if ($scenario === null)
            return;

        $this->setScenario($scenario);
        $this->setIsNewRecord(true);
        $this->newObject();

        $this->init();

        $this->attachBehaviors($this->behaviors());
        $this->afterConstruct();
    }

    public function init() {
        /**
         * Replacing certain validators
         */
        \CValidator::$builtInValidators = array_merge(\CValidator::$builtInValidators, array(
            'unique' => '\ext\activedocument\validators\Unique',
        ));

        $this->resetModified();
        return $this;
    }

    /**
     * The owner/parent of this Document (if any)
     *
     * @return \ext\activedocument\Document
     */
    public function getOwner() {
        return $this->_owner;
    }

    /**
     * The owner/parent of this Document (if any)
     *
     * @param Document|null $owner
     *
     * @return Document
     */
    public function setOwner(Document $owner = null) {
        $this->_owner = $owner;
        return $this;
    }

    /**
     * @return Document
     */
    protected function newObject() {
        $this->setObject($this->loadObject());
        return $this;
    }

    /**
     * @param null $key
     *
     * @return Object
     */
    protected function loadObject($key = null) {
        return $this->getContainer()->getObject($key, null, $this->getIsNewRecord());
    }

    /**
     * @return Object
     */
    public function getObject() {
        return $this->_object;
    }

    /**
     * @param Object $object
     *
     * @return Document
     */
    public function setObject(Object $object) {
        $this->_object = $object;
        if (!$this->getIsNewRecord()) {
            $this->setAttributes($this->_object->data, false);
            $this->ensurePk();
        } else {
            $this->_object->data = $this->getMetaData()->attributeDefaults;
            $this->setAttributes($this->_object->data, false);
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsNewRecord() {
        return $this->_new;
    }

    /**
     * @param bool $value
     *
     * @return Document
     */
    public function setIsNewRecord($value) {
        $this->_new = $value;
        return $this;
    }

    /**
     * Returns whether the object has been modified.
     *
     * @return     boolean True if the object has been modified.
     */
    public function getIsModified() {
        return !empty($this->_modifiedAttributes);
    }

    /**
     * Has specified attribute been modified?
     *
     * @param      string $attr attribute fully qualified name
     *
     * @return     boolean True if $attr has been modified.
     */
    public function isAttributeModified($attr) {
        return in_array($attr, $this->_modifiedAttributes);
    }

    /**
     * Get the attributes that have been modified in this object.
     *
     * @return     array A unique list of the modified attribute names for this object.
     */
    public function getModifiedAttributes() {
        return array_unique($this->_modifiedAttributes);
    }

    /**
     * Sets the modified state for the object to be false.
     *
     * @param string $attr If supplied, only the specified attribute is reset.
     *
     * @return Document
     */
    public function resetModified($attr = null) {
        if ($attr !== null) {
            while (($offset = array_search($attr, $this->_modifiedAttributes)) !== false) {
                array_splice($this->_modifiedAttributes, $offset, 1);
            }
        } else {
            $this->_modifiedAttributes = array();
        }
        return $this;
    }

    /**
     * @param bool $createIfNull
     * @return Criteria
     */
    public function getCriteria($createIfNull = true) {
        if ($this->_c === null) {
            if (($c = $this->defaultScope()) !== array() || $createIfNull)
                $this->_c = new Criteria($c);
        }
        return $this->_c;
    }

    /**
     * @param Criteria $criteria
     * @return Document
     */
    public function setCriteria($criteria) {
        $this->_c = $criteria;
        return $this;
    }

    /**
     * @return array
     */
    public function defaultScope() {
        return array();
    }

    /**
     * @return Document
     */
    public function resetScope() {
        $this->_c = new Criteria();
        return $this;
    }

    /**
     * @return array
     */
    public function __sleep() {
        $this->_md = null;
        return array_keys((array)$this);
    }

    public function &__get($name) {
        if (isset($this->_attributes[$name]))
            return $this->_attributes[$name];
        else if (isset($this->getMetaData()->attributes->$name)) {
            $return = null;
            return $return;
        } else if (isset($this->_related[$name]))
            return $this->_related[$name];
        else if (isset($this->getMetaData()->relations->$name))
            return $this->getRelated($name);
        else {
            if ((strncasecmp($name, 'on', 2) !== 0 || !$this->hasEvent($name)) && !method_exists($this, 'get' . $name) &&
                $this->hasEvent('onGetMissingAttribute') && $this->getEventHandlers('onGetMissingAttribute')
                ->getCount() > 0
            ) {
                $this->onGetMissingAttribute($event = new events\Magic($this, events\Magic::GET, $name));
                if ($event->handled)
                    return $event->result;
            }

            $return = parent::__get($name);
            return $return;
        }
    }

    public function __set($name, $value) {
        if ($this->setAttribute($name, $value) === false) {
            if (isset($this->getMetaData()->relations->$name)) {
                $this->_related[$name]       = $value;
                $this->_modifiedAttributes[] = $name;
            } else {
                if ((strncasecmp($name, 'on', 2) !== 0 || !$this->hasEvent($name)) && !method_exists($this, 'set' . $name) &&
                    $this->hasEvent('onSetMissingAttribute') && $this->getEventHandlers('onSetMissingAttribute')
                    ->getCount() > 0
                ) {
                    $this->onSetMissingAttribute($event = new events\Magic($this, events\Magic::SET, $name, $value));
                    if ($event->handled)
                        return $event->result;
                }
                parent::__set($name, $value);
            }
        }
    }

    public function __isset($name) {
        if (isset($this->_attributes[$name]))
            return true;
        else if (isset($this->getMetaData()->attributes->$name))
            return false;
        else if (isset($this->_related[$name]))
            return true;
        else if (isset($this->getMetaData()->relations->$name))
            return $this->getRelated($name) !== null;
        else {
            if ((strncasecmp($name, 'on', 2) !== 0 || !$this->hasEvent($name)) && !method_exists($this, 'get' . $name) &&
                $this->hasEvent('onIssetMissingAttribute') && $this->getEventHandlers('onIssetMissingAttribute')
                ->getCount() > 0
            ) {
                $this->onIssetMissingAttribute($event = new events\Magic($this, events\Magic::SETIS, $name));
                if ($event->handled)
                    return $event->result;
            }
            return parent::__isset($name);
        }
    }

    public function __unset($name) {
        if (isset($this->getMetaData()->attributes->$name)) {
            unset($this->_attributes[$name]);
            $this->_modifiedAttributes[] = $name;
        } else if (isset($this->getMetaData()->relations->$name)) {
            unset($this->_related[$name]);
            $this->_modifiedAttributes[] = $name;
        } else {
            if ((strncasecmp($name, 'on', 2) !== 0 || !$this->hasEvent($name)) && !method_exists($this, 'set' . $name) &&
                $this->hasEvent('onUnsetMissingAttribute') && $this->getEventHandlers('onUnsetMissingAttribute')
                ->getCount() > 0
            ) {
                $this->onUnsetMissingAttribute($event = new events\Magic($this, events\Magic::SETUN, $name));
                if ($event->handled)
                    return $event->result;
            }
            parent::__unset($name);
        }
    }

    public function __call($name, $parameters) {
        if (isset($this->getMetaData()->relations->$name)) {
            if (empty($parameters))
                return $this->getRelated($name, false);
            else
                return $this->getRelated($name, false, $parameters[0]);
        }

        $scopes = $this->scopes();
        if (isset($scopes[$name])) {
            $this->getCriteria()->mergeWith($scopes[$name]);
            return $this;
        }

        if ((strncasecmp($name, 'on', 2) !== 0 || !$this->hasEvent($name)) &&
            $this->hasEvent('onCallMissingMethod') && $this->getEventHandlers('onCallMissingMethod')->getCount() > 0
        ) {
            $this->onCallMissingMethod($event = new events\Magic($this, events\Magic::CALL, $name, $parameters));
            if ($event->handled)
                return $event->result;
        }

        return parent::__call($name, $parameters);
    }

    public function onGetMissingAttribute(events\Magic $event) {
        $this->raiseEvent('onGetMissingAttribute', $event);
    }

    public function onSetMissingAttribute(events\Magic $event) {
        $this->raiseEvent('onSetMissingAttribute', $event);
    }

    public function onIssetMissingAttribute(events\Magic $event) {
        $this->raiseEvent('onIssetMissingAttribute', $event);
    }

    public function onUnsetMissingAttribute(events\Magic $event) {
        $this->raiseEvent('onUnsetMissingAttribute', $event);
    }

    public function onCallMissingMethod(events\Magic $event) {
        $this->raiseEvent('onCallMissingMethod', $event);
    }

    /**
     * Returns arrays of indexed keys, only applicable to HasMany or ManyMany relations
     *
     * @param string       $name  The relation name (see {@link relations})
     * @param string|array $index Name of the index, or array of index names
     *
     * @return array[]array[]string
     */
    public function getRelatedKeysByIndexName($name, $index) {
        $index  = array_combine((array)$index, (array)$index);
        $object = $this->getObject();
        return array_map(function($index) use($name, $object) {
            return isset($object->data[$name . '_' . $index]) ? $object->data[$name . '_' . $index] : array();
        }, $index);
    }

    /**
     * Return array of keys, filtered by specified index values, only applicable to HasMany or ManyMany relations
     *
     * @param string $name    The relation name (see {@link relations})
     * @param array  $indexes Array of 'indexName'=>'searchValue' to search by
     * @param array  $keys    Array of keys to additionally filter by
     *
     * @return array
     */
    public function getRelatedKeysByIndex($name, array $indexes, array $keys = array()) {
        $pks    = array();
        $object = $this->getObject();
        $class  = get_class($this);
        array_walk($indexes, function($indexValue, $index) use($name, $object, &$pks, $class) {
            $indexName = $name . '_' . $index;
            if ($indexValue === '' || $indexValue === null)
                return;
            $indexValue = $class::stringify($indexValue);
            if (!isset($object->data[$indexName][$indexValue]) || $object->data[$indexName][$indexValue] === array())
                return;
            $pks[] = $object->data[$indexName][$indexValue];
        });
        if ($pks === array())
            return array();
        if ($keys !== array())
            array_push($pks, $keys);
        return count($pks) > 1 ? call_user_func_array('array_intersect', $pks) : array_shift($pks);
    }

    public function getRelatedKeys($name) {
        $relation = $this->getMetaData()->relations[$name];
        if (!isset($this->getObject()->data[$name]) || !($relation instanceof Relation))
            return null;

        if ($relation instanceof HasManyRelation) {
            $pks = $this->getObject()->data[$name];
            if ($relation->nested === true)
                $pks = array_keys($pks);
            return $pks;
        } else {
            if ($relation->nested === true) {
                if ($obj = $this->getRelated($name))
                    return $obj->getEncodedPk();
            } else
                return self::stringify($this->getObject()->data[$name]);
        }
        return null;
    }

    /**
     * Returns related records filtered by indexed values, only applicable to HasMany or ManyMany relations
     *
     * @param string           $name        The relation name (see {@link relations})
     * @param array            $indexes     Array of 'indexName'=>'searchValue' to search by
     * @param bool             $refresh     Whether to force reload objects from db
     * @param array            $params      Additional parameters to customize query
     * @param array            $keys        Array of keys to additionally filter by
     * @param array|Criteria   $criteria    Optional criteria to further customize relation query
     *
     * @return array
     */
    public function &getRelatedByIndex($name, array $indexes, $refresh = false, array $params = array(), array $keys = array(), $criteria = array()) {
        $pks = $this->getRelatedKeysByIndex($name, $indexes, $keys);
        if ($pks === array())
            return $pks;
        Yii::trace('Requesting related records for relation ' . get_class($this) . '.' . $name . ', filtered by ' . \CVarDumper::dumpAsString($indexes), 'ext.activedocument.document.getRelatedByIndex');
        $related = $this->getRelated($name, $refresh, $params, $pks, $criteria);
        return $related;
    }

    /**
     * Returns the related record(s).
     * This method will return the related record(s) of the current record.
     * If the relation is HAS_ONE or BELONGS_TO, it will return a single object
     * or null if the object does not exist.
     * If the relation is HAS_MANY or MANY_MANY, it will return an array of objects
     * or an empty array.
     *
     * @param string           $name        the relation name (see {@link relations})
     * @param boolean          $refresh     Optional whether to reload the related objects from database. Defaults to false.
     * @param array            $params      Optional additional parameters that customize the query conditions as specified in the relation declaration.
     * @param array            $keys        Optional Array of encoded primary keys to filter by on HasMany or ManyMany relations
     * @param array|Criteria   $criteria    Optional criteria to further customize relation query
     *
     * @return mixed the related object(s).
     * @throws Exception if the relation is not specified in {@link relations}.
     */
    public function &getRelated($name, $refresh = false, array $params = array(), array $keys = array(), $criteria = array()) {
        if ($keys !== array())
            $keys = array_map(array('self', 'stringify'), $keys);
        if (!$refresh && $params === array() && $criteria === array() && (isset($this->_related[$name]) || array_key_exists($name, $this->_related))) {
            if ($keys !== array() && is_array($this->_related[$name])) {
                $related = array_filter($this->_related[$name], function(Document $document) use($keys) {
                    return in_array($document->getEncodedPk(), $keys);
                });
                return $related;
            } else {
                return $this->_related[$name];
            }
        }

        $md = $this->getMetaData();
        if (!isset($md->relations[$name]))
            throw new Exception(Yii::t('yii', '{class} does not have relation "{name}".', array('{class}' => get_class($this), '{name}' => $name)));

        Yii::trace('lazy loading ' . get_class($this) . '.' . $name, 'ext.activedocument.document.getRelated');
        /**
         * @var BaseRelation $relation
         */
        $relation = $md->relations[$name];

        if ($this->getIsNewRecord() && !$refresh && ($relation instanceof HasOneRelation || $relation instanceof HasManyRelation)) {
            $_r = $relation instanceof HasOneRelation ? null : array();
            return $_r;
        }

        if ($params !== array() || $criteria !== array() || ($relation instanceof HasManyRelation && $keys !== array())) { // dynamic query
            $exists = $this->hasRelated($name);
            if ($exists)
                $save = $this->_related[$name];
        }
        unset($this->_related[$name]);

        $data = $this->getObject()->data;
        if (isset($data[$name])) {
            $finder    = Document::model($relation->className);
            $_criteria = new Criteria();
            /**
             * @todo The solution below for merging relation settings into standard criteria, could use more elegance
             */
            $relCriteria = array();
            array_map(function($key) use(&$relCriteria, $relation) {
                if (isset($relation->$key))
                    $relCriteria[$key] = $relation->$key;
            }, array_keys($_criteria->toArray()));

            if ($relCriteria !== array())
                $_criteria->mergeWith($relCriteria);

            if ($criteria !== array())
                $_criteria->mergeWith($criteria);

            if ($relation instanceof Relation && $relation->nested === true && $params === array() && $criteria === array()) {
                Yii::trace('Loading nested ' . get_class($this) . '.' . $name, 'ext.activedocument.document.getRelated');
                if ($relation instanceof HasManyRelation) {
                    if ($keys !== array())
                        $data[$name] = array_intersect_key($data[$name], array_flip($keys));
                    array_walk($data[$name], function(&$rel, $key) use($relation, $finder) {
                        $rel = $finder->getContainer()->getObject($key, $rel, true);
                    });
                    $this->_related[$name] = $finder->populateDocuments($data[$name]);
                } else
                    /**
                     * @todo Need to verify that this solution works consistently...
                     */
                    $this->_related[$name] = $finder->populateDocument(
                        $finder->getContainer()->getObject(null, $data[$name], true)
                    );
            } else {
                if ($relation instanceof HasManyRelation) {
                    $pks = $data[$name];
                    if ($relation->nested === true)
                        $pks = array_keys($pks);
                    if ($keys !== array())
                        $pks = array_intersect($pks, $keys);
                    $this->_related[$name] = $finder->findAllByPk($pks, $_criteria, $params);
                } elseif ($relation instanceof StatRelation) {
                    $this->_related[$name] = $finder->count($_criteria, $params);
                } else {
                    if ($relation->nested === true) {
                        /**
                         * @todo Need to verify that this solution works consistently...
                         */
                        $obj = $finder->populateDocument(
                            $finder->getContainer()->getObject(null, $data[$name], true)
                        );
                        if ($obj !== null)
                            $this->_related[$name] = $finder->findByPk($obj->getEncodedPk(), $_criteria, $params);
                    } else {
                        $this->_related[$name] = $finder->findByPk($data[$name], $_criteria, $params);
                    }
                }
            }
        }

        if (!isset($this->_related[$name])) {
            if ($relation instanceof HasManyRelation)
                $this->_related[$name] = array();
            else if ($relation instanceof StatRelation)
                $this->_related[$name] = $relation->defaultValue;
            else
                $this->_related[$name] = null;
        }

        if ($params !== array() || $criteria !== array() || ($relation instanceof HasManyRelation && $keys !== array())) {
            $results = $this->_related[$name];
            if ($exists)
                $this->_related[$name] = $save;
            else
                unset($this->_related[$name]);
            return $results;
        }
        else
            return $this->_related[$name];
    }

    /**
     * Returns a value indicating whether the named related object(s) has been loaded.
     *
     * @param string $name the relation name
     *
     * @return boolean a value indicating whether the named related object(s) has been loaded.
     */
    public function hasRelated($name) {
        return isset($this->_related[$name]) || array_key_exists($name, $this->_related);
    }

    /**
     * Used to populate related objects. This method adds a related object to this record.
     *
     * @param string         $name        attribute name
     * @param array|Document $document    the related document[s]
     * @param string         $foreignName the name of this relationship in the related document. If not empty, relation will be set both ways
     */
    public function addRelated($name, $document, $foreignName = null) {
        if ($this->getMetaData()->relations->$name instanceof HasManyRelation && !is_array($document)) {
            if (!isset($this->_related[$name]) || !is_array($this->_related[$name]))
                $this->_related[$name] = array();
            $this->_related[$name][] = $document;
        } else
            $this->_related[$name] = $document;
        $this->_modifiedAttributes[] = $name;

        if (!empty($foreignName)) {
            if (!is_array($document))
                $document = array($document);
            array_walk($document, function(Document $document, $index, array $relation) {
                list($relationName, $relatedDocument) = $relation;
                $document->addRelated($relationName, $relatedDocument);
            }, array($foreignName, $this));
        }
    }

    public function hasAttribute($name) {
        return isset($this->getMetaData()->attributes->$name);
    }

    public function getAttribute($name) {
        if (property_exists($this, $name))
            return $this->$name;
        else if (isset($this->_attributes[$name]))
            return $this->_attributes[$name];
    }

    public function setAttribute($name, $value) {
        if (property_exists($this, $name))
            $this->$name = $value;
        else if (isset($this->getMetaData()->attributes->$name)) {
            /**
             * Typecast the value
             */
            if ($value !== null && $value !== '' && ($type = $this->getMetaData()->attributes->$name->type))
                settype($value, $type);
            $this->_attributes[$name] = $value;
        } else
            return false;
        $this->_modifiedAttributes[] = $name;
        return true;
    }

    public function getAttributes($names = true) {
        $attributes = $this->_attributes;
        foreach ($this->attributeNames() as $name) {
            if (property_exists($this, $name))
                $attributes[$name] = $this->$name;
            else if ($names === true && !isset($attributes[$name]))
                $attributes[$name] = null;
        }
        if (is_array($names)) {
            $attrs = array();
            foreach ($names as $name) {
                if (property_exists($this, $name))
                    $attrs[$name] = $this->$name;
                else
                    $attrs[$name] = isset($attributes[$name]) ? $attributes[$name] : null;
            }
            return $attrs;
        }
        else
            return $attributes;
    }

    /**
     * Sets the attribute values in a massive way.
     *
     * @param array   $values   attribute values (name=>value) to be set.
     * @param boolean $safeOnly whether the assignments should only be done to the safe attributes.
     *                          A safe attribute is one that is associated with a validation rule in the current {@link scenario}.
     *
     * @see getSafeAttributeNames
     * @see attributeNames
     *
     * @return mixed
     */
    public function setAttributes($values, $safeOnly = true) {
        if (!is_array($values))
            return;
        $this->_modifiedAttributes += $safeOnly ? $this->getSafeAttributeNames() : $this->attributeNames();
        return parent::setAttributes($values, $safeOnly);
    }

    public function refresh() {
        Yii::trace(get_class($this) . '.refresh()', 'ext.activedocument.Document');
        if (!$this->getIsNewRecord() && $this->getObject()->reload()) {
            $this->_related = array();
            $object         = $this->getObject();
            foreach ($this->getMetaData()->attributes as $name => $attr) {
                if (property_exists($this, $name) && isset($object->data[$name]))
                    $this->$name = $object->data[$name];
            }
            $this->resetModified();
            return true;
        }
        else
            return false;
    }

    public function equals(Document $document) {
        return $this->getContainerName() === $document->getContainerName() && $this->getPrimaryKey() === $document->getPrimaryKey();
    }

    /**
     * Returns the propert[y|ies] used to compose the model pk
     *
     * @return string|array
     */
    public function primaryKey() {
        return '_pk';
    }

    /**
     * Returns the model's pk value
     *
     * @return mixed
     */
    public function getPrimaryKey() {
        $pk = $this->primaryKey();
        if (is_string($pk))
            return $this->{$pk};
        else {
            $isNull = true;
            $return = array();
            foreach ($pk as $pkField) {
                $isNull           = $isNull && (is_null($this->{$pkField}) || $this->{$pkField} === '');
                $return[$pkField] = is_null($this->{$pkField}) ? '' : $this->{$pkField};
            }

            /**
             * If all pk values are empty/null, return null
             */
            if ($isNull)
                return null;
            return $return;
        }
    }

    public function setPrimaryKey($value) {
        if ($this->primaryKey() !== '_pk')
            throw new Exception('Unable to store custom primary key!');
        $this->_pk = $value;
    }

    /**
     * Method to ensure that $this->_pk is defined correctly
     */
    protected function ensurePk() {
        if ($this->_pk === null) {
            if ($this->primaryKey() !== '_pk' && $this->getPrimaryKey() !== null) {
                $this->_pk = self::stringify($this->getPrimaryKey());
            } elseif ($this->_object->getKey() !== null) {
                $this->_pk = $this->_object->getKey();
            }
        }
    }

    /**
     * @return string
     */
    public function getEncodedPk() {
        $this->ensurePk();
        return $this->_pk;
    }

    /**
     * Self-recursive function (for arrays)
     * Takes mixed variable types
     * Returns objects/arrays as json
     * Casts any other type to string to ensure type consistency
     *
     * @param mixed $var
     *
     * @return string
     */
    public static function stringify($var) {
        if (is_array($var))
            return \CJSON::encode(array_map(array('self', 'stringify'), $var));
        if (is_object($var))
            return \CJSON::encode($var);
        return (string)$var;
    }

    /* public function cache($duration, $dependency=null, $queryCount=1) {
      $this->getDbConnection()->cache($duration, $dependency, $queryCount);
      return $this;
      } */

    /**
     * @todo Implement support for indexing at bucket level
     * @return array
     */
    public function indexes() {
        return array();
    }

    public function relations() {
        return array();
    }

    public function scopes() {
        return array();
    }

    public function attributeNames() {
        return array_keys((array)$this->getMetaData()->attributes);
    }

    /**
     * Defines validation ruleset for models, override to prevent automatic rule generation.
     *
     * @todo Define rules based on attribute types
     *
     * @return array
     */
    public function rules() {
        return array_merge(parent::rules(), array(
            array(implode(', ', $this->attributeNames()), 'safe', 'on' => 'search'),
        ));
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param array $attributes Array of attribute names to limit searching to
     *
     * @return \ext\activedocument\DataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search(array $attributes = array()) {
        $criteria = new Criteria;

        $attributes = array_intersect_key((array)$this->getMetaData()->attributes, array_flip(!empty($attributes) ? $attributes : $this->getSafeAttributeNames()));
        foreach ($attributes as $name => $attribute) {
            if ($attribute->type === 'string')
                $criteria->compare($name, $this->$name, true);
            else
                $criteria->compare($name, $this->$name);
        }

        return new DataProvider(get_class($this), array(
            'criteria' => $criteria,
        ));
    }

    /**
     * @return \ext\activedocument\Connection
     */
    public function getConnection() {
        if (empty(static::$connName))
            throw new Exception(Yii::t('yii', 'Active Document requires that Document::$connName not be empty.'));

        if (array_key_exists(static::$connName, self::$connections) && self::$connections[static::$connName] instanceof Connection)
            return self::$connections[static::$connName];
        else {
            self::$connections[static::$connName] = Yii::app()->getComponent(static::$connName);
            if (self::$connections[static::$connName] instanceof Connection)
                return self::$connections[static::$connName];
            else
                throw new Exception(Yii::t('yii', 'Active Document requires a "' . static::$connName . '" Connection application component.'));
        }
    }

    /**
     * @return \ext\activedocument\Adapter
     */
    public function getAdapter() {
        return $this->getConnection()->getAdapter();
    }

    /**
     * @return string
     */
    public function containerName() {
        return get_class($this);
    }

    /**
     * @return string
     */
    public function getContainerName() {
        return $this->containerName();
    }

    /**
     * @return \ext\activedocument\Container
     */
    public function getContainer() {
        if ($this->_container === null)
            $this->_container = $this->getAdapter()->getContainer($this->getContainerName(), $this->containerConfig());
        return $this->_container;
    }

    /**
     * @return array
     */
    public function containerConfig() {
        return array();
    }

    /**
     * @return \ext\activedocument\MetaData
     */
    public function getMetaData() {
        if ($this->_md === null)
            $this->_md = self::model(get_class($this))->_md;
        return $this->_md;
    }

    /**
     * @param array|null $attributes  optional
     * @param bool       $clearErrors optional
     *
     * @return bool
     */
    protected function internalValidate(array $attributes = null, $clearErrors = true) {
        return parent::validate($attributes, $clearErrors);
    }

    /**
     * Validate main model and all it's related models recursively.
     *
     * @param string|array $data        attribute[s] and/or relation[s].
     * @param boolean      $clearErrors whether to call {@link CModel::clearErrors} before performing validation.
     *
     * @return boolean whether the validation is successful without any error.
     */
    public function validate($data = null, $clearErrors = true) {
        if ($data === null) {
            $attributes = null;
            $newData    = array();
        } else {
            if (is_string($data))
                $data = array($data);
            $attributeNames = $this->attributeNames();
            $attributes     = array_intersect($data, $attributeNames);

            if ($attributes === array())
                $attributes = null;

            $newData = array_diff($data, $attributeNames);
        }

        $valid = $this->internalValidate($attributes, $clearErrors);

        foreach ($newData as $name => $data) {
            if (!is_array($data))
                $name = $data;

            if (!$this->hasRelated($name))
                continue;

            $related = $this->getRelated($name);

            if (is_array($related)) {
                foreach ($related as $model) {
                    $valid = $model->validate(is_array($data) ? $data : null, $clearErrors) && $valid;
                }
            } else {
                $valid = $related->validate(is_array($data) ? $data : null, $clearErrors) && $valid;
            }
        }

        return $valid;
    }

    /**
     * Saves model and it's relation info
     *
     * @param bool         $runValidation whether to perform validation before saving the record.
     * @param string|array $attributes    attribute[s] to be validated/saved
     *
     * @return boolean whether the saving succeeds.
     */
    public function save($runValidation = true, $attributes = null) {
        if (!$runValidation || $this->validate($attributes))
            return $this->saveInternal($attributes);
        else
            return false;
    }

    /**
     * Internal mechanism for recursively saving model & relations
     *
     * @param array $attributes     Array of attributes to save
     * @param array $modelRelations Registry of models that have been processed, to avoid endless recursion
     *
     * @return boolean True on success
     */
    protected function saveInternal(array $attributes = null, array &$modelRelations = array()) {
        if ($attributes === array())
            $attributes = null;
        array_push($modelRelations, $this);

        if (!$this->beforeSaveInternal())
            return false;

        $relations = $this->getMetaData()->relations;
        $queue     = array();

        foreach ($relations as $name => $relation) {
            if (!$this->hasRelated($name))
                continue;

            if ($relation instanceof BelongsToRelation) {
                /**
                 * @var \ext\activedocument\Document $related
                 */
                $related = $this->getRelated($name);

                /**
                 * If the relation is empty...
                 */
                if ($related === null || $related === '') {
                    /**
                     * If the relation was already empty, skip
                     */
                    if (!isset($this->getObject()->data[$name]) || $this->getObject()->data[$name] === null || $this->getObject()->data[$name] === '')
                        continue;

                    /**
                     * If the relation wasn't already empty, then it should be removed
                     */
                    Yii::trace('Removing a relation "' . $name . '" of type ' . get_class($relation) . ' in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.Document');
                    $this->clearRelation($name);
                    continue;
                }

                /**
                 * If the relation was already set, skip
                 */
                if (isset($this->getObject()->data[$name]) && !$related->getIsNewRecord() &&
                    ((!$relation->nested && $related->getPrimaryKey() === $this->getObject()->data[$name]) ||
                        ($relation->nested && $this->getObject()->data[$name] === $related->getObject()->data &&
                            !$related->isModified))
                ) {
                    continue;
                }

                Yii::trace('Saving a relation "' . $name . '" of type ' . get_class($relation) . ' in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.Document');

                /**
                 * Ensure $related is saved, so we have current PK
                 */
                if (!in_array($related, $modelRelations, true) && $related->isModified)
                    $related->saveInternal(null, $modelRelations, $this);
                $this->appendRelation($related, $name);
            }
            else
                $queue[] = $name;
        }

        if ($this->getIsNewRecord() && empty($this->primaryKey)) {
            if (!$this->insert($attributes))
                return false;
            elseif ($queue === array()) {
                $this->afterSaveInternal();
                return true;
            }
        }

        /**
         * @todo May need to separate saving from the process, until the end, to prevent repetitive saving
         */
        foreach ($queue as $name) {
            /**
             * @var \ext\activedocument\Relation $relations[$name]
             */
            if ($relations[$name] instanceof HasManyRelation) {
                /**
                 * @var \ext\activedocument\Document[] $related
                 */
                $related = $this->getRelated($name);

                /**
                 * If the relation is empty...
                 */
                if ($related === null || $related === array()) {
                    /**
                     * If the relation was already empty, skip
                     */
                    if (!isset($this->getObject()->data[$name]) || $this->getObject()->data[$name] === null || $this->getObject()->data[$name] === array())
                        continue;

                    /**
                     * If the relation wasn't already empty, then it should be removed
                     */
                    Yii::trace('Removing a relation "' . $name . '" of type ' . get_class($relations[$name]) . ' in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.Document');
                    $this->clearRelation($name);
                    continue;
                }

                /**
                 * @var \ext\activedocument\Document $model
                 */
                foreach ($related as $model) {
                    /**
                     * If the relation was already set, skip
                     */
                    if (isset($this->getObject()->data[$name]) && !$model->getIsNewRecord() &&
                        ((!$relations[$name]->nested && in_array($model->getPrimaryKey(), $this->getObject()->data[$name])) ||
                            ($relations[$name]->nested && array_key_exists($model->encodedPk, $this->getObject()->data[$name]) &&
                                !$model->isModified))
                    ) {
                        continue;
                    }

                    if (!in_array($model, $modelRelations, true) && $model->isModified)
                        $model->saveInternal(null, $modelRelations, $this);

                    Yii::trace('Saving a relation "' . $name . '" of type ' . get_class($relations[$name]) . ' in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.Document');
                    $this->appendRelation($model, $name);
                }
            } else {
                /**
                 * @var \ext\activedocument\Document $related
                 */
                $related = $this->getRelated($name);

                /**
                 * If the relation is empty...
                 */
                if ($related === null || $related === '') {
                    /**
                     * If the relation was already empty, skip
                     */
                    if (!isset($this->getObject()->data[$name]) || $this->getObject()->data[$name] === null || $this->getObject()->data[$name] === '')
                        continue;

                    /**
                     * If the relation wasn't already empty, then it should be removed
                     */
                    Yii::trace('Removing a relation "' . $name . '" of type ' . get_class($relations[$name]) . ' in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.Document');
                    $this->clearRelation($name);
                    continue;
                }

                /**
                 * If the relation was already set, skip
                 */
                if (isset($this->getObject()->data[$name]) && !$related->getIsNewRecord() &&
                    ((!$relations[$name]->nested && $related->getPrimaryKey() === $this->getObject()->data[$name]) ||
                        ($relations[$name]->nested && $this->getObject()->data[$name] === $related->getObject()->data &&
                            !$related->isModified))
                ) {
                    continue;
                }

                if (!in_array($related, $modelRelations, true) && $related->isModified)
                    $related->saveInternal(null, $modelRelations, $this);

                Yii::trace('Saving a relation "' . $name . '" of type ' . get_class($relations[$name]) . ' in ' . get_class($this) . '.saveInternal()', 'ext.activedocument.Document');
                $this->appendRelation($related, $name);
            }
        }

        if ($this->isModified) {
            if (!($this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes)))
                return false;
        }

        $this->afterSaveInternal();

        return true;
    }

    /**
     * Pushes the $relationModel's PK into current object's relations
     *
     * @param Document $relationModel
     * @param string   $relationName
     *
     * @throws Exception
     */
    public function appendRelation(Document $relationModel, $relationName) {
        $pk = $relationModel->getPrimaryKey();
        if (empty($pk))
            throw new Exception(Yii::t('yii', 'Related model primary key must not be empty!') .
                PHP_EOL . 'Model: ' . \CVarDumper::dumpAsString($relationModel->getAttributes()));

        /**
         * @var \ext\activedocument\Relation
         */
        $relation = $this->getMetaData()->relations->$relationName;

        if ($relation instanceof HasManyRelation) {
            /**
             * Manages relation indexes stored within the model
             */
            if ($relation->autoIndices !== array()) {
                foreach ($relation->autoIndices as $index) {
                    $indexName  = $relationName . '_' . $index;
                    $indexValue = $relationModel->getAttribute($index);
                    if ($indexValue === '' || $indexValue === null)
                        continue;
                    $indexValue = self::stringify($indexValue);
                    if (!isset($this->getObject()->data[$indexName]) || !is_array($this->getObject()->data[$indexName]))
                        $this->getObject()->data[$indexName] = array();
                    if (!isset($this->getObject()->data[$indexName][$indexValue]) || !is_array($this->getObject()->data[$indexName][$indexValue]))
                        $this->getObject()->data[$indexName][$indexValue] = array();
                    if (!in_array($pk, $this->getObject()->data[$indexName][$indexValue], true))
                        $this->getObject()->data[$indexName][$indexValue][] = $pk;
                }
            }
            if (!isset($this->getObject()->data[$relationName]) || !is_array($this->getObject()->data[$relationName]))
                $this->getObject()->data[$relationName] = array();
            if ($relation->nested === true) {
                $this->getObject()->data[$relationName][$relationModel->getEncodedPk()] = $relationModel->getObject()->data;
            } elseif (!in_array($pk, $this->getObject()->data[$relationName], true))
                $this->getObject()->data[$relationName][] = $pk;
        } else
            $this->getObject()->data[$relationName] = $relation->nested ? $relationModel->getObject()->data : $pk;

        $this->_modifiedAttributes[] = $relationName;
    }

    /**
     * Removes the $relationModel's PK from current object's relations
     *
     * @param Document $relationModel
     * @param string   $relationName
     *
     * @throws Exception
     */
    public function removeRelation(Document $relationModel, $relationName) {
        if (!isset($this->getObject()->data[$relationName]))
            return;

        $pk = $relationModel->getPrimaryKey();
        if (empty($pk))
            throw new Exception(Yii::t('yii', 'Related model primary key must not be empty!'));

        /**
         * @var \ext\activedocument\Relation
         */
        $relation = $this->getMetaData()->relations->$relationName;

        if ($relation instanceof HasManyRelation && is_array($this->getObject()->data[$relationName])) {
            $key = $relation->nested ? $relationModel->getEncodedPk() : array_search($pk, $this->getObject()->data[$relationName]);
            if ($key !== false)
                unset($this->getObject()->data[$relationName][$key]);
            /**
             * Remove related model pk from autoindices
             */
            if ($relation->autoIndices !== array()) {
                foreach ($relation->autoIndices as $index) {
                    $indexName = $relationName . '_' . $index;
                    if (!isset($this->getObject()->data[$indexName]) || !is_array($this->getObject()->data[$indexName]))
                        continue;
                    array_walk($this->getObject()->data[$indexName], function(&$v, $k) use($pk) {
                        if (($_k = array_search($pk, $v)) !== false)
                            unset($v[$_k]);
                    });
                }
            }
        } else
            unset($this->getObject()->data[$relationName]);

        $this->_modifiedAttributes[] = $relationName;
    }

    /**
     * Empties any object associations for the specified relation
     *
     * @param string $relationName
     */
    public function clearRelation($relationName) {
        unset($this->getObject()->data[$relationName]);

        /**
         * @var \ext\activedocument\Relation
         */
        $relation = $this->getMetaData()->relations->$relationName;

        /**
         * Remove autoindices
         */
        if ($relation->autoIndices !== array()) {
            foreach ($relation->autoIndices as $index) {
                $indexName = $relationName . '_' . $index;
                if (isset($this->getObject()->data[$indexName]))
                    unset($this->getObject()->data[$indexName]);
            }
        }
        $this->_modifiedAttributes[] = $relationName;
    }

    public function onBeforeSave(Event $event) {
        $this->raiseEvent('onBeforeSave', $event);
    }

    public function onAfterSave(\CEvent $event) {
        $this->raiseEvent('onAfterSave', $event);
    }

    public function onBeforeSaveInternal(Event $event) {
        $this->raiseEvent('onBeforeSaveInternal', $event);
    }

    public function onAfterSaveInternal(\CEvent $event) {
        $this->raiseEvent('onAfterSaveInternal', $event);
    }

    public function onBeforeDelete(Event $event) {
        $this->raiseEvent('onBeforeDelete', $event);
    }

    public function onAfterDelete(\CEvent $event) {
        $this->raiseEvent('onAfterDelete', $event);
    }

    public function onBeforeFind(Event $event) {
        $this->raiseEvent('onBeforeFind', $event);
    }

    public function onAfterFind(\CEvent $event) {
        $this->raiseEvent('onAfterFind', $event);
    }

    protected function beforeSave() {
        if ($this->hasEventHandler('onBeforeSave')) {
            $event = new Event($this);
            $this->onBeforeSave($event);
            return $event->isValid;
        }
        else
            return true;
    }

    protected function afterSave() {
        if ($this->hasEventHandler('onAfterSave'))
            $this->onAfterSave(new \CEvent($this));
        $this->resetModified();
    }

    protected function beforeSaveInternal() {
        if ($this->hasEventHandler('onBeforeSaveInternal')) {
            $event = new Event($this);
            $this->onBeforeSaveInternal($event);
            return $event->isValid;
        }
        else
            return true;
    }

    protected function afterSaveInternal() {
        if ($this->hasEventHandler('onAfterSaveInternal'))
            $this->onAfterSaveInternal(new \CEvent($this));
        $this->resetModified();
    }

    protected function beforeDelete() {
        if ($this->hasEventHandler('onBeforeDelete')) {
            $event = new Event($this);
            $this->onBeforeDelete($event);
            return $event->isValid;
        }
        else
            return true;
    }

    protected function afterDelete() {
        if ($this->hasEventHandler('onAfterDelete'))
            $this->onAfterDelete(new \CEvent($this));
        $this->resetModified();
    }

    protected function beforeFind() {
        if ($this->hasEventHandler('onBeforeFind')) {
            $event = new Event($this);
            // for backward compatibility
            $event->criteria = func_num_args() > 0 ? func_get_arg(0) : null;
            $this->onBeforeFind($event);
        }
    }

    protected function afterFind() {
        if ($this->hasEventHandler('onAfterFind'))
            $this->onAfterFind(new \CEvent($this));
        $this->resetModified();
    }

    public function beforeFindInternal() {
        $this->beforeFind();
    }

    public function afterFindInternal() {
        $this->afterFind();
    }

    protected function store(array $attributes = null) {
        $attributes = $this->getAttributes($attributes);
        foreach ($attributes as $name => $value) {
            $this->_object->data[$name] = $value;
        }
        $this->_object->setKey($this->_pk);
        return $this->_object->store();
    }

    /**
     * @param array|null $attributes optional
     *
     * @return bool
     * @throws Exception
     */
    public function insert(array $attributes = null) {
        if (!$this->getIsNewRecord())
            throw new Exception(Yii::t('yii', 'The document cannot be inserted because it is not new.'));
        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.insert()', 'ext.activedocument.Document');
            $this->ensurePk();
            if ($this->store($attributes)) {
                $this->_pk = $this->_object->getKey();
                $this->afterSave();
                $this->setIsNewRecord(false);
                $this->setScenario('update');
                return true;
            }
        }
        return false;
    }

    /**
     * @param array|null $attributes optional
     *
     * @return bool
     * @throws Exception
     */
    public function update(array $attributes = null) {
        if ($this->getIsNewRecord())
            throw new Exception(Yii::t('yii', 'The document cannot be updated because it is new.'));
        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.update()', 'ext.activedocument.Document');
            $this->ensurePk();
            if ($this->store($attributes)) {
                $this->_pk = $this->_object->getKey();
                $this->afterSave();
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $attributes
     *
     * @return bool
     * @throws Exception
     */
    public function saveAttributes(array $attributes) {
        if ($this->getIsNewRecord())
            throw new Exception(Yii::t('yii', 'The document cannot be updated because it is new.'));
        Yii::trace(get_class($this) . '.saveAttributes()', 'ext.activedocument.Document');
        $this->setAttributes($attributes, false);
        $this->ensurePk();
        if ($this->store(array_keys($attributes))) {
            $this->_pk                 = $this->_object->getKey();
            $this->_modifiedAttributes = array_diff(array_unique($this->_modifiedAttributes), array_keys($attributes));
            return true;
        }
        return false;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function delete() {
        if ($this->getIsNewRecord())
            throw new Exception(Yii::t('yii', 'The document cannot be deleted because it is new.'));
        Yii::trace(get_class($this) . '.delete()', 'ext.activedocument.Document');
        if ($this->beforeDelete()) {
            $this->ensurePk();
            $result = $this->_object->delete();
            $this->afterDelete();
            return $result;
        }
        else
            return false;
    }

    /**
     * @param Criteria|array   $condition Optional. Default: null
     * @param array            $params
     *
     * @return int
     */
    public function count($condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.count()', 'ext.activedocument.Document');
        $criteria = $this->buildCriteria($condition, $params);
        $this->applyScopes($criteria);
        return $this->_container->count($criteria);
    }

    /**
     * Checks whether there is row satisfying the specified condition.
     * See {@link find()} for detailed explanation about $condition and $params.
     *
     * @param Criteria|array   $condition Optional. Default: null
     * @param array            $params    Optional.
     *
     * @return boolean whether there is row satisfying the specified condition.
     */
    public function exists($condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.exists()', 'ext.activedocument.Document');
        $criteria        = $this->buildCriteria($condition, $params);
        $criteria->limit = 1;
        $this->applyScopes($criteria);
        return $this->_container->count($criteria) > 0;
    }

    /**
     * @param Criteria|array   $condition Optional. Default: null
     * @param array            $params    Optional.
     *
     * @return Document|null
     */
    public function find($condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.find()', 'ext.activedocument.Document');
        return $this->query($this->buildCriteria($condition, $params));
    }

    /**
     * @param mixed            $key
     * @param Criteria|array   $condition Optional. Default: null
     * @param array            $params    Optional.
     *
     * @return Document|null
     */
    public function findByPk($key, $condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.findByPk()', 'ext.activedocument.Document');
        return $this->query($this->buildCriteria($condition, $params), false, array($key));
    }

    /**
     * Finds a single document that has the specified attribute values.
     * See {@link find()} for detailed explanation about $condition and $params.
     *
     * @param array            $attributes list of attribute values (indexed by attribute names) that the documents should match.
     *                                     An attribute value can be an array which will be used to generate an array (IN) condition.
     * @param Criteria|array   $condition  Optional. Default: null
     * @param array            $params     Optional.
     *
     * @return Document|null the record found. Null if none is found.
     */
    public function findByAttributes($attributes, $condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.findByAttributes()', 'ext.activedocument.Document');
        return $this->query($this->buildCriteria($condition, $params, $attributes), false);
    }

    /**
     * @param Criteria|array   $condition Optional. Default: null
     * @param array            $params    Optional.
     *
     * @return array|Document|null
     */
    public function findAll($condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.findAll()', 'ext.activedocument.Document');
        return $this->query($this->buildCriteria($condition, $params), true);
    }

    /**
     * @param array            $keys
     * @param Criteria|array   $condition Optional. Default: null
     * @param array            $params    Optional.
     *
     * @return array|Document|null
     */
    public function findAllByPk(array $keys, $condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.findAllByPk()', 'ext.activedocument.Document');
        return $this->query($this->buildCriteria($condition, $params), true, $keys);
    }

    /**
     * Finds all documents that have the specified attribute values.
     * See {@link find()} for detailed explanation about $condition and $params.
     *
     * @param array            $attributes list of attribute values (indexed by attribute names) that the documents should match.
     *                                     An attribute value can be an array which will be used to generate an array (IN) condition.
     * @param Criteria|array   $condition  Optional. Default: null
     * @param array            $params     Optional.
     *
     * @return Document|null the record found. Null if none is found.
     */
    public function findAllByAttributes($attributes, $condition = null, array $params = array()) {
        Yii::trace(get_class($this) . '.findAllByAttributes()', 'ext.activedocument.Document');
        return $this->query($this->buildCriteria($condition, $params, $attributes), true);
    }

    /**
     * @param Criteria $criteria
     * @param bool     $all
     * @param array    $keys
     *
     * @return array|Document|null
     */
    protected function query(Criteria $criteria, $all = false, array $keys = array()) {
        $this->beforeFind();
        $this->applyScopes($criteria);

        if (!empty($keys))
            $keys = array_map(array('self', 'stringify'), $keys);

        $objects       = array();
        $emptyCriteria = new Criteria;
        if ($criteria == $emptyCriteria && !empty($keys)) {
            /**
             * @todo Need to implement getObjects to speed up this process
             * $objects = $this->getContainer()->getObjects($keys);
             */
            foreach ($keys as $key) {
                $objects[] = $this->loadObject($key);
            }
        } else {
            if (!$all)
                $criteria->limit = 1;
            if (!empty($keys))
                foreach ($keys as $key)
                    $criteria->addInput($this->containerName(), $key);
            $objects = $this->getContainer()->find($criteria);
        }

        /**
         * @todo This is temporary fix for issue where empty object is returned...
         */
        array_filter($objects, function(\ext\activedocument\Object $obj){
            return !($obj->data===null || $obj->data===array());
        });

        if ($objects===array())
            return $all ? array() : null;

        return $all ? $this->populateDocuments($objects) : $this->populateDocument(array_shift($objects));
    }

    /**
     * @param Criteria|array   $condition
     * @param array            $params
     * @param array            $attributes
     *
     * @return Criteria
     */
    protected function buildCriteria($condition, array $params = array(), array $attributes = array()) {
        if (is_array($condition))
            $criteria = new Criteria($condition);
        else if ($condition instanceof Criteria)
            $criteria = clone $condition;
        else
            $criteria = new Criteria;

        if ($params !== array())
            $criteria->mergeWith(array('params' => $params));

        if ($attributes !== array())
            array_walk($attributes, function($val, $key) use($criteria) {
                /**
                 * @var Criteria $criteria
                 */
                if (is_array($val))
                    $criteria->addArrayCondition($key, $val);
                else
                    $criteria->addColumnCondition(array($key => $val));
            });

        return $criteria;
    }

    /**
     * Applies the query scopes to the given criteria.
     * This method merges {@link criteria} with the given criteria parameter.
     * It then resets {@link criteria} to be null.
     *
     * @param Criteria $criteria the query criteria. This parameter may be modified by merging {@link criteria}.
     *
     * @return self
     */
    public function applyScopes(Criteria &$criteria) {
        if (!empty($criteria->scopes)) {
            $scs = $this->scopes();
            $c   = $this->getCriteria();
            foreach ((array)$criteria->scopes as $k => $v) {
                if (is_integer($k)) {
                    if (is_string($v)) {
                        if (isset($scs[$v])) {
                            $c->mergeWith($scs[$v], true);
                            continue;
                        }
                        $scope  = $v;
                        $params = array();
                    } else if (is_array($v)) {
                        $scope  = key($v);
                        $params = current($v);
                    }
                } else if (is_string($k)) {
                    $scope  = $k;
                    $params = $v;
                }

                call_user_func_array(array($this, $scope), (array)$params);
            }
        }

        if (isset($c) || ($c = $this->getCriteria(false)) !== null) {
            $c->mergeWith($criteria);
            $criteria = $c;
            $this->_c = null;
        }

        return $this;
    }

    /**
     * @param Object $object
     * @param bool   $callAfterFind
     *
     * @return Document
     */
    public function populateDocument(Object $object, $callAfterFind = true) {
        $document = $this->instantiate($object);
        $document->setScenario('update');
        $document->setObject($object);
        $document->init();
        $document->attachBehaviors($document->behaviors());
        if ($callAfterFind)
            $document->afterFind();
        return $document;
    }

    /**
     * @param Object[] $objects
     * @param bool     $callAfterFind
     * @param string   $index Document attribute to index the results by
     *
     * @return Document[]
     */
    public function populateDocuments(array $objects, $callAfterFind = true, $index = null) {
        $documents = array();
        foreach ($objects as $object) {
            if (($document = $this->populateDocument($object, $callAfterFind)) !== null) {
                if ($index === null)
                    $documents[] = $document;
                else
                    $documents[$document->$index] = $document;
            }
        }
        return $documents;
    }

    /**
     * @param Object $object
     *
     * @return Document
     */
    protected function instantiate(Object $object) {
        $class    = get_class($this);
        $document = new $class(null);
        return $document;
    }

    /**
     * @param $offset
     *
     * @return bool
     */
    public function offsetExists($offset) {
        return $this->__isset($offset);
    }

}