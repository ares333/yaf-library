<?php
namespace Ares333\Yaf\Helper;

class Singleton
{

    protected static $instances = [];

    /**
     *
     * @param string|callable $name
     * @param array $args
     * @return mixed
     */
    static function getInstance($name, ...$args)
    {
        $key = static::generateKey($name);
        $subKey = static::generateKey($args);
        if (! isset(static::$instances[$key][$subKey])) {
            if (is_callable($name)) {
                $object = call_user_func_array($name, $args);
            } else {
                $reflection = new \ReflectionClass($name);
                $object = $reflection->newInstanceWithoutConstructor();
                $method = $reflection->getConstructor();
                if (isset($method)) {
                    $method->setAccessible(true);
                    $method->invokeArgs($object, $args);
                }
            }
            if (! isset(static::$instances[$key])) {
                static::$instances[$key] = [];
            }
            static::$instances[$key][$subKey] = $object;
        }
        return static::$instances[$key][$subKey];
    }

    static function getInstances($name = null)
    {
        $key = static::generateKey($name);
        if (! isset($key)) {
            return static::$instances;
        }
        if (! isset(static::$instances[$key])) {
            return [];
        }
        return static::$instances[$key];
    }

    static function unsetInstance($name, $args)
    {
        unset(static::$instances[static::generateKey($name)][static::generateKey($args)]);
    }

    protected static function generateKey($args)
    {
        $functionStringValue = null;
        $functionStringValue = function ($var) use (&$functionStringValue) {
            if (is_array($var)) {
                ksort($var);
                $str = '';
                foreach ($var as $k => $v) {
                    $str .= $k . call_user_func($functionStringValue, $v);
                }
                return $str;
            } elseif (is_object($var)) {
                return spl_object_hash($var);
            } else {
                return (string) $var;
            }
        };
        return md5($functionStringValue($args));
    }
}