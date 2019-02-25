<?php
namespace Ares333\Yaf\Zend\Db\TableGateway\Feature\Mysql;

use Zend\Db\Sql\Insert;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Sql\Update;
use Ares333\Yaf\Zend\Db\Sql\Sql;

class SaveChildFeature extends AbstractFeature
{

    protected static $depth = 0;

    protected $childData = array();

    protected $lastInsert;

    public function preInsert(Insert $insert)
    {
        $child = $this->metadata->getConstraintsReferenced($this->tableGateway->getTable());
        foreach ($child as $v) {
            $key = $this->getChildColumnName($v);
            if (in_array($key, $insert->getRawState('columns'))) {
                $this->childData[$key] = $insert->__get($key);
                $insert->__unset($key);
            }
        }
        $this->lastInsert = $insert;
        $this->startTransaction();
    }

    public function postInsert(StatementInterface $statement, ResultInterface $result)
    {
        if ($result->getAffectedRows() > 0) {
            $tableGatewayClass = get_class($this->tableGateway);
            $insertValue = $this->lastInsert->getRawState();
            $insertValue = array_combine($insertValue['columns'], $insertValue['values']);
            $refd = $this->metadata->getConstraintsReferenced($this->tableGateway->getTable());
            $lastInsertValue = $this->tableGateway->getLastInsertValue();
            foreach ($refd as $v) {
                $vTable = new $tableGatewayClass($v->getTableName(), $this->tableGateway->getAdapter(),
                    new self($this->metadata));
                $key = $this->getChildColumnName($v);
                if (! isset($this->childData[$key])) {
                    continue;
                }
                foreach ($this->childData[$key] as $v1) {
                    foreach ($v->getReferencedColumns() as $k2 => $v2) {
                        if (isset($insertValue[$v2])) {
                            $vColumnValue = $insertValue[$v2];
                        } else {
                            if (in_array($v2,
                                $this->metadata->getConstraintPrimaryKey($this->tableGateway->getTable())
                                    ->getColumns()) && false !== $lastInsertValue && null !== $lastInsertValue) {
                                $vColumnValue = $lastInsertValue;
                            }
                        }
                        $v1[$v->getColumns()[$k2]] = $vColumnValue;
                    }
                    static::$depth ++;
                    $vTable->insert($v1);
                    static::$depth --;
                }
            }
        }
        $this->endTransaction();
        $this->childData = array();
        $this->lastInsert = null;
    }

    public function preUpdate(Update $update)
    {
        $state = $update->getRawState();
        $set = $state['set'];
        $hasChild = false;
        foreach (array_keys($set) as $v) {
            if (false !== strpos($v, $this->getChildSep())) {
                $hasChild = true;
            }
        }
        if (! $hasChild) {
            return;
        }
        // check row number
        $select = $this->tableGateway->getSql()->select();
        $select->where($state['where']);
        foreach ($state['joins'] as $v) {
            call_user_func_array([
                $select,
                'join'
            ], $v);
        }
        $adapter = $this->tableGateway->getAdapter();
        $sql = new Sql($adapter);
        $count = $this->tableGateway->selectWith($sql->selectCount($select))
            ->current();
        if (! isset($count) || 1 !== current($count)) {
            user_error(get_class($this) . ' can not used for batch update', E_USER_ERROR);
        }
        $child = $this->metadata->getConstraintsReferenced($this->tableGateway->getTable());
        $tableGatewayClass = get_class($this->tableGateway);
        foreach ($child as $v) {
            $key = $this->getChildColumnName($v);
            if (array_key_exists($key, $set)) {
                $vValues = $set[$key];
                unset($set[$key]);
                $select->columns(array_combine($v->getColumns(), $v->getReferencedColumns()));
                $sql = $select->getSqlString($adapter->getPlatform());
                $vValue = $adapter->query($sql, $adapter::QUERY_MODE_EXECUTE)->current();
                if (! isset($vValue)) {
                    continue;
                }
                settype($vValue, 'array');
                /**
                 *
                 * @var TableGateway $vTableGateway
                 */
                $vTableGateway = new $tableGatewayClass($v->getTableName(), $adapter);
                $vSaveChildFeature = $vTableGateway->getFeatureSet()->getFeatureByClassName(self::class);
                if (false == $vSaveChildFeature) {
                    $vSaveChildFeature = new self($this->metadata);
                    $vTableGateway->getFeatureSet()->addFeature($vSaveChildFeature);
                }
                $vTableGateway->delete($vValue);
                if (is_array($vValues)) {
                    foreach ($vValues as $v1) {
                        $v1 = array_merge($v1, $vValue);
                        static::$depth ++;
                        $vTableGateway->insert($v1);
                        static::$depth --;
                    }
                }
                $this->childData[$v->getTableName()] = $vValues;
            }
        }
        $update->set($set);
    }
}