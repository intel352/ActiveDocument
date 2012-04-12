<?php

namespace ext\activedocument\drivers\mongo;

/**
 * Adapter for Mongo driver
 *
 * Listed are attributes supported in a Mongo connection, as described by php.net
 *
 * @link                        http://www.php.net/manual/en/mongo.construct.php
 *
 * @property bool $connect      If the constructor should connect before returning. Default is TRUE.
 * @property int $timeout       For how long the driver should try to connect to the database (in milliseconds).
 * @property string $replicaSet The name of the replica set to connect to. If this is given, the master will be determined by using the ismaster database command on the seeds, so the driver may end up connecting to a server that was not even listed.
 * @property string $username   The username can be specified here, instead of including it in the host list. This is especially useful if a username has a ":" in it. This overrides a username set in the host list.
 * @property string $password   The password can be specified here, instead of including it in the host list. This is especially useful if a password has a "@" in it. This overrides a password set in the host list.
 * @property string $db         The database to authenticate against can be specified here, instead of including it in the host list. This overrides a database given in the host list.
 */
class Adapter extends \ext\activedocument\Adapter {

    /**
     * @var \Mongo
     */
    protected $mongoConnection;
    /**
     * @var string Server connection string, defaults to php.ini settings.
     * @link http://www.php.net/manual/en/mongo.construct.php
     */
    public $server;
    /**
     * @var string Database to connect to, defaults to "default"
     */
    public $database = 'default';

    /**
     * @param array|null $attributes
     *
     * @return \MongoDB
     */
    protected function loadStorageInstance(array $attributes = null) {
        if (!($this->mongoConnection instanceof \Mongo)) {
            if ($attributes !== null && $attributes !== array()) {
                if ($this->server === null)
                    $this->server = 'mongodb://'.ini_get('mongo.default_host').':'.ini_get('mongo.default_port');
                $this->mongoConnection = new \Mongo($this->server, $attributes);
            } else {
                $this->mongoConnection = new \Mongo;
            }
        }
        return $this->mongoConnection->selectDB($this->database);
    }

    /**
     * @param string $name
     *
     * @return \ext\activedocument\drivers\mongo\Container
     */
    protected function loadContainer($name) {
        return new Container($this, $name);
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     *
     * @return int
     */
    protected function countInternal(\ext\activedocument\Criteria $criteria) {
        return $this->applySearchFilters($criteria)->count();
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     *
     * @return \ext\activedocument\drivers\mongo\Object[]
     */
    protected function findInternal(\ext\activedocument\Criteria $criteria) {
        $cursor = $this->applySearchFilters($criteria);

        /**
         * Apply default sorting
         */
        if (!empty($criteria->order)) {
            $sort    = array();
            $orderBy = explode(',', $criteria->order);
            foreach ($orderBy as $order) {
                preg_match('/(?:([\w\\\]+)\.)?(\w+)(?:\s+(ASC|DESC))?/', trim($order), $matches);
                $field        = $matches[2];
                $desc         = (isset($matches[3]) && strcasecmp($matches[3], 'desc') === 0);
                $sort[$field] = $desc ? -1 : 1;
            }
            $cursor->sort($sort);
        }

        /**
         * Apply limit
         */
        if ($criteria->limit > 0)
            $cursor->limit($criteria->limit);

        if ($criteria->offset > 0)
            $cursor->skip($criteria->offset);

        $objects = array();
        if ($info = $cursor->info()) {
            \Yii::trace('Mongo Find query: ' . \CVarDumper::dumpAsString($info), 'ext.activedocument.drivers.mongo.Adapter');
            $container = array_pop(explode('.', $info['ns']));
            iterator_apply($cursor, array($this, 'iterateObjects'), array($cursor, &$objects, $container));
        }
        return $objects;
    }

    protected function iterateObjects(\MongoCursor $cursor, &$objects, $container) {
        $current = $cursor->current();
        array_push($objects, $this->populateObject($container, $current['_id'], $current));
        return true;
    }

    /**
     * @param \ext\activedocument\Criteria $criteria
     *
     * @return \MongoCursor
     */
    protected function applySearchFilters(\ext\activedocument\Criteria $criteria) {
        $command = array();
        $query   = array();

        $collection = null;
        if (!empty($criteria->container))
            $collection = $criteria->container;

        if (!empty($criteria->inputs)) {
            foreach ($criteria->inputs as $input) {
                if ($collection == null)
                    $collection = $input['container'];
                if ($input['container'] == $collection && !empty($input['key'])) {
                    if (!isset($query['_id']))
                        $query['_id'] = array();
                    $query['_id'][] = Object::properId($input['key']);
                }
            }
        }

        if (!empty($criteria->phases)) {
            foreach ($criteria->phases as $phase) {
                $command[$phase['phase']] = $phase['function'];
                if ($phase['phase'] == 'map' && $phase['args'] !== array())
                    $command['mapparams'] = $phase['args'];
            }

            if ($collection !== null && isset($command['map']) && isset($command['reduce']) && !isset($command['mapreduce']))
                $command['mapreduce'] = $collection;
        }

        if (!empty($criteria->search)) {
            $k = '$regex';
            $o = '$options';
            foreach ($criteria->search as $column) {
                if (!isset($query[$column['column']]))
                    $query[$column['column']] = array();

                if (!isset($query[$column['column']][$k]))
                    $query[$column['column']][$k] = array();
                if (!isset($query[$column['column']][$o]))
                    $query[$column['column']][$o] = array();

                if ($column['escape'])
                    $column['keyword'] = preg_quote($column['keyword'], '/');
                if (!$column['like'])
                    $column['keyword'] = '(?!' . $column['keyword'] . ')';
                $query[$column['column']][$k][] = $column['keyword'];
                $query[$column['column']][$o][] = 'i';
            }
        }

        if (!empty($criteria->between)) {
            foreach ($criteria->columns as $column) {
                $criteria->addColumnCondition(array($column['column'] => $column['valueStart']), '>=');
                $criteria->addColumnCondition(array($column['column'] => $column['valueEnd']), '<=');
            }
        }

        if (!empty($criteria->columns)) {
            foreach ($criteria->columns as $column) {
                if (!isset($query[$column['column']]))
                    $query[$column['column']] = array();

                $k = false;
                switch ($column['operator']) {
                    case '<':
                        $k = '$lt';
                        break;
                    case '<=':
                        $k = '$lte';
                        break;
                    case '>':
                        $k = '$gt';
                        break;
                    case '>=':
                        $k = '$gte';
                        break;
                    case '<>':
                    case '!=':
                    case '!==':
                        $k = '$ne';
                        break;
                    case '==':
                    case '===':
                    default:
                        $query[$column['column']][] = $column['value'];
                        break;
                }

                if ($k !== false) {
                    if (!isset($query[$column['column']][$k]))
                        $query[$column['column']][$k] = array();
                    $query[$column['column']][$k][] = $column['value'];
                }
            }
        }

        if (!empty($criteria->array)) {
            foreach ($criteria->columns as $column) {
                if (!isset($query[$column['column']]))
                    $query[$column['column']] = array();

                if ($column['like'])
                    $k = '$in';
                else
                    $k = '$nin';

                if (!isset($query[$column['column']][$k]))
                    $query[$column['column']][$k] = array();
                $query[$column['column']][$k][] = $column['values'];
            }
        }

        /**
         * Remove empty entries
         */
        $query = array_filter($query);
        /**
         * Recursively flatten query where arrays consist of single values
         */
        array_walk($query, $func=function(&$col, $k)use(&$func) {
            if (is_array($col) && count($col) == 1 && is_int(key($col)))
                $col = array_pop($col);
            if(is_array($col))
                array_walk($col, $func);
        });

        if ($command !== array()) {
            $command['query'] = $query;
            \Yii::trace('Executing command: ' . \CVarDumper::dumpAsString($command), 'ext.activedocument.drivers.mongo.Adapter');
            return $this->_storageInstance->command($command);
        } else {
            \Yii::trace('Executing query: ' . \CVarDumper::dumpAsString($query), 'ext.activedocument.drivers.mongo.Adapter');
            return $this->getContainer($collection)->getContainerInstance()->find($query);
        }
    }

    /**
     * @param string $container Container name
     * @param mixed $key
     * @param mixed $value
     * @return Object
     */
    protected function populateObject($container, $key, $value = null) {
        return new Object($this->getContainer($container), $key, $value, true);
    }

}