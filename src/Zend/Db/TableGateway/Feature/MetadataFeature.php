<?php
namespace Ares333\Yaf\Zend\Db\TableGateway\Feature;

use Zend\Db\TableGateway\Feature\MetadataFeature as Base;
use Zend\Db\Metadata\Source\MysqlMetadata;

class MetadataFeature extends Base
{

    /**
     *
     * @param MysqlMetadata $metadata
     */
    function __construct($metadata)
    {
        parent::__construct();
        if (isset($metadata)) {
            $this->metadata = $metadata;
        }
    }

    /**
     *
     * @return MysqlMetadata
     */
    function getMetadata()
    {
        return $this->metadata;
    }
}