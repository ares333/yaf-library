## 关于
PHP Yaf框架的library，包含一些非常强劲的功能，使PHP开发异常便捷高效并且标准化。

## 需求
PHP: >= 5.4

## 安装
```
composer require ares333/yaf-library
```

## 联系我们
QQ群: 424844502

## ISSUE
已知问题都是依赖库的问题，在此作一个记录。

---
### #1 MysqlMetadata Uniq索引名称问题

**Environ:**
	<pre>
	pdo_mysql:^mysqlnd-5.0
	mysqld:^5.7-log
	zend-db:^2.9
	</pre>

Zend\Db\Metadata\Source\MysqlMetadata 当一个数据表中Uniq索引的名字和外键的名字一样时会导致约束(constraints)信息混乱。

解决：
1. 只能自行保证一个数据表中Uniq索引和外键名字不能一样。
2. 自行修复Zend\Db\Metadata\Source\MysqlMetadata::loadConstraintData()里面的问题，代价巨大，收益微小。

---
### #2 PDO插入bool型数据问题
**Environ:**
	<pre>
	pdo_mysql:^mysqlnd-5.0
	mysqld:^5.7-log	
	zend-db:^2.9
	</pre>
**Config:**
	<pre>
	PDO::ATTR_EMULATE_PREPARES = false
	PDO::ATTR_ERRMODE=PDO::ERRMODE_EXCEPTION
	</pre>

如果php类型是bool，TableGateway::insert()无法正常插入数据，因为 PDOStatement::execute()总是返回false并且不会触发相应Exception。

解决：
1. 使用Ares333\Yaf\Zend\Db\Adapter\Driver\Pdo\PDOStatement，此PDOStatement会自动判断issue并根据需要设置类型为\PDO::PARAM_INT。
   ```PHP
   use Ares333\Yaf\Zend\Db\Adapter\Driver\Pdo\PDOStatement;
   $adapter = new Adapter(
    [
        ...
        'driver_options' => [
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_STATEMENT_CLASS => [
                PDOStatement::class,
                [
                    &$adapter
                ]
            ]
        ]
    ]);
   ```
2. PDO::ATTR_EMULATE_PREPARES = true

---
### #3 Zend Session使用DbTableGateway作为saveHandler写入失败问题
**Environ:**
	<pre>
	zend-session:^2.8
	</pre>
Zend\Session\SessionManager::start()之后再做一些session相关配置会导致多次session持久化操作，如果saveHandler使用Zend\Session\SaveHandler\DbTableGateway，并且写入的数据和数据表中已存在数据一样会触发`session_write_close()`写入失败警告，不会造成实际影响。

因为如果当前session在数据表中已存在，DbTableGateway::write()实际执行update操作，因为数据一样所以update返回0，导致session_write_close()认为写入数据失败。

解决：

使用Ares333\Yaf\Zend\Session\SaveHandler\DbTableGateway

---
### #4 Zend Db Select::getSqlString() 错误问题
**Environ:**
	<pre>
	zend-db:^2.9
	</pre>

Driver使用Pdo_Mysql时，Adapter::query()可能无法执行Zend\Db\Sql\AbstractSql::getSqlString()生成的sql。比如带Zend\Db\Sql\Select::limit()，因为生成的语句中limit数字会被Zend\Db\Adapter\Platform\Mysql::quoteValue()加上单引号导致mysql报错。

Zend\Db\Sql\AbstractSql::getSqlString()使用的是Zend\Db\Adapter\Platform下的组件。

解决：

使用Zend\Db\Sql\Sql::buildSqlString()，此方法用的Zend\Db\Sql\Platform下的组件，经过阅读源码发现其中的组件实际上是做一些额外的事情。

---
### #5 Zend Db 数据表列名字符限制问题
**Environ:**
	<pre>
	zend-db:^2.9
	</pre>

Zend\Db\Adapter\Platform\AbstractPlatform 对列名称支持的字符限制很严格，在where或having条件中如果字段名字符超出限制转义就会出问题，比如字段名包含中文字符。

解决：

Zend\Db\Adapter\Platform\AbstractPlatform::$quoteIdentifierFragmentPattern protected 属性可以通过继承自行指定正则。

---
### #6 Session Save Handler 循环调用问题
**Environ:**
	<pre>
	zend-session:^2.7.4
	</pre>
	
E_WARNING: ErrorException: session_write_close(): Cannot call session save handler in a recursive manner in /srv/www/bc-spider.ares/vendor/zendframework/zend-session/src/SessionManager.php:236

如果使用了自定义的sessionSaveHandler并且在saveHandler的open()中exit就会触发，其他saveHandler函数没有测试。