<?php

namespace AdyenPayment\Classes\Services\Integration\PaymentProcessors;

use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Factory\PaymentLinkRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentLink\Models\PaymentLinkRequestContext;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Factory\PaymentRequestBuilder;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\LineItem;
use Adyen\Core\BusinessLogic\Domain\Checkout\PaymentRequest\Models\StartTransactionRequestContext;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentRequest\LineItemsProcessor as LineItemsProcessorInterface;
use Adyen\Core\BusinessLogic\Domain\Integration\Processors\PaymentLinkRequest\LineItemsProcessor as PaymentLinkLineItemsProcessorInterface;
use Cart;

/**
 * Class LineItemsProcessor
 *
 * @package AdyenPayment\Integration\PaymentProcessors
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
        $cart = new Cart($context->getReference());

        $builder->setLineItems($this->getLineItemsFromCart($cart));
    }

    public function processPaymentLink(PaymentLinkRequestBuilder $builder, PaymentLinkRequestContext $context): void
    {
        $cart = new Cart($context->getReference());

        $builder->setLineItems($this->getLineItemsFromCart($cart));
    }

    /**
     * @param Cart $cart
     *
     * @return LineItem[]
     */
    private function getLineItemsFromCart(Cart $cart): array
    {
        $basketContent = $cart->getProducts();
        $lineItems = [];

        foreach ($basketContent as $item) {
            $image = new \Image($item['id_image']);
            $category = new \Category($item['id_category_default']);
            $amountExcludingTax = $item['price_with_reduction_without_tax'];
            $amountIncludingTax = $item['price_with_reduction'];
            $taxAmount = $amountIncludingTax - $amountExcludingTax;

            $lineItems[] = new LineItem(
                $item['id_product'] ?? '',
                $amountExcludingTax * 100,
                $amountIncludingTax * 100,
                $taxAmount * 100,
                $item['rate'] * 100,
                strip_tags($item['description_short']) ?? '',
                _PS_IMG_DIR_ . $image->getImgPath() . '.' . $image->image_format ?? '',
                $category->getName($cart->id_lang) ?? '',
                $item['quantity'] ?? 0
            );
        }

        return $lineItems;
    }
}
