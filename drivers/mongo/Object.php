<?php

namespace ext\activedocument\drivers\mongo;

/**
 * Object for Mongo driver
 */
class Object extends \ext\activedocument\Object {

    protected function loadObjectInstance($new = true) {
        $data = null;
        if ($this->getKey() !== null && !$new) {
            \Yii::trace('Mongo FindByPk query: ' . \CVarDumper::dumpAsString($this->getKey()), 'ext.activedocument.drivers.mongo.Object');
            /**
             * @todo Using dotnotation here allows partial id matching, may need to revert back to exact matching (need tests)
             */
            $data = $this->_container->getContainerInstance()->findOne(self::dotNotation($this->getKey(), '_id', null));
        }
        if ($data == null)
            $data = array();
        return new \ArrayObject($data, \ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @return bool
     */
    protected function storeInternal() {
        $origData = $this->getObjectData();
        $this->setObjectData($this->data);
        try {
            /**
             * Ensure _id is not null or empty string. Empty string is a valid key in mongo
             */
            if (property_exists($this->_objectInstance, '_id') && ($this->_objectInstance->_id === null || $this->_objectInstance->_id === ''))
                unset($this->_objectInstance->_id);

            /**
             * When we aren't specifying a pk, we should insert, which will update _objectInstance with new pk
             */
            if (!isset($this->_objectInstance->_id)) {
                \Yii::trace('Inserted the value: ' . \CVarDumper::dumpAsString($this->_objectInstance), 'ext.activedocument.drivers.mongo.Object');
                $this->_container->getContainerInstance()->insert($this->_objectInstance, array('safe' => true));
            } else {
                $dataDiff = self::recurseDiff($this->getObjectData(), $origData);
                $criteria = array('_id'=>$this->_objectInstance->_id);

                if ($dataDiff!==array())
                    $dataDiff = self::dotNotation($dataDiff);

                if ($dataDiff===array()) {
                    $result = $this->_container->getContainerInstance()->findOne($criteria);
                    if ($result === null) {
                        #\Yii::trace('Inserted value: ' . \CVarDumper::dumpAsString($this->_objectInstance), 'ext.activedocument.drivers.mongo.Object');
                        $this->_container->getContainerInstance()->insert($this->_objectInstance, array('safe'=>true));
                    }
                } elseif (count($dataDiff)>1) {
                    /**
                     * Make sure that no field exists in more than one procedure
                     */
                    $splitQueries = array();
                    $splitIndex = array();
                    array_walk($dataDiff, function($dataArr, $outerKey)use(&$splitQueries, &$splitIndex){
                        array_walk($dataArr, function($dataArr, $dataKey)use(&$splitQueries, &$splitIndex, $outerKey){
                            $i=0;
                            while(true) {
                                if (!isset($splitQueries[$i]))
                                    $splitQueries[$i] = $splitIndex[$i] = array();
                                if (!in_array($dataKey, $splitIndex[$i])) {
                                    $splitQueries[$i][$outerKey][$dataKey] = $dataArr;
                                    $splitIndex[$i][] = $dataKey;
                                    break;
                                }
                                $i++;
                            }
                        });
                    });
                    unset($dataDiff);

                    /**
                     * Sorting queries to ensure $pull requests issue first
                     */
                    usort($splitQueries, function($a, $b){
                        $remove = array('$pull','$pullAll');
                        if (array()!==array_intersect($remove, array_keys($a)))
                            return -1;
                        elseif (array()!==array_intersect($remove, array_keys($b)))
                            return 1;
                        else
                            return 0;
                    });

                    #\Yii::trace('Stored multiple requests: ' . \CVarDumper::dumpAsString($splitQueries), 'ext.activedocument.drivers.mongo.Object');
                    $instance = $this->_container->getContainerInstance();
                    array_map(function($query)use($instance, $criteria){
                        $instance->update($criteria, $query, array('safe'=>true, 'upsert'=>true));
                    }, $splitQueries);
                } else {
                    #\Yii::trace('Stored the value: ' . \CVarDumper::dumpAsString($dataDiff), 'ext.activedocument.drivers.mongo.Object');
                    $this->_container->getContainerInstance()->update($criteria, $dataDiff, array('safe'=>true, 'upsert'=>true));
                }
            }
            $this->reloadInternal();
        } catch (\MongoException $e) {
            throw new \ext\activedocument\Exception('MongoDB threw an exception: ' . $e->getMessage(), 0, $e);
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function deleteInternal() {
        $this->setObjectData($this->data);
        try {
            $this->_container->getContainerInstance()->remove(array('_id' => $this->getKey()), array('safe' => true));
        } catch (\MongoException $e) {
            /**
             * @todo Throw custom exception
             */
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function reloadInternal() {
        $this->setObjectData($this->data);
        $this->_objectInstance = $this->loadObjectInstance(false);
        $this->data            = $this->getObjectData();
        return true;
    }

    /**
     * @return \MongoId|null|string
     */
    public function getKey() {
        if ($this->_objectInstance instanceof \ArrayObject && isset($this->_objectInstance->_id))
            $key = $this->_objectInstance->_id;
        else
            $key = parent::getKey();
        $key = self::properId($key);
        #\Yii::trace('Mongo getKey(): ' . \CVarDumper::dumpAsString($key), 'ext.activedocument.drivers.mongo.Object');

        return $key;
    }

    /**
     * @param string|\MongoId $value
     */
    public function setKey($value) {
        $value = self::properId($value);
        #\Yii::trace('Mongo setKey(): ' . \CVarDumper::dumpAsString($value), 'ext.activedocument.drivers.mongo.Object');
        if ($this->_objectInstance instanceof \ArrayObject) {
            $this->_objectInstance->_id = $value;
        }
        return parent::setKey($value);
    }

    /**
     * @param mixed $id
     *
     * @return \MongoId|mixed
     */
    public static function properId($id) {
        if ($id === null)
            return $id;
        if (is_array($id) && isset($id['$id']))
            return new \MongoId($id['$id']);
        elseif (is_string($id) && ($mId = new \MongoId($id)) && $id === (string)$mId)
            return $mId;
        return $id;
    }

    /**
     * @return mixed
     */
    protected function getObjectData() {
        return (array)$this->_objectInstance;
    }

    /**
     * @param mixed $data
     */
    protected function setObjectData($data) {
        $this->_objectInstance->exchangeArray(array_merge((array)$this->_objectInstance, (array)$data));
    }

    /**
     * Converts result of recurseDiff to mongodb dot notation format, which is safer for updating fields
     *
     * @static
     * @param mixed $arr
     * @param string $prefix
     * @param string $action
     * @param array $base
     * @return array
     */
    public static function dotNotation($arr, $prefix = '', $action = '$set', &$base = array()) {
        if ((is_array($arr) && !is_int(key($arr))) || (is_object($arr) && !method_exists($arr, '__toString'))) {
            foreach ((array)$arr as $k => $v) {
                $p = $prefix;
                $a = $action;
                switch ($k) {
                    case '$set':
                    case '$addToSet':
                    case '$unset':
                    case '$pull':
                    case '$pullAll':
                    case '$push':
                    case '$pushAll':
                        $a = $k;
                        break;
                    case '$each':
                        /**
                         * For $each, set the value and skip recursion
                         */
                        $base[$a][$p][$k] = $v;
                        continue 2; /* skips to the next iteration of the foreach */
                    default:
                        if ($p !== '')
                            $p .= '.';
                        $p .= $k;
                        break;
                }
                self::dotNotation($v, $p, $a, $base);
            }
        } else {
            if (isset($action))
                $base[$action][$prefix] = $arr;
            else
                /**
                 * Just return basic dot notation in flat array
                 */
                $base[$prefix] = $arr;
        }
        return $base;
    }

    public static function arrayObject($var) {
        if (is_array($var) || (is_object($var) && !method_exists($var, '__toString'))) {
            $arrObj = function($var){
                $obj = new \stdClass;
                foreach ((object) $var as $k=>$v)
                    $obj->$k = \ext\activedocument\drivers\mongo\Object::arrayObject($v);
                return $obj;
            };
            $var = $arrObj($var);
        }
        return $var;
    }

    /**
     * Method for comparing arrays to determine what is diff between array1 and following arrays
     * Method is recursive
     *
     * While this method is currently built for the specific needs of Mongo, it could
     * be abstracted for general array diff purposes
     *
     * @todo Optimize!
     *
     * @static
     * @return array
     */
    public static function recurseDiff() {
        $castToArray = function($arr) {
            return (array)$arr;
        };
        $checkExists = function($arr, $value, $key) {
            if (is_string($key))
                return array_key_exists($key, $arr);
            else
                return in_array($value, $arr);
        };

        $arrays = func_get_args();
        $array1 = array_shift($arrays);

        /**
         * We were using array_diff* methods, but weren't flexible enough
         */
        $added = $removed = $remaining = array();
        array_walk($castToArray($array1), function($v, $k) use(&$added, $arrays, $castToArray, $checkExists) {
            $itemExists = array_filter(array_map($castToArray, $arrays), function($arr) use($v, $k, $checkExists) {
                return $checkExists($arr, $v, $k);
            });
            /**
             * If $itemExists is empty, then a new element has been added
             */
            if ($itemExists === array())
                $added[$k] = $v;
        });
        array_walk($castToArray(current($arrays)), function($v, $k) use(&$removed, $array1, $castToArray, $checkExists) {
            if (!$checkExists($castToArray($array1), $v, $k))
                $removed[$k] = $v;
        });
        array_walk($castToArray($array1), function($v, $k) use($added, $removed, &$remaining, $castToArray, $checkExists) {
            $itemExists = array_filter(array_map($castToArray, array($added, $removed)), function($arr) use($v, $k, $checkExists) {
                return $checkExists($arr, $v, $k);
            });
            if ($itemExists === array())
                $remaining[$k] = $v;
        });

        $changed    = array();
        $keysToDiff = array();
        array_walk($remaining, function($val, $k) use(&$changed, &$keysToDiff, $arrays) {
            if ((is_array($val) || (is_object($val) && !method_exists($val, '__toString')))) {
                $chArr = call_user_func_array(array('\ext\activedocument\drivers\mongo\Object', 'recurseDiff'), array_merge(array($val), array_map(function($v) use($k, $val) {
                    if (is_array($v) || is_object($v)) {
                        $v = (array)$v;
                        if (is_int($k)) {
                            if (($_k = array_search($val, $v)))
                                return $v[$_k];
                        } elseif (array_key_exists($k, $v))
                            return $v[$k];
                    }
                    return array();
                }, $arrays)));
                if ($chArr !== array()) {
                    if (is_int($k)) {
                        $changed[$k] = $val;
                    } else {
                        if (isset($chArr['$addToSet'])) {
                            if (!isset($changed['$addToSet']))
                                $changed['$addToSet'] = array();
                            if (!isset($changed['$addToSet'][$k]))
                                $changed['$addToSet'][$k] = array();
                            if (!isset($changed['$addToSet'][$k]['$each']))
                                $changed['$addToSet'][$k]['$each'] = array();
                            $changed['$addToSet'][$k]['$each'] += $chArr['$addToSet'];
                            unset($chArr['$addToSet']);
                        }
                        if (isset($chArr['$pullAll'])) {
                            if (!isset($changed['$pullAll']))
                                $changed['$pullAll'] = array();
                            if (!isset($changed['$pullAll'][$k]))
                                $changed['$pullAll'][$k] = array();
                            $changed['$pullAll'][$k] += $chArr['$pullAll'];
                            unset($chArr['$pullAll']);
                        }
                        if ($chArr !== array())
                            $changed[$k] = $chArr;
                    }
                }
            } else
                $keysToDiff[] = $k;
        });

        /**
         * @todo Need to check if this logic works as intended with int keys
         */
        if ($keysToDiff !== array()) {
            $changed = array_merge($changed,
                call_user_func_array('array_diff_assoc', array_merge(array(array_intersect_key($remaining, array_flip($keysToDiff))), array_map($castToArray, $arrays)))
            );
        }

        $return = array();
        if ($added !== array() || $changed !== array()) {
            if (isset($changed['$addToSet'])) {
                $return['$addToSet'] = $changed['$addToSet'];
                unset($changed['$addToSet']);
            }
            if (isset($changed['$pullAll'])) {
                $return['$pullAll'] = $changed['$pullAll'];
                unset($changed['$pullAll']);
            }
            array_walk(array_merge($added, $changed), function($v, $k) use(&$return) {
                if (is_int($k))
                    $return['$addToSet'][$k] = $v;
                else
                    $return['$set'][$k] = $v;
            });
        }
        if ($removed !== array()) {
            array_walk($removed, function($v, $k) use(&$return) {
                if (is_int($k)) {
                    $return['$pullAll'][$k] = $v;
                } else
                    $return['$unset'][$k] = true;
            });
        }
        return $return;
    }

}