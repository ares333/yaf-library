<?php

namespace Ares333\Yaf\Helper {

    /**
     * execute `new Functions()` to load functions into root namespace
     */
    class Functions
    {

        static function printr()
        {
            $args = func_get_args();
            static::tag();
            foreach ($args as $v) {
                if (is_scalar($v)) {
                    echo $v . "\n";
                } else {
                    print_r($v);
                }
            }
            exit();
        }

        static function vardump()
        {
            $args = func_get_args();
            static::tag();
            call_user_func_array('var_dump', $args);
            exit();
        }

        protected static function tag()
        {
            static $tagged = false;
            if (!$tagged) {
                if (PHP_SAPI == 'fpm-fcgi') {
                    echo '<pre>';
                }
                $tagged = true;
            }
        }

        static function arrayDict(array $list, $columns = null, $primary = null, $group = null)
        {
            if (!isset($columns)) {
                $columns = array();
            }
            if (!isset($primary)) {
                $primary = array();
            }
            if (!isset($group)) {
                $group = array();
            }
            if (is_scalar($columns)) {
                $columns = array(
                    $columns
                );
            }
            if (is_scalar($primary)) {
                $primary = array(
                    $primary
                );
            }
            if (is_scalar($group)) {
                $group = array(
                    $group
                );
            }
            $listNew = array();
            foreach ($list as $v) {
                $vPrimaryValue = array();
                foreach ($primary as $v1) {
                    if (is_scalar($v1)) {
                        $v1 = array(
                            $v1
                        );
                    }
                    $v1v = $v;
                    foreach ($v1 as $v2) {
                        if (is_object($v1v)) {
                            $v1v = $v1v->$v2;
                        } else {
                            $v1v = $v1v[$v2];
                        }
                    }
                    $vPrimaryValue[] = $v1v;
                }
                if ([] !== $columns) {
                    $vNew = array();
                    foreach ($columns as $k1 => $v1) {
                        if (is_scalar($v1)) {
                            $v1 = array(
                                $k1 => $v1
                            );
                        }
                        $v1v = $v;
                        $v1new = &$vNew;
                        foreach ($v1 as $k2 => $v2) {
                            if (is_int($k2)) {
                                $k2 = $v2;
                            }
                            if (is_object($v1v)) {
                                $v1v = $v1v->$v2;
                            } else {
                                $v1v = $v1v[$v2];
                            }
                            $v1new = &$v1new[$k2];
                        }
                        $v1new = $v1v;
                        unset($v1new);
                    }
                } else {
                    $vNew = $v;
                }
                if (is_object($v)) {
                    settype($vNew, 'object');
                }
                if ([] !== $group) {
                    $vGroup = &$listNew;
                    foreach ($group as $v1) {
                        if (is_scalar($v1)) {
                            $v1 = array(
                                $v1
                            );
                        }
                        $v1v = $v;
                        foreach ($v1 as $v2) {
                            if (is_object($v)) {
                                $v1v = $v1v->$v2;
                            } else {
                                $v1v = $v1v[$v2];
                            }
                        }
                        $vGroup = &$vGroup[$v1v];
                    }
                    if ([] !== $vPrimaryValue) {
                        $vGroupNew = &$vGroup;
                        foreach ($vPrimaryValue as $v1) {
                            $vGroupNew = &$vGroupNew[$v1];
                        }
                        $vGroupNew = $vNew;
                        unset($vGroupNew);
                    } else {
                        $vGroup[] = $vNew;
                    }
                    unset($vGroup);
                } else {
                    if ([] !== $vPrimaryValue) {
                        $vListNew = &$listNew;
                        foreach ($vPrimaryValue as $v1) {
                            $vListNew = &$vListNew[$v1];
                        }
                        $vListNew = $vNew;
                        unset($vListNew);
                    } else {
                        $listNew[] = $vNew;
                    }
                }
            }
            return $listNew;
        }
    }
}

namespace {

    use Ares333\Yaf\Helper\Functions;

    if (!function_exists('printr')) {

        /**
         *
         * @return mixed
         */
        function printr()
        {
            return call_user_func_array(Functions::class . '::printr', func_get_args());
        }
    }

    if (!function_exists('vardump')) {

        /**
         *
         * @return mixed
         */
        function vardump()
        {
            return call_user_func_array(Functions::class . '::vardump', func_get_args());
        }
    }

    if (!function_exists('array_dict')) {

        /**
         *
         * @param array $list
         * @param string|array $columns
         * @param string|array $primary
         * @param string|array $group
         * @return array
         */
        function array_dict(array $list, $columns = null, $primary = null, $group = null)
        {
            return call_user_func(Functions::class . '::arrayDict', $list, $columns, $primary, $group);
        }
    }
}