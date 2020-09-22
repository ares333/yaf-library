<?php
namespace Ares333\Yaf;

use Smarty;
use SmartyException;
use Yaf\View_Interface;

class SmartyView implements View_Interface
{

    protected $smarty;

    /**
     *
     * @param Smarty $smarty
     */
    function __construct($smarty)
    {
        $this->smarty = $smarty;
    }

    /**
     *
     * @return Smarty
     */
    function getSmarty()
    {
        return $this->smarty;
    }

    /**
     *
     * @param Smarty $smarty
     */
    function setSmarty($smarty)
    {
        $this->smarty = $smarty;
    }

    /**
     *
     * {@inheritdoc}
     * @see \Yaf\View_Interface::setScriptPath()
     */
    public function setScriptPath($path)
    {
        $this->smarty->setTemplateDir($path);
    }

    /**
     *
     * {@inheritdoc}
     * @see \Yaf\View_Interface::getScriptPath()
     */
    public function getScriptPath()
    {
        return rtrim(current($this->smarty->getTemplateDir()), '/');
    }

    /**
     *
     * {@inheritdoc}
     * @see \Yaf\View_Interface::assign()
     */
    public function assign($spec, $value = null)
    {
        if (! isset($value)) {
            $this->smarty->assign($spec);
            return;
        }
        $this->smarty->assign($spec, $value);
    }

    /**
     *
     * {@inheritdoc}
     * @throws SmartyException
     * @see \Yaf\View_Interface::render()
     */
    public function render($name, $value = NULL)
    {
        if (isset($value)) {
            $this->smarty->assign($value);
        }
        return $this->smarty->fetch($name);
    }

    /**
     *
     * {@inheritdoc}
     * @throws SmartyException
     * @see \Yaf\View_Interface::display()
     */
    public function display($name, $value = NULL)
    {
        if (isset($value)) {
            $this->assign($value);
        }
        $this->smarty->display($name);
    }
}