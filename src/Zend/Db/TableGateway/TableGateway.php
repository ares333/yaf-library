<?php
namespace Ares333\Yaf\Zend\Db\TableGateway;

use Ares333\Yaf\Zend\Db\Sql\Insert;
use Ares333\Yaf\Zend\Db\Sql\Sql;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway as Base;

class TableGateway extends Base
{

    /**
     * Select
     *
     * @param \Zend\Db\Sql\Where|\Closure|string|array $where
     * @return int
     */
    public function count($where = null)
    {
        if (! $this->isInitialized) {
            $this->initialize();
        }

        $select = $this->sql->select();

        if ($where instanceof \Closure) {
            $where($select);
        } elseif ($where !== null) {
            $select->where($where);
        }

        return $this->countWith($select);
    }

    /**
     *
     * @param Select $select
     *
     * @return int
     */
    function countWith(Select $select = null)
    {
        if (! $this->isInitialized) {
            $this->initialize();
        }
        if (! ($this->sql instanceof Sql)) {
            user_error('not supported with ' . get_class($this->sql), E_USER_ERROR);
        }
        $select = $this->sql->selectCount($select);
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        $row = $result->current();
        return (int) $row['c'];
    }

    /**
     *
     * @param array $set
     *
     * @return int
     */
    function insertIgnore($set)
    {
        if (! $this->isInitialized) {
            $this->initialize();
        }
        $insert = $this->sql->insert();
        if (! ($insert instanceof Insert)) {
            user_error('insertIgnore not supported');
        }
        $insert->values($set)->setIgnore(true);
        return $this->insertWith($insert);
    }

    function insertIgnoreBatch($set)
    {
        return $this->insertBatch($set, null, 'ignore');
    }

    function insertDuplicateBatch($set, $update)
    {
        return $this->insertBatch($set, $update, 'duplicate');
    }

    protected function insertBatch($set, $update, $type)
    {
        if (count($set) == 0) {
            return;
        }
        /**
         *
         * @var \Zend\Db\Adapter\Adapter $adapter
         */
        $adapter = $this->getAdapter();
        $columns = array_keys(current($set));
        if ($type == 'ignore') {
            $ignore = 'IGNORE ';
        } else {
            $ignore = '';
        }
        $sql = 'INSERT ' . $ignore . 'INTO ' . $this->getTable() . '(' . implode($columns, ',') . ') values ';
        $sql .= implode(',', array_pad([], count($set), '(' . implode(',', array_pad([], count($columns), '?')) . ')'));
        if ($type == 'duplicate') {
            $sql .= ' ON DUPLICATE KEY UPDATE ';
            $dup = [];
            foreach ($update as $k => $v) {
                $k = $adapter->getPlatform()->quoteIdentifier($k);
                $v = $adapter->getPlatform()->quoteValue($v);
                $dup[] = "$k = $v";
            }
            $sql .= implode(',', $dup);
        }
        $stmt = $adapter->query($sql, $adapter::QUERY_MODE_PREPARE);
        $values = [];
        foreach ($set as $v) {
            $values = array_merge($values, array_values($v));
        }
        return $stmt->execute($values);
    }

    /**
     *
     * @param array $set
     * @param array $update
     *
     * @return int
     */
    function insertDuplicate($set, $update)
    {
        if (! $this->isInitialized) {
            $this->initialize();
        }
        $insert = $this->sql->insert();
        if (! ($insert instanceof Insert)) {
            user_error('insertDuplicate not supported');
        }
        $insert->values($set)->setDuplicate($update);
        return $this->insertWith($insert);
    }
}