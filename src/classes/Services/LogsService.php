<?php

namespace AdyenPayment\Classes\Services;

use AdyenPayment\Classes\Repositories\LogsRepository;

/**
 * Class LogsService
 */
class LogsService
{
    private const LOG_NUMBER_DAYS = 7;
    private const LIMIT = 10000;

    /** @var LogsRepository */
    private $logsRepository;

    public function __construct(LogsRepository $logsRepository)
    {
        $this->logsRepository = $logsRepository;
    }

    /**
     * @return string
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getLogs(): string
    {
        $result[] = [];
        $currentDate = new \DateTime();
        $currentDate->sub(new \DateInterval('P' . self::LOG_NUMBER_DAYS . 'D'));
        $currentOffset = 0;

        $logs = $this->logsRepository->getLogs($currentDate, $currentOffset);
        while (!empty($logs)) {
            $result[] = $logs;
            $currentOffset += self::LIMIT;
            $logs = $this->logsRepository->getLogs($currentDate, $currentOffset);
        }

        $result = array_merge(...$result);

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
