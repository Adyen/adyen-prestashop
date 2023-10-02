$(document).ready(function () {
        let url = new URL(location.href),
            amazonCheckoutSessionId = url.searchParams.get('amazonCheckoutSessionId'),
            adyenWalletsService = new AdyenWallets.AdyenWalletsService(),
            prestaVersion = document.getElementsByClassName('adyen-presta-version')[0];

        if (prestaVersion && prestaVersion.value > '1.7.7') {
            moveExpressCheckoutButtons();
        }

        if (amazonCheckoutSessionId) {
            adyenWalletsService.mountAmazon(getData);
        } else {
            adyenWalletsService.mountElements(getData);
        }

        $(document).ajaxComplete(function (event, request, settings) {
            let method = adyenWalletsService.getAjaxUrlParam('controller', settings.url);
            if (method === 'product') {
                adyenWalletsService.mountElements(getData);
            }
        });

        function moveExpressCheckoutButtons() {
            let expressCheckoutDiv = document.getElementById('adyen-express-checkout'),
                prodQuantity = document.getElementsByClassName('product-quantity'),
                tempParent = prodQuantity[0].parentNode;
            if (expressCheckoutDiv) {
                tempParent.appendChild(expressCheckoutDiv);
            }
        }

        function getData() {
            let productDetails = JSON.parse(document.getElementById('product-details').dataset.product),
                product =
                    {
                        'id_product': productDetails.id_product,
                        'id_product_attribute': productDetails.id_product_attribute,
                        'id_customization': productDetails.id_customization,
                        'quantity_wanted': productDetails.quantity_wanted,
                        'price_amount': productDetails.price_amount
                    };

            return JSON.stringify(product);
        }
    }
)