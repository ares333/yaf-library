<?php
$opt = getopt('', [
    'host:',
    'username:',
    'password:',
    'database:'
]);
if (! isset($opt['host'], $opt['username'], $opt['database'])) {
    exit("usage: php test.php --host localhost --username root --password 123456 --database test\n");
}
return $opt;
