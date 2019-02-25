<?php
namespace Ares333\Yaf\Zend\Db\Metadata\Object;

use Zend\Db\Metadata\Object\ColumnObject as Base;

class ColumnObject extends Base
{

    protected $isVirtual;

    protected $isAutoIncrement;

    function isVirtual()
    {
        return $this->isVirtual;
    }

    function setIsVirtual($isVirtual)
    {
        $this->isVirtual = $isVirtual;
    }

    function isAutoIncrement()
    {
        return $this->isAutoIncrement;
    }

    function setIsAutoIncrement($isAutoIncrement)
    {
        $this->isAutoIncrement = $isAutoIncrement;
    }
}