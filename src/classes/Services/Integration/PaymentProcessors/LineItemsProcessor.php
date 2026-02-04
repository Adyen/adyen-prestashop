<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\LineItem;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\LineItemsProcessor as PaymentLinkLineItemsProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\LineItemsProcessor as LineItemsProcessorInterface;

/**
 * Class LineItemsProcessor
 */
class LineItemsProcessor implements LineItemsProcessorInterface, PaymentLinkLineItemsProcessorInterface
{
    /**
     * @param PaymentRequestBuilder $builder
     * @param StartTransactionRequestContext $context
     *
     * @return void
     */
    public function process(PaymentRequestBuilder $builder, StartTransactionRequestContext $context): void
    {
        $builder->setLineItems($this->getLineItemsFromCart((int) $context->getReference()));
    }

    public function processPaymentLink(PaymentLinkRequestBuilder $builder, PaymentLinkRequestContext $context): void
    {
        $builder->setLineItems($this->getLineItemsFromCart((int) $context->getReference()));
    }

    /**
     * @param int $cartId
     *
     * @return LineItem[]
     */
    private function getLineItemsFromCart(int $cartId): array
    {
        $cart = new \Cart($cartId);
        $basketContent = $cart->getProducts();
        $lineItems = [];

        foreach ($basketContent as $item) {
            $image = new \Image($item['id_image']);
            $category = new \Category($item['id_category_default']);
            $amountExcludingTax = $item['price_with_reduction_without_tax'];
            $amountIncludingTax = $item['price_with_reduction'];
            $taxAmount = $amountIncludingTax - $amountExcludingTax;
            $description = strip_tags($item['description_short']);

            $lineItems[] = new LineItem(
                $item['id_product'] ?? '',
                $amountExcludingTax * 100,
                $amountIncludingTax * 100,
                $taxAmount * 100,
                $item['rate'] * 100,
                $description ?: strip_tags($item['name']),
                _PS_IMG_DIR_ . $image->getImgPath() . '.' . $image->image_format,
                $category->getName($cart->id_lang),
                $item['quantity'] ?? 0
            );
        }

        return $lineItems;
    }
}
