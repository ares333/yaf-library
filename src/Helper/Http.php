<?php
namespace Ares333\Yaf\Helper;

class Http
{

    /**
     *
     * @param array $parse
     * @return string
     */
    static function buildUrl(array $parse)
    {
        $keys = array(
            'scheme',
            'host',
            'port',
            'user',
            'pass',
            'path',
            'query',
            'fragment'
        );
        $parseOrigin = $parse;
        foreach ($keys as $v) {
            if (! isset($parse[$v])) {
                $parse[$v] = '';
            }
        }
        if ('' !== $parse['scheme']) {
            $parse['scheme'] .= '://';
        }
        if ('' !== $parse['user']) {
            $parse['user'] .= ':';
            $parse['pass'] .= '@';
        }
        if ('' !== $parse['port']) {
            $schemePort = [
                'http' => 80,
                'https' => 443
            ];
            if (! isset($parseOrigin['scheme'], $schemePort[$parseOrigin['scheme']]) ||
                $parseOrigin['port'] != $schemePort[$parseOrigin['scheme']]) {
                $parse['host'] .= ':';
            } else {
                $parse['port'] = '';
            }
        }
        if ('' !== $parse['query']) {
            $parse['path'] .= '?';
            // sort
            $query = [];
            parse_str($parse['query'], $query);
            asort($query);
            $parse['query'] = http_build_query($query);
        }
        if ('' !== $parse['fragment']) {
            $parse['query'] .= '#';
        }
        $parse['path'] = preg_replace('/\/+/', '/', $parse['path']);
        return $parse['scheme'] . $parse['user'] . $parse['pass'] . $parse['host'] . $parse['port'] . $parse['path'] .
            $parse['query'] . $parse['fragment'];
    }

    /**
     *
     * @param string|null $url
     * @return string|null
     */
    static function getOriginUrl($url = null)
    {
        if (! isset($url)) {
            $url = static::getCurrentUrl();
        }
        if (! isset($url)) {
            return null;
        }
        $urlArr = parse_url($url);
        $url = array(
            'scheme' => $urlArr['scheme'],
            'host' => $urlArr['host']
        );
        if (isset($urlArr['port'])) {
            $url['port'] = $urlArr['port'];
        }
        return static::buildUrl($url);
    }

    /**
     *
     * @return null|string
     */
    static function getCurrentUrl()
    {
        if (PHP_SAPI == 'cli') {
            return null;
        }
        $parse = [];
        $server = $_SERVER;
        if (! empty($server['REQUEST_SCHEME'])) {
            $parse['scheme'] = $server['REQUEST_SCHEME'];
        } else {
            $isSecure = false;
            if (isset($server['HTTPS']) && $server['HTTPS'] !== 'off') {
                $isSecure = true;
            } elseif (! empty($server['HTTP_X_FORWARDED_PROTO']) && $server['HTTP_X_FORWARDED_PROTO'] == 'https' || ! empty(
                $server['HTTP_X_FORWARDED_SSL']) && $server['HTTP_X_FORWARDED_SSL'] == 'on') {
                $isSecure = true;
            }
            $parse['scheme'] = $isSecure ? 'https' : 'http';
        }
        $parse['host'] = $server['HTTP_HOST'];
        $parse['port'] = $server['SERVER_PORT'];
        $uriArr = parse_url($server['REQUEST_URI']);
        $parse['path'] = $uriArr['path'];
        if (isset($uriArr['query'])) {
            $parse['query'] = $uriArr['query'];
        }
        return static::buildUrl($parse);
    }
}