<?php
/**
 * detect if issue exist
 */
use Zend\Db\Adapter\Adapter;
use Zend\Db\Metadata\Source\MysqlMetadata;

require_once __DIR__ . '/../../vendor/autoload.php';

$opt = include_once __DIR__ . '/../_inc/db.php';

$adapter = new Adapter(
    [
        'driver' => 'Pdo_Mysql',
        'hostname' => $opt['host'],
        'username' => $opt['username'],
        'password' => isset($opt['password']) ? $opt['password'] : '',
        'database' => $opt['database']
    ]);
$tableName = array();
for ($k = 1; $k < 3; $k ++) {
    $tableName[] = '_yafLibraryIssue' . $k . md5(__FILE__);
}

$adapter->query(
    'CREATE TABLE IF NOT EXISTS `' . $tableName[1] . '` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', Adapter::QUERY_MODE_EXECUTE);

$adapter->query(
    'CREATE TABLE IF NOT EXISTS `' . $tableName[0] .
    '` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `value` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `columnKey` (`value`) USING BTREE,
  CONSTRAINT `columnKey` FOREIGN KEY (`value`) REFERENCES `' .
    $tableName[1] . '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', Adapter::QUERY_MODE_EXECUTE);

$meta = new MysqlMetadata($adapter);

$flag = false;
foreach ($meta->getConstraints($tableName[0]) as $v) {
    if ($v->isUnique() && count($v->getColumns()) > 1) {
        $flag = true;
    }
}
echo $flag ? "issue exist" : "issue not exist";
echo "\n";
foreach ($tableName as $v) {
    $adapter->query('drop table `' . $v . '`', Adapter::QUERY_MODE_EXECUTE);
}