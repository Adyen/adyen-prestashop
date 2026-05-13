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
                prodQuantity = document.getElementsByClassName('product-quantity');

            if (prodQuantity.length === 0) {
                prodQuantity = document.getElementsByClassName('product-actions__quantity');
            }

            let tempParent = prodQuantity[0].parentNode;

            if (expressCheckoutDiv) {
                tempParent.appendChild(expressCheckoutDiv);
            }
        }

        function getData() {
            // CLASSIC theme
            let productDetailsElement = document.getElementById('product-details');
            if (productDetailsElement && productDetailsElement.dataset && productDetailsElement.dataset.product) {
                try {
                    let d = JSON.parse(productDetailsElement.dataset.product);
                    return JSON.stringify({
                        'id_product': d.id_product,
                        'id_product_attribute': d.id_product_attribute,
                        'id_customization': d.id_customization,
                        'quantity_wanted': d.quantity_wanted,
                        'price_amount': d.price_amount
                    });
                } catch (error) {
                    console.error('Error while parsing product JSON data:', error);
                }
            }

            // HUMMINGBIRD theme
            let idProductInput = document.getElementById('product_page_product_id');
            if (!idProductInput) {
                console.error('Error: Product details element or data not found.');
                return JSON.stringify({});
            }

            let form = document.getElementById('add-to-cart-or-refresh');
            let attrInput = form && form.querySelector('input[name="id_product_attribute"]');
            let customizationInput = document.getElementById('product_customization_id');
            let qtyInput = document.getElementById('quantity_wanted');
            let priceMeta = document.querySelector('meta[property="product:price:amount"]');

            return JSON.stringify({
                'id_product': parseInt(idProductInput.value, 10) || 0,
                'id_product_attribute': attrInput ? (parseInt(attrInput.value, 10) || 0) : 0,
                'id_customization': customizationInput ? (parseInt(customizationInput.value, 10) || 0) : 0,
                'quantity_wanted': qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1,
                'price_amount': priceMeta ? (parseFloat(priceMeta.getAttribute('content')) || 0) : 0
            });
        }
    }
)
