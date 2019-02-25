<?php
/**
 * detect if issue exist
 */
use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Zend\Session\SessionManager;
use Zend\Session\Container;
use Zend\Session\SaveHandler\DbTableGateway;
use Zend\Session\SaveHandler\DbTableGatewayOptions;
use Zend\Session\Config\SessionConfig;

/**
 * composer require zendframework/zend-session zendframework/zend-db
 * http://localhost/detect.php?opt[host]=127.0.0.1&opt[username]=root&opt[password]=&opt[database]=test
 */
require_once __DIR__ . '/vendor/autoload.php';

$opt = $_GET['opt'];

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
    "CREATE TABLE IF NOT EXISTS `$tableName` (
    `id` char(32) COLLATE utf8mb4_bin NOT NULL,
    `name` char(32) COLLATE utf8mb4_bin NOT NULL,
    `modified` int(11) DEFAULT NULL,
    `lifetime` int(11) DEFAULT NULL,
    `data` text COLLATE utf8mb4_bin,
    PRIMARY KEY (`id`,`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin", Adapter::QUERY_MODE_EXECUTE);
$adapter->query('truncate table `' . $tableName . '`', Adapter::QUERY_MODE_EXECUTE);

$tableGateway = new TableGateway($tableName, $adapter);
$saveHandler = new DbTableGateway($tableGateway, new DbTableGatewayOptions());
$sessionConfig = new SessionConfig();
$sessionConfig->setOptions([
    'name' => 'sid'
]);
$sessionManager = new SessionManager($sessionConfig, null, $saveHandler);
Container::setDefaultManager($sessionManager);
$sess = new Container(md5(__FILE__));
$sess->key = 'value';
$flag = false;
set_error_handler(
    function ($errno, $errstr, $errfile, $errline) use (&$flag) {
        if (false !== strpos($errstr, 'session_write_close')) {
            $flag = true;
        }
    });
$sessionManager->getConfig()->setOption('gc_maxlifetime', 2000);
$sessionManager->writeClose();
if ($flag) {
    echo "issue exist";
} else {
    echo "issue not exist";
}
$adapter->query('drop table `' . $tableName . '`', Adapter::QUERY_MODE_EXECUTE);