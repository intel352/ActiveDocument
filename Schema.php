<?php

namespace ext\activedocument;
use Yii, CComponent;

abstract class Schema extends CComponent {
    /**
     * @var \ext\activedocument\Connection
     */
    protected $_connection;
    protected $_containers=array();
	protected $_cacheExclude=array();
    
	abstract protected function loadContainer($name);
    
	public function __construct(Connection $conn)
	{
		$this->_connection=$conn;
		foreach($conn->schemaCachingExclude as $name)
			$this->_cacheExclude[$name]=true;
	}

	/**
	 * @return Connection storage connection. The connection is active.
	 */
	public function getConnection()
	{
		return $this->_connection;
	}
    
    public function getContainer($name) {
		if(isset($this->_containers[$name]))
			return $this->_containers[$name];
		else
		{
			if($this->_connection->containerPrefix!==null && strpos($name,'{{')!==false)
				$realName=preg_replace('/\{\{(.*?)\}\}/',$this->_connection->containerPrefix.'$1',$name);
			else
				$realName=$name;

			// temporarily disable query caching
			/*if($this->_connection->queryCachingDuration>0)
			{
				$qcDuration=$this->_connection->queryCachingDuration;
				$this->_connection->queryCachingDuration=0;
			}*/

			if(!isset($this->_cacheExclude[$name]) && ($duration=$this->_connection->schemaCachingDuration)>0 && $this->_connection->schemaCacheID!==false && ($cache=Yii::app()->getComponent($this->_connection->schemaCacheID))!==null)
			{
				$key='activedocument.storageschema.'.$this->_connection->driver.'.'.$this->_connection->containerPrefix.'.'.$name;
				if(($container=$cache->get($key))===false)
				{
					$container=$this->loadContainer($realName);
					if($container!==null)
						$cache->set($key,$container,$duration);
				}
				$this->_containers[$name]=$container;
			}
			else
				$this->_containers[$name]=$container=$this->loadContainer($realName);

			/*if(isset($qcDuration))  // re-enable query caching
				$this->_connection->queryCachingDuration=$qcDuration;*/

			return $container;
		}
    }
}