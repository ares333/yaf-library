<?php
namespace Ares333\Yaf\Zend\Cache\Pattern;

use Zend\Cache\Pattern\AbstractPattern;

class IdCache extends AbstractPattern
{

    /**
     *
     * @param array $ids
     * @param mixed $cb
     *
     * @return array
     */
    function getList(array $ids, $cb = null)
    {
        if (empty($ids)) {
            return array();
        }
        $idsMap = $keyMap = array();
        foreach ($ids as $v) {
            $idsMap[$v] = $this->getOptions()->getClass() . $v;
            $keyMap[$idsMap[$v]] = $v;
        }
        $dict = array();
        $storage = $this->getOptions()->getStorage();
        $dictMaped = $storage->getItems($idsMap);
        foreach ($dictMaped as $k => $v) {
            $dict[$keyMap[$k]] = $v;
        }
        $idsNoCache = array_diff_key($keyMap, $dictMaped);
        if (! empty($idsNoCache)) {
            $dictNoCache = array();
            if (isset($cb)) {
                $dictNoCache = call_user_func($cb, $idsNoCache);
            }
            $dictNoCacheMaped = array();
            foreach ($idsNoCache as $k => $v) {
                if (array_key_exists($v, $dictNoCache)) {
                    $dict[$v] = $dictNoCacheMaped[$k] = $dictNoCache[$v];
                }
            }
            $storage->addItems($dictNoCacheMaped);
        }
        foreach ($ids as $k => &$v) {
            if (! array_key_exists($v, $dict)) {
                unset($ids[$k]);
            } else {
                $v = $dict[$v];
            }
        }
        return array_values($ids);
    }

    /**
     *
     * @param mixed $key
     * @param mixed $cb
     *
     * @return mixed
     */
    function get($key, $cb = null)
    {
        return current(
            $this->getList(array(
                $key
            ), function ($ids) use ($cb) {
                if (isset($cb)) {
                    return array(
                        current($ids) => $cb()
                    );
                }
                return null;
            }));
    }

    /**
     *
     * @param string $key
     *
     * @return boolean
     */
    function delete($key)
    {
        return $this->getOptions()
            ->getStorage()
            ->removeItem($this->getOptions()
            ->getClass() . $key);
    }
}