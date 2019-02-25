<?php
namespace Ares333\Yaf\Zend\Db\Sql;

use Zend\Db\Sql\Insert as Base;
use Zend\Db\Adapter\Platform\PlatformInterface;
use Zend\Db\Adapter\Driver\DriverInterface;
use Zend\Db\Adapter\ParameterContainer;

class Insert extends Base
{

    protected $duplicate = null;

    /**
     *
     * @param bool $flag
     * @return self
     */
    function setIgnore($flag)
    {
        $specifications = $this->specifications;
        $strIgnore = 'INSERT IGNORE ';
        $str = 'INSERT ';
        if ($flag) {
            foreach ($specifications as $k => $v) {
                if (0 !== strpos($v, $strIgnore)) {
                    $specifications[$k] = substr_replace($v, $strIgnore, 0, strlen($str));
                }
            }
        } else {
            foreach ($specifications as $k => $v) {
                if (0 === strpos($v, $strIgnore)) {
                    $specifications[$k] = substr_replace($v, $str, 0, strlen($strIgnore));
                }
            }
        }
        $this->specifications = $specifications;
        return $this;
    }

    /**
     *
     * @param array $values
     * @return self
     */
    function setDuplicate($values)
    {
        $this->duplicate = $values;
        return $this;
    }

    protected function processInsert(PlatformInterface $platform, DriverInterface $driver = null,
        ParameterContainer $parameterContainer = null)
    {
        $sql = parent::processInsert($platform, $driver, $parameterContainer);
        if (! isset($this->duplicate)) {
            return $sql;
        }
        $sql .= ' ON DUPLICATE KEY UPDATE ';
        $dup = [];
        foreach ($this->duplicate as $k => $v) {
            $k = $platform->quoteIdentifier($k);
            $v = $this->resolveColumnValue($v, $platform, $driver, $parameterContainer);
            $dup[] = "$k = $v";
        }
        $sql .= implode(',', $dup);
        return $sql;
    }
}