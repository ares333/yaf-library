<?php
/**
 * detect if issue exist
 */
use Zend\Db\Adapter\Adapter;
use Ares333\Yaf\Zend\Db\TableGateway\TableGateway;

require_once __DIR__ . '/../../vendor/autoload.php';

$opt = include_once __DIR__ . '/../_inc/db.php';

$adapter = new Adapter(
    [
        'driver' => 'Pdo_Mysql',
        'hostname' => $opt['host'],
        'username' => $opt['username'],
        'password' => isset($opt['password']) ? $opt['password'] : '',
        'database' => $opt['database'],
        'driver_options' => [
            \PDO::ATTR_EMULATE_PREPARES => false
        ]
    ]);
$tableName = '_yafLibraryIssue' . md5(__FILE__);
$adapter->query(
    'CREATE TABLE IF NOT EXISTS `' . $tableName . '` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data` bit(1) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin', Adapter::QUERY_MODE_EXECUTE);
$adapter->query('truncate table `' . $tableName . '`', Adapter::QUERY_MODE_EXECUTE);
$table = new TableGateway($tableName, $adapter);
$table->insert([
    'id' => 1,
    'data' => true
]);
$row = $table->select([
    'id' => 1
])->current();
if (! isset($row)) {
    echo "issue exist";
} else {
    echo "issue not exist";
}
echo "\n";
$adapter->query('drop table `' . $tableName . '`', Adapter::QUERY_MODE_EXECUTE);