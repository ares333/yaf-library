<?php
namespace Ares333\Yaf\Zend\Db\TableGateway\Feature\Mysql;

use Zend\Db\Sql\Select;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Sql\Predicate\PredicateSet;
use PHPSQLParser\utils\ExpressionType;
use PHPSQLParser\Options;
use PHPSQLParser\processors\DefaultProcessor;
use PHPSQLParser\processors\SelectExpressionProcessor;
use PHPSQLParser\processors\ExpressionListProcessor;
use PHPSQLParser\processors\HavingProcessor;
use PHPSQLParser\processors\WhereProcessor;
use Zend\Db\Sql\Expression;
use PHPSQLParser\builders\HavingBuilder;
use PHPSQLParser\processors\OrderByProcessor;
use PHPSQLParser\builders\OrderByBuilder;
use Zend\Db\ResultSet\ResultSet;
use PHPSQLParser\processors\AbstractProcessor;
use Zend\Db\Metadata\Object\ConstraintObject;
use Zend\Db\Sql\Predicate\Operator;

class SelectFeature extends AbstractFeature
{

    protected $tableColumnSep = ' ';

    protected $sqlProcessor = array();

    protected $exclude = array();

    protected $resultFilter;

    protected $selectColumnFilter;

    protected $selectFilter;

    protected $child = array();

    protected $childUser = array();

    protected $childLimit = 32;

    protected $childSelectFilter = array();

    protected static $childDepth = 0;

    protected $enabled = false;

    /**
     *
     * @param Select $select
     */
    function preSelect($select)
    {
        if ($this->enabled) {
            $this->preSelectParent($select);
            $this->preSelectChild($select);
        }
    }

    /**
     *
     * @param Select $select
     */
    protected function preSelectParent($select)
    {
        $tableName = $select->getRawState($select::TABLE);
        $metadataTable = $this->metadata->getTable($tableName);
        // origin column
        $cols = array();
        foreach ($metadataTable->getColumns() as $v) {
            $cols[$v->getName()] = $v->getName();
        }
        // dynamic column
        if (isset($this->selectColumnFilter)) {
            $cols = array_merge($cols, call_user_func($this->selectColumnFilter, $tableName));
        }
        // manual column
        $cols = array_merge($cols, $select->getRawState($select::COLUMNS));
        $columns = array();
        foreach ($cols as $k => $v) {
            if ($v !== $select::SQL_STAR) {
                $kNew = $tableName . $this->tableColumnSep;
                if (is_int($k)) {
                    if ($v instanceof Expression) {
                        $kNew .= $v->getExpression();
                    } else {
                        $kNew .= $v;
                    }
                } else {
                    $kNew .= $k;
                }
                $columns[$kNew] = $v;
            }
        }
        $select->columns($columns);
        // where
        $identifierSeparator = $this->getIdentifierSeparator();
        foreach ($select->where->getPredicates() as $v) {
            /**
             *
             * @var \Zend\Db\Sql\Predicate\Operator $vOperator
             */
            $vOperator = $v[1];
            if (! $vOperator instanceof Operator) {
                continue;
            }
            if ($vOperator->getLeftType() == $vOperator::TYPE_IDENTIFIER) {
                $vLeft = $vOperator->getLeft();
                if (false === strpos($vLeft, $identifierSeparator)) {
                    $vOperator->setLeft($tableName . $identifierSeparator . $vLeft);
                }
            }
        }
        $refCounter = array(
            $tableName => 0
        );
        $functionRefFindInfinite = null;
        $functionRefFindInfinite = function ($node, $nodePre) use (&$functionRefFindInfinite) {
            if ($node->getSchemaName() !== $nodePre->getSchemaName() ||
                $node->getTableName() !== $nodePre->getTableName() || $node->getColumns() !== $nodePre->getColumns()) {
                if (property_exists($nodePre, 'prev')) {
                    return call_user_func($functionRefFindInfinite, $node, $nodePre->prev);
                } else {
                    return false;
                }
            }
            return true;
        };
        $schema = $this->metadata->getDefaultSchema();
        $refList = $this->getFk($tableName, $schema);
        while (! empty($refList)) {
            $v = array_shift($refList);
            $vTableNameAlias = $vTableName = $v->getTableName();
            $vTableNameRefAlias = $vTableNameRef = $v->getReferencedTableName();
            $vColumnsRef = $v->getReferencedColumns();
            $vSchemaRef = $v->getReferencedTableSchema();
            if (in_array(array(
                $vSchemaRef,
                $vTableNameRef,
                $vColumnsRef
            ), $this->exclude)) {
                continue;
            }
            // table current alias
            if (isset($v->prev)) {
                $vTableNameAlias = $v->prev->refAlias;
            }
            // Same table can be joined multiple times.
            // Database will do full join if table alias is not setted.
            if (array_key_exists($vTableNameRef, $refCounter)) {
                $refCounter[$vTableNameRef] ++;
                $vTableNameRefAlias .= $this->tableAliasSep . $refCounter[$vTableNameRef];
            } else {
                $refCounter[$vTableNameRef] = 0;
            }
            $vColumnPrefix = isset($v->prev) ? $v->prev->columnPrefix : $vTableName;
            $vColumnPrefix .= $this->tableColumnSep . implode($this->columnSep, $v->getColumns());
            $vMeta = $this->metadata->getTable($vTableNameRef, $vSchemaRef);
            $vColumns = array();
            $vJoinTablePrefix = '';
            $vColumnDbPrefix = '';
            if ($schema !== $vSchemaRef) {
                $vColumnDbPrefix = $vSchemaRef . $this->dbSep;
                $vJoinTablePrefix = '`' . $vSchemaRef . '`.';
            }
            $vCols = array();
            foreach ($vMeta->getColumns() as $v1) {
                $vCols[$v1->getName()] = $v1->getName();
            }
            if (isset($this->selectColumnFilter)) {
                $vCols = array_merge($vCols, call_user_func($this->selectColumnFilter, $vTableNameRef));
            }
            $vColumnPrefix .= $this->parentSep . $vColumnDbPrefix . $vTableNameRef;
            foreach ($vCols as $k1 => $v1) {
                $vColumns[$vColumnPrefix . $this->tableColumnSep . $k1] = $v1;
            }
            $joinCondition = array();
            foreach ($v->getColumns() as $k1 => $v1) {
                $joinCondition[] = '`' . $vTableNameAlias . '`.`' . $v1 . '`=`' . $vTableNameRefAlias . '`.`' .
                    $v->getReferencedColumns()[$k1] . '`';
            }
            $joinCondition = new Expression(implode(' ' . PredicateSet::OP_AND . ' ', $joinCondition));
            $select->join(
                array(
                    $vTableNameRefAlias => new Expression($vJoinTablePrefix . '`' . $vTableNameRef . '`')
                ), $joinCondition, $vColumns, $select::JOIN_LEFT);
            $refFollow = $this->getFk($vTableNameRef, $vSchemaRef);
            $v->refAlias = $vTableNameRefAlias;
            $v->columnPrefix = $vColumnPrefix;
            foreach ($refFollow as $k1 => $v1) {
                if (! call_user_func($functionRefFindInfinite, $v1, $v)) {
                    $v1->prev = $v;
                    $refFollow[$k1] = $v1;
                } else {
                    unset($refFollow[$k1]);
                }
            }
            $refList = array_merge($refList, $refFollow);
        }
        if (isset($this->selectFilter)) {
            call_user_func($this->selectFilter, $select);
        }
        // group
        $group = $select->getRawState($select::GROUP);
        if (isset($group)) {
            if ($group !== array(
                null
            )) {
                user_error('group is invalid', E_USER_ERROR);
            }
            $groupColsOri = $select->getRawState($select::COLUMNS);
            $joins = $select->getRawState($select::JOINS);
            foreach ($joins as $v) {
                $groupColsOri = array_merge($groupColsOri, $v['columns']);
            }
            // find aggregation column
            $functionFindAggregation = null;
            $functionFindAggregation = function ($list) use (&$functionFindAggregation) {
                foreach ($list as $v) {
                    if ($v['expr_type'] == ExpressionType::AGGREGATE_FUNCTION) {
                        return true;
                    }
                    if (! empty($v['sub_tree'])) {
                        return call_user_func($functionFindAggregation, $v['sub_tree']);
                    }
                }
                return false;
            };
            $sqlParseGroup = $this->getSqlProcessor('select');
            $groupCols = array();
            foreach ($groupColsOri as $k => $v) {
                if (! ($v instanceof Expression) || ! call_user_func($functionFindAggregation,
                    array(
                        $sqlParseGroup->process($v->getExpression())
                    ))) {
                    $groupCols[] = new Expression('`' . $k . '`');
                }
            }
            $select->reset($select::GROUP);
            $select->group($groupCols);
        }
        $functionSqlFixColumn = null;
        $functionSqlFixColumn = function ($list, $type) use (&$functionSqlFixColumn, $tableName) {
            foreach ($list as $k => $v) {
                $isDoProcess = false;
                if ($v['expr_type'] == ExpressionType::COLREF) {
                    $isDoProcess = true;
                } elseif ($v['expr_type'] == ExpressionType::AGGREGATE_FUNCTION) {
                    if (false === $v['sub_tree']) {
                        $v['expr_type'] = ExpressionType::COLREF;
                        $v['no_quotes'] = array(
                            'parts' => array(
                                $v['base_expr']
                            )
                        );
                        $isDoProcess = true;
                    }
                }
                if ($isDoProcess) {
                    $count = count($v['no_quotes']['parts']);
                    $column = $v['no_quotes']['parts'][$count - 1];
                    if (1 == $count) {
                        $v['base_expr'] = '`' . $tableName . $this->tableColumnSep . $column . '`';
                    }
                }
                if (! empty($v['sub_tree'])) {
                    $v['sub_tree'] = call_user_func($functionSqlFixColumn, $v['sub_tree'], $type);
                }
                $list[$k] = $v;
            }
            return $list;
        };
        // having
        $having = $select->getRawState($select::HAVING);
        if ($having->count() > 0) {
            $havingArr = array();
            foreach ($having->getExpressionData() as $v) {
                if (! is_array($v)) { // AND,OR
                    $vNew = $v;
                } else {
                    if ([] == $v[1]) {
                        $vNew = '(' . trim($v[0]) . ')';
                    } else {
                        $vNew = $v[0];
                        foreach ($v[1] as $v1) {
                            $vNew = sprintf($vNew, $v1);
                        }
                    }
                }
                $havingArr[] = $vNew;
            }
            $sqlParseHaving = $this->getSqlProcessor('having');
            $sqlParseHaving = $sqlParseHaving->process($sqlParseHaving->splitSQLIntoTokens(implode(' ', $havingArr)));
            $sqlParseHaving = call_user_func($functionSqlFixColumn, $sqlParseHaving, 'having');
            $sqlParseHaving = substr((new HavingBuilder())->build($sqlParseHaving), 7);
            $select->reset($select::HAVING);
            $select->having($sqlParseHaving);
        }
        // order
        $order = $select->getRawState($select::ORDER);
        if (! empty($order)) {
            $select->reset($select::ORDER);
            foreach ($order as $v) {
                $token = $this->getSqlProcessor('default')->splitSQLIntoTokens($v);
                $sqlParseOrder = new OrderByProcessor(new Options(array()));
                $sqlParseOrder = $sqlParseOrder->process($token);
                $sqlParseOrder = call_user_func($functionSqlFixColumn, $sqlParseOrder, 'order');
                $sqlParseOrder = substr((new OrderByBuilder())->build($sqlParseOrder), 9);
                $sqlParseOrder = new Expression($sqlParseOrder);
                $select->order($sqlParseOrder);
            }
        }
    }

    /**
     *
     * @param string $tableName
     * @param string $schemaName
     * @return array
     */
    protected function getFk($tableName, $schemaName)
    {
        $fk = $this->metadata->getConstraintsFk($tableName, $schemaName);
        foreach ($this->virtualFk as $v) {
            if ($v->getSchemaName() === $schemaName && $v->getTableName() === $tableName) {
                $fk[] = $v;
            }
        }
        foreach ($fk as $k => $v) {
            $fk[$k] = clone $v;
        }
        return $fk;
    }

    /**
     *
     * @param array $exclude
     * @return self
     */
    function setExclude($exclude)
    {
        foreach ($exclude as $k => $v) {
            if (! isset($v[0])) {
                $v[0] = $this->metadata->getDefaultSchema();
            }
            if (is_string($v[2])) {
                $v[2] = array(
                    $v[2]
                );
            }
            $exclude[$k] = $v;
        }
        $this->exclude = $exclude;
        return $this;
    }

    /**
     *
     * @return array
     */
    function getExclude()
    {
        return $this->exclude;
    }

    /**
     *
     * @param StatementInterface $statement
     * @param ResultInterface $result
     * @param ResultSet $resultSet
     */
    function postSelect(StatementInterface $statement, ResultInterface $result, ResultSet $resultSet)
    {
        if ($this->enabled) {
            $this->postSelectParent($statement, $result, $resultSet);
            $this->postSelectChild($statement, $result, $resultSet);
        }
    }

    /**
     *
     * @param StatementInterface $statement
     * @param ResultInterface $result
     * @param ResultSet $resultSet
     */
    protected function postSelectParent(StatementInterface $statement, ResultInterface $result, ResultSet $resultSet)
    {
        if ($resultSet->count() > 0) {
            $list = $resultSet->toArray();
            $list = $this->build($list);
            $resultSet->initialize($list);
        }
    }

    /**
     *
     * @param array|\ArrayIterator $list
     * @return array|\ArrayIterator
     */
    protected function build($list)
    {
        $functionArrayMerge = null;
        $functionArrayMerge = function ($nodeOri, $node) use (&$functionArrayMerge) {
            foreach ($node as $k => $v) {
                if (is_array($v)) {
                    if (! array_key_exists($k, $nodeOri)) {
                        $nodeOri[$k] = array();
                    }
                    $v = call_user_func($functionArrayMerge, $nodeOri[$k], $v);
                }
                $nodeOri[$k] = $v;
            }
            return $nodeOri;
        };
        foreach ($list as $k => $v) {
            $vNew = array();
            foreach ($v as $k1 => $v1) {
                $k1 = explode($this->tableColumnSep, $this->parentSep . $k1);
                $k1 = array_reverse($k1);
                $node = $v1;
                while (count($k1) > 0) {
                    $k1new = array_shift($k1);
                    if (0 === count($k1)) {
                        if (is_array($node)) {
                            $nodeOri = array();
                            if (array_key_exists($k1new, $vNew)) {
                                $nodeOri = $vNew[$k1new];
                            }
                            $vNew[$k1new] = call_user_func($functionArrayMerge, $nodeOri, $node);
                        } else {
                            $vNew[$k1new] = $node;
                        }
                    } else {
                        $node = array(
                            $k1new => $node
                        );
                    }
                }
            }
            $list[$k] = $vNew;
        }
        foreach ($list as $k => $v) {
            $v = $this->listFilter($v);
            $v = current($v);
            $list[$k] = $v;
        }
        return $list;
    }

    /**
     *
     * @param array $node
     * @return array
     */
    protected function listFilter($node)
    {
        $nodeAppend = array();
        foreach ($node as $k => $v) {
            if (false !== ($pos = strpos($k, $this->parentSep))) {
                $v = call_user_func(__METHOD__, $v);
                $tableName = substr($k, $pos + strlen($this->parentSep));
                $schema = $this->metadata->getDefaultSchema();
                if (false !== strpos($tableName, $this->dbSep)) {
                    list ($schema, $tableName) = explode($this->dbSep, $tableName);
                }
                if (isset($this->resultFilter)) {
                    $meta = $this->metadata->getTable($tableName, $schema);
                    $v = call_user_func($this->resultFilter, $v, $meta);
                }
                $nodeAppend[substr($k, 0, strrpos($k, $this->parentSep) + 1)] = $v;
                unset($node[$k]);
            }
        }
        $nodeNew = array();
        foreach ($node as $k => $v) {
            $nodeNew[$k] = $v;
            $kNew = $k . $this->parentSep;
            if (array_key_exists($kNew, $nodeAppend)) {
                $nodeNew[$kNew] = $nodeAppend[$kNew];
                unset($nodeAppend[$kNew]);
            }
        }
        $nodeNew = array_merge($nodeNew, $nodeAppend);
        return $nodeNew;
    }

    /**
     *
     * @param string $type
     * @return AbstractProcessor
     */
    protected function getSqlProcessor($type)
    {
        $sqlProcessor = $this->sqlProcessor;
        if (! isset($sqlProcessor['default'])) {
            $sqlProcessor['default'] = new DefaultProcessor(new Options(array()));
        }
        if (! isset($sqlProcessor[$type])) {
            switch ($type) {
                case 'select':
                    $sqlProcessor[$type] = new SelectExpressionProcessor(new Options(array()));
                    break;
                case 'expression':
                    $sqlProcessor[$type] = new ExpressionListProcessor(new Options(array()));
                    break;
                case 'having':
                    $sqlProcessor[$type] = new HavingProcessor(new Options(array()));
                    break;
                case 'where':
                    $sqlProcessor[$type] = new WhereProcessor(new Options(array()));
                    break;
            }
        }
        return $sqlProcessor[$type];
    }

    /**
     *
     * @param callable $cb
     */
    function setResultFilter(callable $cb)
    {
        $this->resultFilter = $cb;
    }

    /**
     *
     * @param callable $cb
     */
    function setSelectColumnFilter(callable $cb)
    {
        $this->selectColumnFilter = $cb;
    }

    /**
     *
     * @param callable $cb
     */
    function setSelectFilter(callable $cb)
    {
        $this->selectFilter = $cb;
    }

    /**
     *
     * @param string $schemaName
     * @param string $tableName
     * @param string|array $columns
     * @param string $referencedTableSchema
     * @param string $referencedTableName
     * @param string|array $referencedColumns
     * @param int $depth
     * @param array $opt
     *            SelectParentFeature user defined instance, false will disable
     * @return self
     */
    function add($schemaName = null, $tableName, $columns = null, $referencedTableSchema = null, $referencedTableName = null,
        $referencedColumns = null, $depth = null, $opt = array())
    {
        if (! isset($depth)) {
            $depth = 0;
        }
        if (isset($referencedColumns)) {
            if (is_string($referencedColumns)) {
                $referencedColumns = array(
                    $referencedColumns
                );
            }
        }
        if (isset($columns)) {
            if (is_string($columns)) {
                $columns = array(
                    $columns
                );
            }
        }
        if (! array_key_exists($depth, $this->childUser)) {
            $this->childUser[$depth] = array();
        }
        $this->childUser[$depth][] = [
            'schemaName' => $schemaName,
            'tableName' => $tableName,
            'columns' => $columns,
            'referencedTableSchema' => $referencedTableSchema,
            'referencedTableName' => $referencedTableName,
            'referencedColumns' => $referencedColumns,
            '_opt' => $opt
        ];
        return $this;
    }

    /**
     *
     * @param callable $cb
     * @param number $depth
     */
    function addSelectFilter($cb, $depth = 0)
    {
        $this->childSelectFilter[$depth] = $cb;
    }

    /**
     *
     * @param Select $select
     */
    protected function preSelectChild(Select $select)
    {
        if (! array_key_exists(self::$childDepth, $this->childUser) || 0 === count($this->childUser[self::$childDepth])) {
            return;
        }
        $childUser = $this->childUser[self::$childDepth];
        $childAll = $this->metadata->getConstraintsReferenced($this->tableGateway->getTable());
        $joins = $select->getRawState($select::JOINS);
        $dbSep = $this->dbSep;
        $aliasSep = $this->tableAliasSep;
        $sqlQuoteSymbol = $this->tableGateway->adapter->getPlatform()->getQuoteIdentifierSymbol();
        foreach ($joins as $v) {
            $vSchema = null;
            if (is_array($v['name'])) {
                $vTableNameAlias = key($v['name']);
            } else {
                $vTableNameAlias = $v['name'];
            }
            if (false !== strpos($vTableNameAlias, $aliasSep)) {
                continue;
            }
            if (is_array($v['name'])) {
                $vTableName = current($v['name']);
            } else {
                $vTableName = $v['name'];
            }
            if ($vTableName instanceof Expression) {
                $vTableName = $vTableName->getExpression();
                if (false !== strpos($vTableName, $dbSep)) {
                    list ($vSchema, $vTableName) = explode($dbSep, $vTableName);
                    $vSchema = trim($vSchema, $sqlQuoteSymbol);
                }
            }
            $vTableName = trim($vTableName, $sqlQuoteSymbol);
            $childAll = array_merge($childAll, $this->metadata->getConstraintsReferenced($vTableName, $vSchema));
        }
        $child = array();
        foreach ($childAll as $v) {
            $v = clone $v;
            foreach ($childUser as $v1) {
                $v1opt = $v1['_opt'];
                unset($v1['_opt']);
                foreach ($v1 as $k2 => $v2) {
                    if (isset($v2)) {
                        if ($v2 !== call_user_func(array(
                            $v,
                            'get' . ucfirst($k2)
                        ))) {
                            continue 2;
                        }
                    }
                }
                $v->_opt = $v1opt;
                $child[] = $v;
            }
        }
        $this->child = $child;
    }

    /**
     *
     * @param StatementInterface $statement
     * @param ResultInterface $result
     * @param ResultSet $resultSet
     */
    protected function postSelectChild(StatementInterface $statement, ResultInterface $result, ResultSet $resultSet)
    {
        if (count($this->child) > 0 && $resultSet->count() > 0) {
            $list = $this->attach($resultSet->toArray());
            $resultSet->initialize($list);
        }
    }

    /**
     *
     * @param array $list
     * @return array
     */
    function attach($list)
    {
        $functionGetKey = null;
        $functionGetKey = function (&$res, $pre, $constraint, $constraintReferenced) use (&$functionGetKey) {
            $pre[] = $this->getParentColumnName($constraint);
            if ($constraint->getReferencedTableSchema() === $constraintReferenced->getReferencedTableSchema() && $constraint->getReferencedTableName() ===
                $constraintReferenced->getReferencedTableName()) {
                $res[] = array_merge($pre, [
                    $constraintReferenced->getReferencedColumns()
                ]);
            }
            foreach ($this->metadata->getConstraintsFk($constraint->getReferencedTableName(),
                $constraint->getReferencedTableSchema()) as $v) {
                $functionGetKey($res, $pre, $v, $constraintReferenced);
            }
        };
        $functionAttach = null;
        $functionAttach = function ($node, $keys, $constraint, $listGroup) use (&$functionAttach) {
            foreach ($keys as $k => $v) {
                $vNode = array_shift($v);
                $keys[$k] = $v;
                foreach ($node as $k1 => $v1) {
                    if ($k1 == $vNode) {
                        if (1 == count($v) && is_array($v[0])) {
                            $kGroup = '';
                            $referencedColumns = $constraint->getReferencedColumns();
                            foreach ($referencedColumns as $v2) {
                                $kGroup .= $v1[$v2];
                            }
                            if (array_key_exists($kGroup, $listGroup)) {
                                $sub = $listGroup[$kGroup];
                            } else {
                                $sub = array();
                            }
                            $v1[$this->getChildColumnName($constraint)] = $sub;
                        } else {
                            $v1 = array_merge($v1,
                                call_user_func_array($functionAttach, array(
                                    $v1,
                                    $keys,
                                    $constraint,
                                    $listGroup
                                )));
                        }
                    }
                    $node[$k1] = $v1;
                }
            }
            return $node;
        };
        $tableName = $this->tableGateway->getTable();
        foreach ($list as $k => $v) {
            $list[$k] = array(
                $this->parentSep => $v
            );
        }
        foreach ($this->child as $k => $v) {
            $vTableName = $v->getTableName();
            $vColumns = $v->getColumns();
            $vReferencedTableSchema = $v->getReferencedTableSchema();
            $vReferencedTableName = $v->getReferencedTableName();
            $vReferencedColumns = $v->getReferencedColumns();
            $vColumnsMap = array();
            foreach ($vColumns as $k1 => $v1) {
                $vColumnsMap[$vReferencedColumns[$k1]] = $v1;
            }
            $vKeys = [];
            $functionGetKey($vKeys, [],
                (new ConstraintObject(null, null))->setReferencedTableName($tableName)->setReferencedTableSchema(
                    $this->metadata->getDefaultSchema()), $v);
            $vValues = [];
            foreach ($list as $v1) {
                foreach ($vKeys as $v2) {
                    $v1value = $v1;
                    foreach ($v2 as $v3) {
                        if (is_array($v3)) {
                            $vValueNode = [];
                            foreach ($v3 as $v4) {
                                $vValueNode[$v4] = $v1value[$v4];
                            }
                            $vValues[] = $vValueNode;
                        } else {
                            $v1value = $v1value[$v3];
                        }
                    }
                }
            }
            if (! empty($vValues)) {
                $tableGatewayName = get_class($this->tableGateway);
                $vTableGateway = new $tableGatewayName($vTableName, $this->tableGateway->getAdapter());
                if (isset($v->_opt['SelectParentFeature'])) {
                    $vParentFeature = $v->_opt['SelectParentFeature'];
                } else {
                    $vParentFeature = $vTableGateway->getFeatureSet()->getFeatureByClassName(SelectFeature::class);
                    if (false === $vParentFeature) {
                        $vParentFeature = $this->tableGateway->getFeatureSet()->getFeatureByClassName(
                            SelectFeature::class);
                    }
                }
                $vChildFeature = new self($this->metadata);
                foreach ($this->childSelectFilter as $k1 => $v1) {
                    if ($k1 != self::$childDepth) {
                        $vChildFeature->addSelectFilter($v1, $k1 - 1);
                    }
                }
                foreach ($this->childUser as $k1 => $v1) {
                    if ($k1 != self::$childDepth) {
                        foreach ($v1 as $v2) {
                            $vChildFeature->add($v2['schemaName'], $v2['tableName'], $v2['columns'],
                                $v2['referencedTableSchema'], $v2['referencedTableName'], $v2['referencedColumns'],
                                $k1 - 1);
                        }
                    }
                }
                $vTableGateway->getFeatureSet()->addFeature($vChildFeature);
                if (false !== $vParentFeature) {
                    $vTableGateway->getFeatureSet()->addFeature($vParentFeature);
                    $exclude = $vExcludeOri = $vParentFeature->getExclude();
                    $exclude[] = array(
                        $vReferencedTableSchema,
                        $vReferencedTableName,
                        $vReferencedColumns
                    );
                    $vParentFeature->setExclude($exclude);
                }
                $vSelect = $vTableGateway->getSql()->select();
                foreach ($vValues as $k1 => $v1) {
                    $v1new = array();
                    foreach ($v1 as $k2 => $v2) {
                        $v1new[$vTableName . '.' . $vColumnsMap[$k2]] = $v2;
                    }
                    $vSelect->where($v1new, PredicateSet::OP_OR);
                }
                $vSelect->limit($this->childLimit * count($vValues));
                if (isset($this->childSelectFilter[self::$childDepth])) {
                    $vSelect = call_user_func($this->childSelectFilter[self::$childDepth], $vSelect);
                }
                // add columns for attach
                if (false === $vParentFeature) {
                    $vSelectColumns = $vSelect->getRawState('columns');
                    foreach ($vColumns as $v1) {
                        if (! in_array($v1, $vSelectColumns)) {
                            $vSelectColumns[] = $v1;
                        }
                    }
                    $vSelect->columns($vSelectColumns);
                }
                self::$childDepth ++;
                $subList = $vTableGateway->selectWith($vSelect)->toArray();
                self::$childDepth --;
                if (false !== $vParentFeature) {
                    $vParentFeature->setExclude($vExcludeOri);
                }
                $subListGroup = array();
                foreach ($subList as $v1) {
                    $kGroup = '';
                    foreach ($vColumns as $v2) {
                        $kGroup .= $v1[$v2];
                    }
                    if (! array_key_exists($kGroup, $subListGroup)) {
                        $subListGroup[$kGroup] = array();
                    }
                    $subListGroup[$kGroup][] = $v1;
                }
                // attach
                foreach ($list as $k1 => $v1) {
                    $list[$k1] = call_user_func_array($functionAttach, array(
                        $v1,
                        $vKeys,
                        $v,
                        $subListGroup
                    ));
                }
            }
        }
        foreach ($list as $k => $v) {
            $list[$k] = current($v);
        }
        return $list;
    }

    protected function getParentColumnName(ConstraintObject $constraint)
    {
        $columnName = implode($this->columnSep, $constraint->getColumns()) . $this->parentSep;
        return $columnName;
    }

    /**
     *
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     *
     * @param boolean $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }
}