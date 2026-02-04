<?php

namespace AdyenPayment\Classes\Repositories;

/**
 * Class LogsRepository
 */
class LogsRepository
{
    private const LIMIT = 10000;

    /**
     * Retrieves logs from PrestaShop database.
     *
     * @param \DateTime $currentDate
     * @param $currentOffset
     *
     * @return array|bool|\mysqli_result|\PDOStatement|resource|null
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getLogs(\DateTime $currentDate, $currentOffset)
    {
        $query = new \DbQuery();
        $query->select('*')->from('log')->where("date_add > '" . pSQL($currentDate->format('Y-m-d H:i:s')) . "'");
        $query->limit(self::LIMIT, $currentOffset);

        return \Db::getInstance()->executeS($query);
    }
}
