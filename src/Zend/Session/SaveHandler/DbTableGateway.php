<?php
namespace Ares333\Yaf\Zend\Session\SaveHandler;

use Zend\Session\SaveHandler\DbTableGateway as Base;

class DbTableGateway extends Base
{

    /**
     * resolve issue in README.md
     *
     * {@inheritdoc}
     *
     * @see \Zend\Session\SaveHandler\DbTableGateway::write()
     */
    function write($id, $data)
    {
        $res = parent::write($id, $data);
        if (! $res) {
            $row = $this->tableGateway->select(
                [
                    $this->options->getIdColumn() => $id,
                    $this->options->getNameColumn() => $this->sessionName
                ])
                ->current();
            if (isset($row) && $row->data === $data) {
                $res = true;
            }
        }
        return $res;
    }
}