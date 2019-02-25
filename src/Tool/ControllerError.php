<?php
namespace Ares333\Yaf\Tool;

use Yaf\Controller_Abstract;
use Yaf\Exception\LoadFailed\Action;
use Yaf\Exception\LoadFailed\Controller;
use Yaf\Exception\LoadFailed\Module;
use Ares333\Yaf\Helper\Error;

class ControllerError extends Controller_Abstract
{

    function errorAction(\Throwable $exception)
    {
        if ($this->is404($exception)) {
            return $this->error404Action();
        } else {
            $this->error500Action();
            Error::catchException($exception);
        }
    }

    function error500Action()
    {
        if (! $this->getRequest()->isCli()) {
            http_response_code(500);
        }
        if (! ini_get('display_errors')) {
            echo '500 Internal Server Error' . "\n";
        }
        return false;
    }

    function error404Action()
    {
        if (! $this->getRequest()->isCli()) {
            http_response_code(404);
        }
        echo '404 Not Found' . "\n";
        return false;
    }

    function error403Action()
    {
        if (! $this->getRequest()->isCli()) {
            http_response_code(403);
        }
        echo '403 Forbidden' . "\n";
        return false;
    }

    /**
     * Land page.
     * Useful for redirect outside of controller
     *
     * @return boolean
     */
    function dummyAction()
    {
        return false;
    }

    /**
     * is 404
     *
     * @param \Throwable $exception
     * @return boolean
     */
    protected function is404($exception)
    {
        return $exception instanceof Module || $exception instanceof Controller || $exception instanceof Action;
    }
}