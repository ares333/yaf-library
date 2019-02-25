<?php
namespace Ares333\Yaf\Zend\Db\Adapter\Driver\Pdo;

use PDOStatement as Base;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Driver\Pdo\Connection;

class PDOStatement extends Base
{

    protected $adapter;

    protected function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function bindParam($parameter, &$variable, $data_type = null, $length = null, $driver_options = null)
    {
        $connection = $this->adapter->getDriver()->getConnection();
        // to resolve issue in README.md
        if ($connection instanceof Connection && $connection->getDriverName() == 'mysql' && $connection->getResource()->getAttribute(
            \PDO::ATTR_EMULATE_PREPARES) === 0 && $data_type === \PDO::PARAM_BOOL) {
            $data_type = \PDO::PARAM_INT;
        }
        return parent::bindParam($parameter, $variable, $data_type, $length, $driver_options);
    }
}