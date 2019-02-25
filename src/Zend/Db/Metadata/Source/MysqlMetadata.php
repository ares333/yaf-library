<?php
namespace Ares333\Yaf\Zend\Db\Metadata\Source;

use Zend\Db\Metadata\Source\MysqlMetadata as Base;
use Zend\Db\Metadata\Object\ConstraintObject;
use Ares333\Yaf\Zend\Db\Metadata\Object\ColumnObject;
use Zend\Db\Adapter\Adapter;

class MysqlMetadata extends Base
{

    protected $columnObjectMethodMap;

    protected $dataConstraintsReferenced;

    /**
     *
     * @return string
     */
    function getDefaultSchema()
    {
        return $this->defaultSchema;
    }

    /**
     *
     * @param string $table
     * @param string $schema
     *
     * @return ConstraintObject[]
     */
    function getConstraintsReferenced($table, $schema = null)
    {
        if (! isset($schema)) {
            $schema = $this->defaultSchema;
        }
        if (! isset($this->dataConstraintsReferenced[$schema][$table])) {
            $p = $this->adapter->getPlatform();
            $sql = 'select CONSTRAINT_NAME,TABLE_SCHEMA,TABLE_NAME from';
            $sql .= ' INFORMATION_SCHEMA.KEY_COLUMN_USAGE where';
            $sql .= ' REFERENCED_TABLE_NAME=' . $p->quoteTrustedValue($table) . ' and';
            $sql .= ' REFERENCED_TABLE_SCHEMA=' . $p->quoteTrustedValue($schema) . ' and';
            $sql .= ' TABLE_NAME is not null';
            $sql .= ' order by ORDINAL_POSITION,CONSTRAINT_NAME';
            $this->dataConstraintsReferenced[$schema][$table] = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE)->toArray();
        }
        $list = array();
        foreach ($this->dataConstraintsReferenced[$schema][$table] as $v) {
            $list[] = $this->getConstraint($v['CONSTRAINT_NAME'], $v['TABLE_NAME'], $v['TABLE_SCHEMA']);
        }
        return $list;
    }

    /**
     *
     * @param string $table
     * @param string $schema
     *
     * @return ConstraintObject
     */
    function getConstraintPrimaryKey($table, $schema = null)
    {
        $list = $this->getConstraints($table, $schema);
        foreach ($list as $v) {
            if ($v->isPrimaryKey()) {
                return $v;
            }
        }
    }

    /**
     *
     * @param string $table
     * @param string $schema
     *
     * @return ConstraintObject[]
     */
    function getConstraintsUnique($table, $schema = null)
    {
        $list = $this->getConstraints($table, $schema);
        $res = array();
        foreach ($list as $v) {
            if ($v->isUnique()) {
                $res[] = $v;
            }
        }
        return $res;
    }

    /**
     *
     * @param string $table
     * @param string $schema
     *
     * @return ConstraintObject[]
     */
    function getConstraintsFk($table, $schema = null)
    {
        $list = $this->getConstraints($table, $schema);
        $res = array();
        foreach ($list as $v) {
            if ($v->isForeignKey()) {
                $res[] = $v;
            }
        }
        return $res;
    }

    /**
     *
     * @param string $table
     * @param string $schema
     *
     * @return ColumnObject[]
     */
    function getColumnsAutoIncrement($table, $schema = null)
    {
        $list = [];
        foreach ($this->getColumns($table, $schema) as $v) {
            if ($v->isAutoIncrement()) {
                $list[] = $v;
            }
        }
        return $list;
    }

    /**
     *
     * {@inheritdoc}
     * @see \Zend\Db\Metadata\Source\AbstractSource::getColumns()
     * @return ColumnObject[]
     */
    function getColumns($table, $schema = null)
    {
        return parent::getColumns($table, $schema);
    }

    /**
     *
     * {@inheritdoc}
     * @see \Zend\Db\Metadata\Source\AbstractSource::getColumn()
     * @return ColumnObject
     */
    function getColumn($columnName, $table, $schema = null)
    {
        $column = parent::getColumn($columnName, $table, $schema);
        if (! isset($this->columnObjectMethodMap)) {
            foreach (get_class_methods(\Zend\Db\Metadata\Object\ColumnObject::class) as $v) {
                if (0 === strpos($v, 'get') && ! in_array($v,
                    [
                        'getSchemaName',
                        'getTableName',
                        'getName',
                        'getErrata'
                    ])) {
                    $this->columnObjectMethodMap[$v] = 'set' . substr($v, 3);
                }
            }
        }
        $columnNew = new ColumnObject($column->getName(), $column->getTableName(), $column->getSchemaName());
        foreach ($this->columnObjectMethodMap as $k => $v) {
            $columnNew->{$v}($column->{$k}());
        }
        $columnNew->setIsVirtual($this->data['columns'][$schema][$table][$columnName]['is_virtual']);
        $columnNew->setIsAutoIncrement($this->data['columns'][$schema][$table][$columnName]['is_auto_increment']);
        return $columnNew;
    }

    /**
     * add is_virtual,is_auto_increment to column data
     *
     * {@inheritdoc}
     * @see \Zend\Db\Metadata\Source\MysqlMetadata::loadColumnData()
     */
    protected function loadColumnData($table, $schema)
    {
        if (isset($this->data['columns'][$schema][$table])) {
            return;
        }
        parent::loadColumnData($table, $schema);
        $p = $this->adapter->getPlatform();
        $sql = 'SELECT ' . $p->quoteIdentifierChain('COLUMN_NAME') . ',INSTR(' . $p->quoteIdentifierChain('EXTRA') . ',' .
            $p->quoteTrustedValue('VIRTUAL GENERATED') . ') as IS_VIRTUAL,INSTR(' . $p->quoteIdentifierChain('EXTRA') .
            ',' . $p->quoteTrustedValue('auto_increment') . ') as IS_AUTO_INCREMENT FROM ' . $p->quoteIdentifierChain(
                [
                    'INFORMATION_SCHEMA',
                    'COLUMNS'
                ]) . ' WHERE ' . $p->quoteIdentifierChain([
                'TABLE_NAME'
            ]) . ' = ' . $p->quoteTrustedValue($table);
        if ($schema != self::DEFAULT_SCHEMA) {
            $sql .= ' AND ' . $p->quoteIdentifierChain([
                'TABLE_SCHEMA'
            ]) . ' = ' . $p->quoteTrustedValue($schema);
        } else {
            $sql .= ' AND ' . $p->quoteIdentifierChain([
                'TABLE_SCHEMA'
            ]) . ' != \'INFORMATION_SCHEMA\'';
        }
        $sql .= ' AND ( EXTRA like ' . $p->quoteTrustedValue('%VIRTUAL GENERATED%') . ' OR EXTRA like ' .
            $p->quoteTrustedValue('%auto_increment%') . ')';
        $results = [];
        foreach ($this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE)->toArray() as $v) {
            $results[$v['COLUMN_NAME']] = $v;
        }
        foreach ($this->data['columns'][$schema][$table] as $k => $v) {
            $v['is_virtual'] = isset($results[$k]['IS_VIRTUAL']) && (int) $results[$k]['IS_VIRTUAL'] > 0;
            $v['is_auto_increment'] = isset($results[$k]['IS_AUTO_INCREMENT']) &&
                (int) $results[$k]['IS_AUTO_INCREMENT'] > 0;
            $this->data['columns'][$schema][$table][$k] = $v;
        }
    }
}