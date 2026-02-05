<?php

namespace AdyenPayment\Classes\Repositories;

use Adyen\Core\Infrastructure\Logger\Logger;
use Adyen\Core\Infrastructure\ORM\Entity;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ORM\Interfaces\RepositoryInterface;
use Adyen\Core\Infrastructure\ORM\QueryFilter\Operators;
use Adyen\Core\Infrastructure\ORM\QueryFilter\QueryCondition;
use Adyen\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use Adyen\Core\Infrastructure\ORM\Utility\IndexHelper;

/**
 * Class BaseRepository
 */
class BaseRepository implements RepositoryInterface
{
    /**
     * Fully qualified name of this class.
     */
    public const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    public const TABLE_NAME = 'adyen_entity';
    /**
     * @var string
     */
    protected $entityClass;
    /**
     * @var array
     */
    private $indexMapping;

    /**
     * Returns full class name.
     *
     * @return string full class name
     */
    public static function getClassName(): string
    {
        return static::THIS_CLASS_NAME;
    }

    /**
     * Sets repository entity.
     *
     * @param string $entityClass repository entity class
     */
    public function setEntityClass($entityClass): void
    {
        $this->entityClass = $entityClass;
    }

    /**
     * Executes select query.
     *
     * @param QueryFilter|null $filter filter for query
     *
     * @return Entity[] a list of resulting entities
     *
     * @throws QueryFilterInvalidParamException
     * @throws \PrestaShopDatabaseException
     */
    public function select(?QueryFilter $filter = null): array
    {
        /** @var Entity $entity */
        $entity = new $this->entityClass();

        $fieldIndexMap = IndexHelper::mapFieldsToIndexes($entity);
        $groups = $filter ? $this->buildConditionGroups($filter, $fieldIndexMap) : [];
        $type = $entity->getConfig()->getType();

        $typeCondition = "type='" . pSQL($type) . "'";
        $whereCondition = $this->buildWhereCondition($groups, $fieldIndexMap);
        $result = $this->getRecordsByCondition(
            $typeCondition . (!empty($whereCondition) ? ' AND ' . $whereCondition : ''),
            $filter
        );

        return $this->unserializeEntities($result);
    }

    /**
     * Executes select query and returns first result.
     *
     * @param QueryFilter|null $filter filter for query
     *
     * @return Entity|null first found entity or NULL
     *
     * @throws QueryFilterInvalidParamException
     * @throws \PrestaShopDatabaseException
     */
    public function selectOne(?QueryFilter $filter = null): ?Entity
    {
        if ($filter === null) {
            $filter = new QueryFilter();
        }

        $filter->setLimit(1);
        $results = $this->select($filter);

        return empty($results) ? null : $results[0];
    }

    /**
     * Executes insert query and returns ID of created entity. Entity will be updated with new ID.
     *
     * @param Entity $entity entity to be saved
     *
     * @return int identifier of saved entity
     *
     * @throws \PrestaShopDatabaseException
     */
    public function save(Entity $entity): int
    {
        $indexes = IndexHelper::transformFieldsToIndexes($entity);
        $record = $this->prepareDataForInsertOrUpdate($entity, $indexes);
        $record['type'] = pSQL($entity->getConfig()->getType());

        $result = \Db::getInstance()->insert($this->getDbName(), $record);

        if (!$result) {
            $message = 'Entity ' . $entity->getConfig()->getType() .
                ' cannot be inserted. Error: ' . \Db::getInstance()->getMsgError();
            Logger::logError($message);

            throw new \RuntimeException($message);
        }

        $entity->setId((int) \Db::getInstance()->Insert_ID());

        return $entity->getId();
    }

    /**
     * Counts records that match filter criteria.
     *
     * @param QueryFilter|null $filter filter for query
     *
     * @return int number of records that match filter criteria
     *
     * @throws QueryFilterInvalidParamException
     * @throws \PrestaShopDatabaseException
     */
    public function count(?QueryFilter $filter = null): int
    {
        return count($this->select($filter));
    }

    /**
     * Executes update query and returns success flag.
     *
     * @param Entity $entity entity to be updated
     *
     * @return bool TRUE if operation succeeded; otherwise, FALSE
     */
    public function update(Entity $entity): bool
    {
        $indexes = IndexHelper::transformFieldsToIndexes($entity);
        $record = $this->prepareDataForInsertOrUpdate($entity, $indexes);

        $id = (int) $entity->getId();
        $result = \Db::getInstance()->update($this->getDbName(), $record, "id = $id");
        if (!$result) {
            Logger::logError('Entity ' . $entity->getConfig()->getType() . ' with ID ' . $id . ' cannot be updated.');
        }

        return $result;
    }

    /**
     * Executes delete query and returns success flag.
     *
     * @param Entity $entity entity to be deleted
     *
     * @return bool TRUE if operation succeeded; otherwise, FALSE
     */
    public function delete(Entity $entity): bool
    {
        $id = (int) $entity->getId();
        $result = \Db::getInstance()->delete($this->getDbName(), "id = $id");

        if (!$result) {
            Logger::logError(
                'Could not delete entity ' . $entity->getConfig()->getType() . ' with ID ' . $entity->getId()
            );
        }

        return $result;
    }

    /**
     * Translates database records to Adyen entities.
     *
     * @param array $records array of database records
     *
     * @return Entity[]
     */
    protected function unserializeEntities(array $records): array
    {
        $entities = [];
        foreach ($records as $record) {
            $entity = $this->unserializeEntity($record['data']);
            if ($entity !== null) {
                $entity->setId((int) $record['id']);
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Returns index mapped to given property.
     *
     * @param string $property property name
     *
     * @return string index column in Adyen entity table
     */
    protected function getIndexMapping(string $property): ?string
    {
        if ($this->indexMapping === null) {
            $this->indexMapping = IndexHelper::mapFieldsToIndexes(new $this->entityClass());
        }

        if (array_key_exists($property, $this->indexMapping)) {
            return 'index_' . $this->indexMapping[$property];
        }

        return null;
    }

    /**
     * Returns columns that should be in the result of a select query on Adyen entity table.
     *
     * @return array select columns
     */
    protected function getSelectColumns(): array
    {
        return ['id', 'data'];
    }

    /**
     * Builds condition groups (each group is chained with OR internally, and with AND externally) based on query
     * filter.
     *
     * @param QueryFilter $filter query filter object
     * @param array $fieldIndexMap map of property indexes
     *
     * @return array Array of condition groups..
     *
     * @throws QueryFilterInvalidParamException
     */
    protected function buildConditionGroups(QueryFilter $filter, array $fieldIndexMap): array
    {
        $groups = [];
        $counter = 0;
        $fieldIndexMap['id'] = 0;
        foreach ($filter->getConditions() as $condition) {
            if (!empty($groups[$counter]) && $condition->getChainOperator() === 'OR') {
                ++$counter;
            }

            // Only index columns can be filtered.
            if (!array_key_exists($condition->getColumn(), $fieldIndexMap)) {
                throw new QueryFilterInvalidParamException('Field ' . $condition->getColumn() . ' is not indexed!');
            }

            $groups[$counter][] = $condition;
        }

        return $groups;
    }

    /**
     * Retrieves group query parts.
     *
     * @param array $conditionGroups
     * @param array $indexMap
     *
     * @return array
     */
    protected function getQueryParts(array $conditionGroups, array $indexMap): array
    {
        $parts = [];

        foreach ($conditionGroups as $group) {
            $subPart = [];

            foreach ($group as $condition) {
                $subPart[] = $this->getQueryPart($condition, $indexMap);
            }

            if (!empty($subPart)) {
                $parts[] = $subPart;
            }
        }

        return $parts;
    }

    /**
     * Generates where statement.
     *
     * @param array $queryParts
     *
     * @return string
     */
    protected function generateWhereStatement(array $queryParts): string
    {
        $where = '';

        foreach ($queryParts as $index => $part) {
            $subWhere = '';

            if ($index > 0) {
                $subWhere .= ' OR ';
            }

            $subWhere .= $part[0];
            $count = count($part);
            for ($i = 1; $i < $count; ++$i) {
                $subWhere .= ' AND ' . $part[$i];
            }

            $where .= $subWhere;
        }

        return $where;
    }

    /**
     * Retrieves query part.
     *
     * @param QueryCondition $condition
     * @param array $indexMap
     *
     * @return string
     */
    protected function getQueryPart(QueryCondition $condition, array $indexMap): string
    {
        $column = $condition->getColumn();

        if ($column === 'id') {
            return 'id=' . $condition->getValue();
        }

        $part = 'index_' . $indexMap[$column] . ' ' . $condition->getOperator();
        if (!in_array($condition->getOperator(), [Operators::NULL, Operators::NOT_NULL], true)) {
            if (in_array($condition->getOperator(), [Operators::NOT_IN, Operators::IN], true)) {
                $part .= $this->getInOperatorValues($condition);
            } else {
                $part .= " '" . IndexHelper::castFieldValue($condition->getValue(), $condition->getValueType()) . "'";
            }
        }

        return $part;
    }

    /**
     * Handles values for the IN and NOT IN operators,
     *
     * @param QueryCondition $condition
     *
     * @return string
     */
    protected function getInOperatorValues(QueryCondition $condition): string
    {
        $values = array_map(
            function ($item) {
                if (is_string($item)) {
                    return "'" . pSQL($item) . "'";
                }

                return "'" . IndexHelper::castFieldValue($item, is_int($item) ? 'integer' : 'double') . "'";
            },
            $condition->getValue()
        );

        return '(' . implode(',', $values) . ')';
    }

    /**
     * Builds WHERE statement of SELECT query by separating AND and OR conditions.
     * Output format: (C1 AND C2) OR (C3 AND C4) OR (C5 AND C6 AND C7)
     *
     * @param array $groups array of condition groups
     * @param array $fieldIndexMap map of property indexes
     *
     * @return string fully formed WHERE statement
     */
    protected function buildWhereCondition(array $groups, array $fieldIndexMap): string
    {
        $whereStatement = '';
        foreach ($groups as $groupIndex => $group) {
            $conditions = [];
            foreach ($group as $condition) {
                $conditions[] = $this->addCondition($condition, $fieldIndexMap);
            }

            $whereStatement .= '(' . implode(' AND ', $conditions) . ')';

            if (\count($groups) !== 1 && $groupIndex < count($groups) - 1) {
                $whereStatement .= ' OR ';
            }
        }

        return $whereStatement;
    }

    /**
     * Retrieves db_name for DBAL.
     *
     * @return string
     */
    protected function getDbName(): string
    {
        return self::TABLE_NAME;
    }

    /**
     * Filters records by given condition.
     *
     * @param QueryCondition $condition query condition object
     * @param array $indexMap map of property indexes
     *
     * @return string a single WHERE condition
     */
    private function addCondition(QueryCondition $condition, array $indexMap): string
    {
        $column = $condition->getColumn();
        $columnName = $column === 'id' ? 'id' : 'index_' . $indexMap[$column];
        if ($column === 'id') {
            $conditionValue = (int) $condition->getValue();
        } else {
            $conditionValue = IndexHelper::castFieldValue($condition->getValue(), $condition->getValueType());
        }

        if (in_array($condition->getOperator(), [Operators::NOT_IN, Operators::IN], true)) {
            $values = array_map(function ($item) {
                if (is_string($item)) {
                    return "'" . pSQL($item) . "'";
                }

                if (is_int($item)) {
                    $val = IndexHelper::castFieldValue($item, 'integer');

                    return "'{$val}'";
                }

                $val = IndexHelper::castFieldValue($item, 'double');

                return "'{$val}'";
            }, $condition->getValue());
            $conditionValue = '(' . implode(',', $values) . ')';
        } else {
            $conditionValue = "'" . pSQL($conditionValue, true) . "'";
        }

        return $columnName . ' ' . $condition->getOperator()
            . (!in_array($condition->getOperator(), [Operators::NULL, Operators::NOT_NULL], true)
                ? $conditionValue : ''
            );
    }

    /**
     * Returns Adyen entity records that satisfy provided condition.
     *
     * @param string $condition Condition in format: KEY OPERATOR VALUE
     * @param QueryFilter|null $filter query filter object
     *
     * @return array array of Adyen entity records
     *
     * @throws QueryFilterInvalidParamException
     * @throws \PrestaShopDatabaseException
     */
    private function getRecordsByCondition(string $condition, ?QueryFilter $filter = null): array
    {
        $query = new \DbQuery();
        $query->select(implode(',', $this->getSelectColumns()))
            ->from(bqSQL($this->getDbName()))
            ->where($condition);
        $this->applyLimitAndOrderBy($query, $filter);

        $result = \Db::getInstance()->executeS($query);

        return !empty($result) ? $result : [];
    }

    /**
     * Applies limit and order by statements to provided SELECT query.
     *
     * @param \DbQuery $query SELECT query
     * @param QueryFilter|null $filter query filter object
     *
     * @throws QueryFilterInvalidParamException
     */
    private function applyLimitAndOrderBy(\DbQuery $query, ?QueryFilter $filter = null)
    {
        if ($filter) {
            $limit = (int) $filter->getLimit();

            if ($limit) {
                $query->limit($limit, $filter->getOffset());
            }

            $orderByColumn = $filter->getOrderByColumn();
            if ($orderByColumn) {
                $indexedColumn = $orderByColumn === 'id' ? 'id' : $this->getIndexMapping($orderByColumn);
                if (empty($indexedColumn)) {
                    throw new QueryFilterInvalidParamException('Unknown or not indexed OrderBy column ' . $filter->getOrderByColumn());
                }

                $query->orderBy($indexedColumn . ' ' . $filter->getOrderDirection());
            }
        }
    }

    /**
     * Prepares data for inserting a new record or updating an existing one.
     *
     * @param Entity $entity adyen entity object
     * @param array $indexes array of index values
     *
     * @return array prepared record for inserting or updating
     */
    protected function prepareDataForInsertOrUpdate(Entity $entity, array $indexes): array
    {
        $record = ['data' => pSQL($this->serializeEntity($entity), true)];

        foreach ($indexes as $index => $value) {
            $record['index_' . $index] = $value !== null ? pSQL($value, true) : null;
        }

        return $record;
    }

    /**
     * Serializes Entity to string.
     *
     * @param Entity $entity Entity object to be serialized
     *
     * @return string Serialized entity
     */
    protected function serializeEntity(Entity $entity): string
    {
        return json_encode($entity->toArray());
    }

    /**
     * Unserializes entity form given string.
     *
     * @param string $data serialized entity as string
     *
     * @return Entity created entity object
     */
    private function unserializeEntity(string $data): Entity
    {
        $jsonEntity = json_decode($data, true);
        if (array_key_exists('class_name', $jsonEntity) && is_subclass_of($jsonEntity['class_name'], Entity::class)) {
            $entity = new $jsonEntity['class_name']();
        } else {
            $entity = new $this->entityClass();
        }

        /* @var Entity $entity */
        $entity->inflate($jsonEntity);

        return $entity;
    }
}
