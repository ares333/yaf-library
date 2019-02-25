<?php
namespace Ares333\Yaf\Tool;

use Ares333\Yaf\Helper\Singleton as Base;

/**
 * perfect singleton
 *
 * @author ares
 *        
 */
trait Singleton
{

    /**
     *
     * @return self
     */
    public static function getInstance()
    {
        $args = func_get_args();
        array_unshift($args, get_called_class());
        return call_user_func_array(Base::class . '::getInstance', $args);
    }
}