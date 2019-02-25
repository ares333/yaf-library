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
        'charset' => 'utf8mb4',
        'driver_options' => [
            \PDO::ATTR_EMULATE_PREPARES => false
        ]
    ]);
$tableName = '_yafLibraryIssue' . md5(__FILE__);

$adapter->query(
    "CREATE TABLE IF NOT EXISTS `$tableName` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `数据` varchar(255) NOT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", Adapter::QUERY_MODE_EXECUTE);
$adapter->query('truncate table `' . $tableName . '`', Adapter::QUERY_MODE_EXECUTE);

$table = new TableGateway($tableName, $adapter);

$table->insert([
    '数据' => 'test1'
]);

$flag = false;
try {
    $table->select([
        '数据' => 'test1'
    ]);
} catch (PDOException $e) {
    $flag = true;
}
if ($flag) {
    echo "issue exist";
} else {
    echo "issue not exist";
}
echo "\n";
$adapter->query('drop table `' . $tableName . '`', Adapter::QUERY_MODE_EXECUTE);