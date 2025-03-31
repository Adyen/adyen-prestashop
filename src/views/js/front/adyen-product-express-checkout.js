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
            let productDetailsElement = document.getElementById('product-details');

            if (!productDetailsElement || !productDetailsElement.dataset.product) {
                console.error('Error: Product details element or data not found.');
                return JSON.stringify({});
            }

            let productDetails;

            try {
                productDetails = JSON.parse(productDetailsElement.dataset.product);
            } catch (error) {
                console.error('Error while parsing product JSON data:', error);
                return JSON.stringify({});
            }

            let product = {
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