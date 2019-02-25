<?php
namespace Ares333\Yaf\Zend\Db\Sql;

use Zend\Db\Sql\Sql as Base;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

class Sql extends Base
{

    /**
     *
     * @return Insert
     */
    function insert($table = null)
    {
        $insert = parent::insert($table);
        return new Insert($insert->getRawState('table'));
    }

    /**
     *
     * @param Select $select
     * @return Select
     */
    function selectCount($select = null)
    {
        if (! isset($select)) {
            $select = $this->getSql()->select();
        } else {
            $select = clone $select;
        }
        $select->reset($select::LIMIT);
        $select->reset($select::OFFSET);
        $select->reset($select::ORDER);
        $group = $select->getRawState($select::GROUP);
        if (! empty($group)) {
            $select = new Select(array(
                'selectOrigin' => $select
            ));
        } else {
            $join = $select->getRawState($select::JOINS);
            $joins = $join->getJoins();
            $join->reset();
            foreach ($joins as $v) {
                $join->join($v['name'], $v['on'], array(), $v['type']);
            }
            $select->columns(array(
                null
            ));
        }
        $select->columns(array(
            'c' => new Expression('count(1)')
        ));
        return $select;
    }
}