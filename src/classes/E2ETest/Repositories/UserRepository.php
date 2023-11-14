<?php

namespace AdyenPayment\Classes\E2ETest\Repositories;


/**
 * Class UserRepository
 *
 * @package AdyenPayment\E2ETest\Repositories
 */
class UserRepository
{
    /**
     * Shopware\Models\User\Repository
     *
     * @var Repository
     */
    private $shopwareRepository;

    /**
     * UserRepository constructor.
     */
    public function __construct()
    {
        $this->shopwareRepository = Shopware()->Models()->getRepository(User::class);
    }

    /**
     * Returns array of Shopware users from database
     *
     * @return User[]
     */
    public function getShopwareAuthUsers(): array
    {
        $query = $this->shopwareRepository->createQueryBuilder('user');

        return $query->getQuery()->getResult();
    }
}
