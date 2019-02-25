<?php
namespace Ares333\Yaf\Zend\Db\TableGateway\Feature\Mysql;

use Zend\Db\TableGateway\Feature\AbstractFeature as Base;
use Ares333\Yaf\Zend\Db\Metadata\Source\MysqlMetadata;
use Zend\Db\Metadata\Object\ConstraintObject;

class AbstractFeature extends Base
{

    protected $metadata;

    protected $dbSep = '.';

    protected $tableAliasSep = '#';

    protected $parentSep = '@';

    protected $childSep = '|';

    protected $columnSep = '&';

    protected $closeTransaction = false;

    protected $virtualFk = array();

    protected static $virtualFkIndex = 0;

    protected $_identifierSeparator;

    /**
     *
     * @param MysqlMetadata $metadata
     */
    function __construct($metadata)
    {
        $this->metadata = $metadata;
    }

    protected function getIdentifierSeparator()
    {
        if (! isset($this->_identifierSeparator)) {
            $this->_identifierSeparator = $this->getTableGateway()
                ->getAdapter()
                ->getPlatform()
                ->getIdentifierSeparator();
        }
        return $this->_identifierSeparator;
    }

    /**
     *
     * @param string $schemaName
     * @param string $tableName
     * @param string|array $columns
     * @param string $referencedTableSchema
     * @param string $referencedTableName
     * @param string|array $referencedColumns
     * @return self
     */
    function addFk($schemaName = null, $tableName, $columns = null, $referencedTableSchema = null, $referencedTableName = null,
        $referencedColumns = null)
    {
        if (! isset($schemaName)) {
            $schemaName = $this->metadata->getDefaultSchema();
        }
        if (! isset($referencedTableSchema)) {
            $referencedTableSchema = $this->metadata->getDefaultSchema();
        }
        if (isset($columns)) {
            if (is_string($columns)) {
                $columns = array(
                    $columns
                );
            }
        }
        if (isset($referencedColumns)) {
            if (is_string($referencedColumns)) {
                $referencedColumns = array(
                    $referencedColumns
                );
            }
        }
        $name = $tableName . '_vfk_' . static::$virtualFkIndex ++;
        $fk = new ConstraintObject($name, $tableName, $schemaName);
        $fk->setType('FOREIGN KEY');
        $fk->setColumns($columns);
        $fk->setReferencedTableSchema($referencedTableSchema);
        $fk->setReferencedTableName($referencedTableName);
        $fk->setReferencedColumns($referencedColumns);
        $this->virtualFk[] = $fk;
        return $this;
    }

    /**
     *
     * @param ConstraintObject $constraint
     * @return string
     */
    function getChildColumnName(ConstraintObject $constraint)
    {
        $columnName = '';
        if ($constraint->getSchemaName() !== $constraint->getReferencedTableSchema()) {
            $columnName .= $constraint->getSchemaName() . $this->dbSep;
        }
        $columnName .= $constraint->getTableName() . $this->childSep .
            implode($this->columnSep, $constraint->getColumns());
        return $columnName;
    }

    protected function startTransaction()
    {
        $conn = $this->tableGateway->getAdapter()
            ->getDriver()
            ->getConnection();
        if (! $conn->inTransaction()) {
            $conn->beginTransaction();
            $this->closeTransaction = true;
        }
    }

    protected function endTransaction()
    {
        if ($this->closeTransaction) {
            $conn = $this->tableGateway->getAdapter()
                ->getDriver()
                ->getConnection();
            $conn->commit();
            $this->closeTransaction = false;
        }
    }

    /**
     *
     * @return string
     */
    function getChildSep()
    {
        return $this->childSep;
    }

    /**
     *
     * @return string
     */
    function getDbSep()
    {
        return $this->dbSep;
    }

    /**
     *
     * @return string
     */
    function getTableAliasSep()
    {
        return $this->tableAliasSep;
    }

    /**
     *
     * @return string
     */
    function getParentSep()
    {
        return $this->parentSep;
    }

    /**
     *
     * @return string
     */
    function getColumnSep()
    {
        return $this->columnSep;
    }

    /**
     *
     * @return \Zend\Db\TableGateway\AbstractTableGateway
     */
    function getTableGateway()
    {
        return $this->tableGateway;
    }
}
