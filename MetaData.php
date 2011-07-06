<?php

namespace ext\activedocument;
use \Yii, \CComponent;

class MetaData extends CComponent {

    public $attributes = array();
    #public $relations=array();
    public $attributeDefaults = array();
    /**
     * @var \ext\activedocument\Document
     */
    private $_model;
    /**
     * @var \ext\activedocument\Container
     */
    private $_container;

    public function __construct(Document $model) {
        $this->_model = $model;

        if (($container = $model->getContainer()) === null)
            throw new Exception(Yii::t('yii', 'The container "{container}" for document class "{class}" cannot be found in the storage media.', array('{class}' => get_class($model), '{container}' => $model->getContainerName())));
        $this->_container = $container;
        $this->attributes = $this->loadAttributes();

        /*foreach ($this->attributes as $name => $attribute) {
            if ($attribute->defaultValue !== null)
                $this->attributeDefaults[$name] = $attribute->defaultValue;
        }*/

        /* foreach($model->relations() as $name=>$config)
          {
          $this->addRelation($name,$config);
          } */
    }
    
    /**
     * @return \ext\activedocument\Container
     */
    public function getContainer() {
        return $this->_container;
    }
    
    protected function loadAttributes() {
        $attributes = $this->_container->getAttributes();
        if(empty($attributes)) {
            $reflection = new \ReflectionClass(get_class($this->_model));
            $props = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            foreach($props as $prop) {
                /**
                 * Exclude public static properties
                 * @todo Need attr "definition"
                 */
                if(!$prop->isStatic())
                    $attributes[$prop->getName()] = $prop->getName();
            }
        }
        return $attributes;
    }

    /* public function addRelation($name,$config)
      {
      if(isset($config[0],$config[1],$config[2]))  // relation class, AR class, FK
      $this->relations[$name]=new $config[0]($name,$config[1],$config[2],array_slice($config,3));
      else
      throw new ActiveException(Yii::t('yii','Active record "{class}" has an invalid configuration for relation "{relation}". It must specify the relation type, the related active record class and the foreign key.', array('{class}'=>get_class($this->_model),'{relation}'=>$name)));
      }

      public function hasRelation($name)
      {
      return isset($this->relations[$name]);
      }

      public function removeRelation($name)
      {
      unset($this->relations[$name]);
      } */
}