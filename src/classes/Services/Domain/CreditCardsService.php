<?php

namespace AdyenPayment\Classes\Services\Domain;

use Adyen\Core\BusinessLogic\Domain\Multistore\StoreContext;
use Adyen\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Adyen\Core\Infrastructure\ORM\Interfaces\RepositoryInterface;
use Adyen\Core\Infrastructure\ORM\QueryFilter\Operators;
use Adyen\Core\Infrastructure\ORM\QueryFilter\QueryFilter;

/**
 * Class CreditCardsService
 *
 * @package AdyenPayment\Classes\Services\Domain
 */
class CreditCardsService
{
    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @param RepositoryInterface $paymentMethodRepository
     */
    public function __construct(RepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @return bool
     *
     * @throws QueryFilterInvalidParamException
     */
    public function creditCardEnabled(): bool
    {
        $queryFilter = new QueryFilter();
        $queryFilter->where('code', Operators::EQUALS, 'scheme')->where(
            'storeId',
            Operators::EQUALS,
            StoreContext::getInstance()->getStoreId()
        );

        $creditCard = $this->paymentMethodRepository->selectOne($queryFilter);

        return (bool)$creditCard;
    }
}
