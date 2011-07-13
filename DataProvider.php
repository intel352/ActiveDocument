<?php

namespace ext\activedocument;

use \CActiveDataProvider;

class DataProvider extends CActiveDataProvider {

    /**
     * @var string the primary Document class name. The {@link getData()} method
     * will return a list of objects of this class.
     */
    public $modelClass;
    /**
     * @var Document the finder instance (eg <code>Post::model()</code>).
     * This property can be set by passing the finder instance as the first parameter
     * to the constructor. For example, <code>Post::model()->published()</code>.
     */
    public $model;
    /**
     * @var string the name of key attribute for {@link modelClass}. If not set,
     * it means the primary key of the corresponding database table will be used.
     */
    public $keyAttribute;
    private $_criteria;
    private $_sort;

    /**
     * Constructor.
     * @param mixed $modelClass the model class (e.g. 'Post') or the model finder instance
     * (e.g. <code>Post::model()</code>, <code>Post::model()->published()</code>).
     * @param array $config configuration (name=>value) to be applied as the initial property values of this class.
     */
    public function __construct($modelClass, $config=array()) {
        if (is_string($modelClass)) {
            $this->modelClass = $modelClass;
            $this->model = Document::model($this->modelClass);
        } else if ($modelClass instanceof Document) {
            $this->modelClass = get_class($modelClass);
            $this->model = $modelClass;
        }
        $this->setId($this->modelClass);
        foreach ($config as $key => $value)
            $this->$key = $value;
    }

    /**
     * Returns the query criteria.
     * @return Criteria the query criteria
     */
    public function getCriteria() {
        if ($this->_criteria === null)
            $this->_criteria = new Criteria;
        return $this->_criteria;
    }

    /**
     * Sets the query criteria.
     * @param mixed $value the query criteria. This can be either a DbCriteria object or an array
     * representing the query criteria.
     */
    public function setCriteria($value) {
        $this->_criteria = $value instanceof Criteria ? $value : new Criteria($value);
    }

    /**
     * Returns the sorting object.
     * @return CSort the sorting object. If this is false, it means the sorting is disabled.
     */
    public function getSort() {
        if ($this->_sort === null) {
            $this->_sort = new Sort;
            if (($id = $this->getId()) != '')
                $this->_sort->sortVar = $id . '_sort';
            $this->_sort->modelClass = $this->modelClass;
        }
        return $this->_sort;
    }

    /**
     * Fetches the data from the persistent data storage.
     * @return array list of data items
     */
    protected function fetchData() {
        $criteria = clone $this->getCriteria();

        if (($pagination = $this->getPagination()) !== false) {
            $pagination->setItemCount($this->getTotalItemCount());
            $pagination->applyLimit($criteria);
        }

        $baseCriteria = $this->model->getCriteria(false);

        if (($sort = $this->getSort()) !== false) {
            // set model criteria so that CSort can use its table alias setting
            if ($baseCriteria !== null) {
                $c = clone $baseCriteria;
                $c->mergeWith($criteria);
                $this->model->setCriteria($c);
            }
            else
                $this->model->setCriteria($criteria);
            $sort->applyOrder($criteria);
        }

        $this->model->setCriteria($baseCriteria !== null ? clone $baseCriteria : null);
        $data = $this->model->findAll($criteria);
        $this->model->setCriteria($baseCriteria);  // restore original criteria
        return $data;
    }

    /**
     * Fetches the data item keys from the persistent data storage.
     * @return array list of data item keys.
     */
    protected function fetchKeys() {
        $keys = array();
        foreach ($this->getData() as $i => $data) {
            $key = $this->keyAttribute === null ? $data->getPrimaryKey() : $data->{$this->keyAttribute};
            $keys[$i] = is_array($key) ? implode(',', $key) : $key;
        }
        return $keys;
    }

    /**
     * Calculates the total number of data items.
     * @return integer the total number of data items.
     */
    protected function calculateTotalItemCount() {
        $baseCriteria = $this->model->getCriteria(false);
        if ($baseCriteria !== null)
            $baseCriteria = clone $baseCriteria;
        $count = $this->model->count($this->getCriteria());
        $this->model->setCriteria($baseCriteria);
        return $count;
    }

}