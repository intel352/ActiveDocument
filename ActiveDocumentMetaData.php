<?php

class ActiveDocumentMetaData {

    public $containerMeta;
    public $attributes;
    #public $relations=array();
    public $attributeDefaults = array();
    private $_model;

    public function __construct(ActiveDocument $model) {
        $this->_model = $model;

        $containerName = $model->containerName();
        if (($container = $model->getConnection()->getSchema()->getTable($containerName)) === null)
            throw new ActiveException(Yii::t('yii', 'The container "{container}" for active document class "{class}" cannot be found in the storage media.', array('{class}' => get_class($model), '{container}' => $containerName)));
        if ($container->primaryKey === null)
            $container->primaryKey = $model->primaryKey();
        $this->containerMeta = $container;
        $this->attributes = $container->attributes;

        foreach ($container->attributes as $name => $attribute) {
            if (!$attribute->isPrimaryKey && $attribute->defaultValue !== null)
                $this->attributeDefaults[$name] = $attribute->defaultValue;
        }

        /* foreach($model->relations() as $name=>$config)
          {
          $this->addRelation($name,$config);
          } */
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