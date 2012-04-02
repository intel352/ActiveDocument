# ActiveDocument: Document ORM for Yii #
ActiveDocument is an ORM for Yii, providing Active Record functionality for document-based storage engines.
This library is functionally similar to Yii's own SQL-based Active Record class, CActiveRecord.

## Current Storage Drivers ##
* Memory - temporary storage using PHP memory. Good for nested documents, and testing.
* Riak - via the Yii ext, Riiak (currently packaged in with ActiveDocument)
* MongoDB - via [PHP PECL extension](http://php.net/manual/en/mongo.installation.php)

## Planned Storage Drivers ##
* CouchDB
* Redis
* SQL - may possibly add support for SQL, if only as a CActiveRecord wrapper (to enable seamlessly changing storage driver)

## Requirements ##
* Yii 1.1+ (Yii 1.1.7+ recommended, lower releases should be compatible, but are untested)
* PHP 5.3+ (ActiveDocument library is namespaced, which is a PHP 5.3 feature)

## Installation ##
Clone this repository to your protected/extensions/ directory, to use the latest available code

    hg clone https://bitbucket.org/intel352/activedocument

## Quick start ##
This quick example uses the Memory driver

###protected/config/main.php:###

    // ...
    'components' => array(
        // ...
        'conn'=>array(
            'class' => '\ext\activedocument\Connection',
            'driver' => 'memory',
        ),
        // ...
    ),
    // ...


###protected/models/Config.php:###

    <?php

    /**
     * NOTE:
     * An important difference in ActiveDocument from CActiveRecord, is that you are no longer required to specify a
     * model() method within every model class that you create. ActiveDocument makes use of a PHP 5.3 feature, Late
     * Static Binding, to achieve the same purpose as CActiveRecord::model() with less redundancy.
     *
     * @property ConfigSetting[] $options Not needed for ActiveDocument, this exists as helper phpdoc for IDE users
     */
    class Config extends \ext\activedocument\Document {
        /**
         * This phpdoc notation is detected by ActiveDocument. Only partially supported at this time.
         * @var string
         */
        public $name;

        /**
         * Relations function similar to CActiveRecord, see below for an example.
         *
         * @return array
         */
        public function relations() {
            return array(
                /**
                 * For CActiveRecord, a relation requires 3 values specified (type, model, foreign key).
                 * ActiveDocument instead requires 2 values (type, model). A foreign key is no longer needed, as related
                 * documents have their primary key stored into the parent document.
                 *
                 * If you specify the named parameter, 'nested', as true, the related document[s] will have copies
                 * stored directly into this model. So in this specific example, if 4 ConfigSetting instances were saved
                 * to the 'options' relation, a copy of each would be stored within the Config instance in the data
                 * engine.
                 */
                'options'=>array(self::HAS_MANY, 'ConfigSetting', 'nested'=>true),
            );
        }

    }


###protected/models/ConfigSetting.php:###

    <?php

    /**
     * ConfigSetting
     */
    class ConfigSetting extends \ext\activedocument\Document {
        /**
         * @var string Setting name
         */
        public $name;
        /**
         * @var mixed
         */
        public $value;

        /**
         * If you do not specify which attribute carries your primary key value, then the default of '_pk' will be used.
         * If the default '_pk' is used, a primary key may be generated for you, based on the behavior of your selected
         * driver and storage engine.
         *
         * You can specify a composite key, by returning an array of attribute names.
         *
         * @return string|array
         */
        public function primaryKey() {
            return 'name';
        }

    }


###protected/controllers/TestController.php:###

    <?php

    class TestController extends Controller {

        public function actionIndex() {
            $config = new Config;
            $config->name = 'Test Config';

            $cs = new ConfigSetting;
            $cs->name = 'config1';
            $cs->value = 'config1value';

            $cs2 = new ConfigSetting;
            $cs2->name = 'config2';
            $cs2->value = 'config2value';

            /**
             * Helper method to add one or more related documents.
             * Other ways to add related documents:
             * - $config->addRelated('options', array($cs, $cs2));
             * - $config->options = array($cs, $cs2);
             */
            $config->addRelated('options', $cs);
            $config->addRelated('options', $cs2);

            $config->save();

            /**
             * $config->primaryKey triggers magic call to Document::getPrimaryKey()
             */
            $pk = $config->primaryKey;

            /**
             * Perform a fresh lookup of the new config instance
             */
            $config = Config::model()->findByPk($pk);

            /**
             * Loop and print the related values
             */
            foreach($config->options as $option) {
                echo 'Option "'.$option->name.'": "'.$option->value.'"<br />';
            }
        }

    }
