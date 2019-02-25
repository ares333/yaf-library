<?php
namespace Ares333\Yaf\Zend\Cache\Pattern;

use Zend\Db\Adapter\Adapter;
use Zend\Cache\Storage\StorageInterface;
use Ares333\Yaf\Zend\Db\Metadata\Source\MysqlMetadata as Base;
use Zend\Cache\Pattern\ObjectCache;
use Zend\Cache\Pattern\PatternOptions;

/**
 * Make only one copy exists in cache for per schema.
 *
 * @method string getDefaultSchema()
 * @method \Zend\Db\Metadata\Object\ConstraintObject[] getConstraintsReferenced($tableName, $schema = null)
 * @method \Zend\Db\Metadata\Object\ConstraintObject getConstraintPrimaryKey($tableName, $schema = null)
 * @method \Zend\Db\Metadata\Object\ConstraintObject[] getConstraintsUnique($tableName, $schema = null)
 * @method \Zend\Db\Metadata\Object\ConstraintObject[] getConstraintsFk($tableName, $schema = null)
 * @method \Ares333\Yaf\Zend\Db\Metadata\Object\ColumnObject[] getColumnsAutoIncrement($tableName, $schema = null)
 * @method string[] getSchemas()
 * @method string[] getTableNames($schema = null, $includeViews = false)
 * @method \Zend\Db\Metadata\Object\TableObject[] getTables($schema = null, $includeViews = false)
 * @method \Zend\Db\Metadata\Object\TableObject getTable($tableName, $schema = null)
 * @method string[] getViewNames($schema = null)
 * @method \Zend\Db\Metadata\Object\ViewObject[] getViews($schema = null)
 * @method \Zend\Db\Metadata\Object\ViewObject getView($viewName, $schema = null)
 * @method string[] getColumnNames($table, $schema = null)
 * @method \Ares333\Yaf\Zend\Db\Metadata\Object\ColumnObject[] getColumns($table, $schema = null)
 * @method \Ares333\Yaf\Zend\Db\Metadata\Object\ColumnObject getColumn($columnName, $table, $schema = null)
 * @method \Zend\Db\Metadata\Object\ConstraintObject[] getConstraints($table, $schema = null)
 * @method \Zend\Db\Metadata\Object\ConstraintObject getConstraint($constraintName, $table, $schema = null)
 * @method \Zend\Db\Metadata\Object\ConstraintKeyObject[] getConstraintKeys($constraint, $table, $schema = null)
 * @method string[] getTriggerNames($schema = null)
 * @method \Zend\Db\Metadata\Object\TriggerObject[] getTriggers($schema = null)
 * @method \Zend\Db\Metadata\Object\TriggerObject getTrigger($triggerName, $schema = null)
 *        
 */
class MysqlMetadata extends ObjectCache
{

    protected $defaultSchema;

    protected $paramsKeyFactor;

    protected $localCache = [];

    /**
     *
     * @param Adapter $adapter
     * @param StorageInterface $storage
     */
    function __construct(Adapter $adapter, StorageInterface $storage)
    {
        $object = new Base($adapter);
        $this->defaultSchema = $object->getDefaultSchema();
        $dbParams = $adapter->getDriver()
            ->getConnection()
            ->getConnectionParameters();
        $port = '';
        if (isset($dbParams['port'])) {
            $port = $dbParams['port'];
        }
        $this->paramsKeyFactor = [
            'hostname' => $dbParams['hostname'],
            'port' => $port
        ];
        $options = [];
        $options['cache_output'] = false;
        $options['object'] = $object;
        $options['storage'] = $storage;
        $options = new PatternOptions($options);
        $this->setOptions($options);
    }

    protected function generateArgumentsKey($args)
    {
        $args[] = $this->paramsKeyFactor;
        return parent::generateArgumentsKey($args);
    }

    /**
     *
     * {@inheritdoc}
     */
    function call($method, array $args = [])
    {
        $map = [];
        foreach ([
            'getColumn' => 2,
            'getColumnNames' => 1,
            'getColumns' => 1,
            'getConstraint' => 2,
            'getConstraintKeys' => 2,
            'getConstraints' => 1,
            'getConstraintPrimaryKey' => 1,
            'getConstraintsUnique' => 1,
            'getConstraintsFk' => 1,
            'getConstraintsReferenced' => 1,
            'getColumnsAutoIncrement' => 1,
            'getTable' => 1,
            'getTableNames' => 0,
            'getTables' => 0,
            'getTrigger' => 1,
            'getTriggerNames' => 0,
            'getTriggers' => 0,
            'getView' => 1,
            'getViewNames' => 0,
            'getViews' => 0
        ] as $k => $v) {
            $map[strtolower($k)] = $v;
        }
        $key = strtolower($method);
        if (array_key_exists($key, $map)) {
            $pos = $map[$key];
            // add default schema
            if (! isset($args[$pos])) {
                for ($i = 0; $i < $pos; $i ++) {
                    if (! array_key_exists($i, $args)) {
                        $args[$i] = null;
                    }
                }
                $args[$pos] = $this->defaultSchema;
            }
        }
        $key = $this->generateKey($method, $args);
        if (! array_key_exists($key, $this->localCache)) {
            $this->localCache[$key] = parent::call($method, $args);
        }
        return $this->localCache[$key];
    }
}