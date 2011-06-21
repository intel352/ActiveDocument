<?php

class ActiveConnection extends CApplicationComponent {

    const PARAM_NULL=0;
    const PARAM_BOOL=1;
    const PARAM_INT=2;
    const PARAM_STR=3;
    const PARAM_ARR=4;
    const PARAM_OBJ=5;

    public $driver;
    /* public $schemaCachingDuration=0;
      public $schemaCachingExclude=array();
      public $schemaCacheID='cache'; */
    /* public $queryCachingDuration=0;
      public $queryCachingDependency;
      public $queryCachingCount=0;
      public $queryCacheID='cache'; */
    public $autoConnect = true;
    /* public $emulatePrepare=false;
      public $enableParamLogging=false;
      public $enableProfiling=false; */
    public $containerPrefix;
    /* public $initSQLs; */
    public $driverMap = array(
        'riak' => array(
            'adapter'=>'RiakActiveAdapter',
            'schema'=>'RiakActiveSchema',
        ),
    );
    private $_attributes = array();
    private $_active = false;
    private $_aco;
    private $_transaction;
    private $_schema;

    public function __construct($driver='', $settings=array()) {
        $this->driver = $driver;
        $this->driverSettings = $settings;
    }

    public function __sleep() {
        $this->close();
        return array_keys(get_object_vars($this));
    }

    public function init() {
        parent::init();
        if ($this->autoConnect)
            $this->setActive(true);
    }

    public function getActive() {
        return $this->_active;
    }

    public function setActive($value) {
        if ($value != $this->_active) {
            if ($value)
                $this->open();
            else
                $this->close();
        }
    }

    /* public function cache($duration, $dependency=null, $queryCount=1)
      {
      $this->queryCachingDuration=$duration;
      $this->queryCachingDependency=$dependency;
      $this->queryCachingCount=$queryCount;
      return $this;
      } */

    protected function open() {
        if ($this->_aco === null) {
            if (empty($this->driver))
                throw new ActiveException(Yii::t('yii', 'ActiveConnection.driver cannot be empty.'));
            try {
                Yii::trace('Opening data storage connection', 'ext.active-document.ActiveConnection');
                $this->_aco = $this->createConnectionInstance();
                $this->initConnection($this->_aco);
                $this->_active = true;
            } catch (ACOException $e) {
                if (YII_DEBUG) {
                    throw new ActiveException(Yii::t('yii', 'ActiveConnection failed to open the data storage connection: {error}', array('{error}' => $e->getMessage())), (int) $e->getCode(), $e->errorInfo);
                } else {
                    Yii::log($e->getMessage(), CLogger::LEVEL_ERROR, 'exception.ActiveException');
                    throw new ActiveException(Yii::t('yii', 'ActiveConnection failed to open the data storage connection.'), (int) $e->getCode(), $e->errorInfo);
                }
            }
        }
    }

    protected function close() {
        Yii::trace('Closing data storage connection', 'ext.active-document.ActiveConnection');
        $this->_aco = null;
        $this->_active = false;
        $this->_schema = null;
    }

    protected function createConnectionInstance() {
        $driver = $this->driverMap[$this->driver]['adapter'];
        return Yii::createComponent(array_merge($this->_attributes,array('class'=>$driver)));
    }

    protected function initConnection($aco) {
        /*$aco->setAttribute(ACO::ATTR_ERRMODE, ACO::ERRMODE_EXCEPTION);
        if ($this->emulatePrepare && constant('ACO::ATTR_EMULATE_PREPARES'))
            $aco->setAttribute(ACO::ATTR_EMULATE_PREPARES, true);
        if ($this->initSQLs !== null) {
            foreach ($this->initSQLs as $sql)
                $aco->exec($sql);
        }*/
    }

    public function getAcoInstance() {
        return $this->_aco;
    }

    public function createCommand($query=null) {
        $this->setActive(true);
        return new CDbCommand($this, $query);
    }

    public function getCurrentTransaction() {
        if ($this->_transaction !== null) {
            if ($this->_transaction->getActive())
                return $this->_transaction;
        }
        return null;
    }

    public function beginTransaction() {
        $this->setActive(true);
        $this->_aco->beginTransaction();
        return $this->_transaction = new CDbTransaction($this);
    }

    public function getSchema() {
        if ($this->_schema !== null)
            return $this->_schema;
        else {
            $driver = $this->getDriverName();
            if (isset($this->driverMap[$driver]))
                return $this->_schema = Yii::createComponent($this->driverMap[$driver], $this);
            else
                throw new ActiveException(Yii::t('yii', 'ActiveConnection does not support reading schema for {driver} database.', array('{driver}' => $driver)));
        }
    }

    public function getCommandBuilder() {
        return $this->getSchema()->getCommandBuilder();
    }

    public function getLastInsertID($sequenceName='') {
        $this->setActive(true);
        return $this->_aco->lastInsertId($sequenceName);
    }

    public function getAcoType($type) {
        static $map = array(
        'NULL' => self::PARAM_NULL,
        'boolean' => self::PARAM_BOOL,
        'integer' => self::PARAM_INT,
        'string' => self::PARAM_STR,
        'array' => self::PARAM_ARR,
        'object' => self::PARAM_OBJ,
        );
        return isset($map[$type]) ? $map[$type] : self::PARAM_STR;
    }

    public function getColumnCase() {
        return $this->getAttribute(ACO::ATTR_CASE);
    }

    public function setColumnCase($value) {
        $this->setAttribute(ACO::ATTR_CASE, $value);
    }

    public function getNullConversion() {
        return $this->getAttribute(ACO::ATTR_ORACLE_NULLS);
    }

    public function setNullConversion($value) {
        $this->setAttribute(ACO::ATTR_ORACLE_NULLS, $value);
    }

    public function getAutoCommit() {
        return $this->getAttribute(ACO::ATTR_AUTOCOMMIT);
    }

    public function setAutoCommit($value) {
        $this->setAttribute(ACO::ATTR_AUTOCOMMIT, $value);
    }

    public function getPersistent() {
        return $this->getAttribute(ACO::ATTR_PERSISTENT);
    }

    public function setPersistent($value) {
        return $this->setAttribute(ACO::ATTR_PERSISTENT, $value);
    }

    public function getDriverName() {
        if (($pos = strpos($this->connectionString, ':')) !== false)
            return strtolower(substr($this->connectionString, 0, $pos));
    }

    public function getClientVersion() {
        return $this->getAttribute(ACO::ATTR_CLIENT_VERSION);
    }

    public function getConnectionStatus() {
        return $this->getAttribute(ACO::ATTR_CONNECTION_STATUS);
    }

    public function getPrefetch() {
        return $this->getAttribute(ACO::ATTR_PREFETCH);
    }

    public function getServerInfo() {
        return $this->getAttribute(ACO::ATTR_SERVER_INFO);
    }

    public function getServerVersion() {
        return $this->getAttribute(ACO::ATTR_SERVER_VERSION);
    }

    public function getTimeout() {
        return $this->getAttribute(ACO::ATTR_TIMEOUT);
    }

    public function getAttribute($name) {
        $this->setActive(true);
        return $this->_aco->getAttribute($name);
    }

    public function setAttribute($name, $value) {
        if ($this->_aco instanceof ACO)
            $this->_aco->setAttribute($name, $value);
        else
            $this->_attributes[$name] = $value;
    }

    public function getAttributes() {
        return $this->_attributes;
    }

    public function setAttributes($values) {
        foreach ($values as $name => $value)
            $this->_attributes[$name] = $value;
    }

    public function getStats() {
        $logger = Yii::getLogger();
        $timings = $logger->getProfilingResults(null, 'ext.active-document.CDbCommand.query');
        $count = count($timings);
        $time = array_sum($timings);
        $timings = $logger->getProfilingResults(null, 'ext.active-document.CDbCommand.execute');
        $count+=count($timings);
        $time+=array_sum($timings);
        return array($count, $time);
    }

}