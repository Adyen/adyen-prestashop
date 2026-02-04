<?php

namespace AdyenPayment\Classes\Repositories;

use Adyen\Core\BusinessLogic\DataAccess\Interfaces\ConditionallyDeletes;
use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use Adyen\Core\Infrastructure\ORM\Utility\IndexHelper;

/**
 * Class BaseRepositoryWithConditionalDelete
 */
class BaseRepositoryWithConditionalDelete extends BaseRepository implements ConditionallyDeletes
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;

    /**
     * {@inheritDoc}
     */
    public function deleteWhere(?QueryFilter $queryFilter = null): void
    {
        try {
            $entity = new $this->entityClass();
            $type = $entity->getConfig()->getType();
            $indexMap = IndexHelper::mapFieldsToIndexes($entity);

            $groups = $queryFilter ? $this->buildConditionGroups($queryFilter, $indexMap) : [];
            $queryParts = $this->getQueryParts($groups, $indexMap);

            $whereClause = $this->generateWhereStatement($queryParts);

            \Db::getInstance()->delete($this->getDbName(), $whereClause . " AND type='" . pSQL($type) . "'");
        } catch (\Exception $e) {
            Logger::logError('Delete where failed with error ' . $e->getMessage());
        }
    }

    /**
     * Retrieves db_name for DBAL.
     *
     * @return string
     */
    protected function getDbName(): string
    {
        return 'adyen_entity';
    }
}
