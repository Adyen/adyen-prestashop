<?php

namespace AdyenPayment\Classes\E2ETest\Services;

use Adyen\Core\BusinessLogic\DataAccess\TransactionLog\Entities\TransactionLog;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Adyen\Core\Infrastructure\ORM\QueryFilter\Operators;
use Adyen\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use Adyen\Core\Infrastructure\ORM\RepositoryRegistry;
use Adyen\Core\Infrastructure\TaskExecution\QueueItem;
use AdyenPayment\Classes\Repositories\TransactionLogRepository;

class TransactionLogService
{
    /**
     * @var TransactionLogRepository
     */
    private $repository;

    /**
     * @throws RepositoryNotRegisteredException
     */
    public function __construct()
    {
        $this->repository = RepositoryRegistry::getRepository(TransactionLog::getClassName());
    }

    /**
     * @param string $merchantReference
     * @param string $eventCode
     * @return bool
     * @throws QueryFilterInvalidParamException
     */
    public function findLogsByMerchantReference(string $merchantReference, string $eventCode): bool
    {
        $filter = new QueryFilter();
        $filter->where('merchantReference', Operators::EQUALS, $merchantReference);

        /** @var TransactionLog[] $transactionLogs */
        $transactionLogs = $this->repository->select($filter);
        foreach ($transactionLogs as $transactionLog){
            if($transactionLog->getEventCode() === $eventCode
                && $transactionLog->getQueueStatus() !== QueueItem::QUEUED
                && $transactionLog->getQueueStatus() !== QueueItem::IN_PROGRESS
            )
            {
                return true;
            }
        }

        return false;
    }
}