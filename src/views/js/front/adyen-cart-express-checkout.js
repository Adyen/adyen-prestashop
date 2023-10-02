$(document).ready(function () {
    let url = new URL(location.href),
        amazonCheckoutSessionId = url.searchParams.get('amazonCheckoutSessionId'),
        adyenWalletsService = new AdyenWallets.AdyenWalletsService();

    if (amazonCheckoutSessionId) {
        adyenWalletsService.mountAmazon();
    } else {
        adyenWalletsService.mountElements();
    }

    $(document).ajaxComplete(function (event, request, settings) {
        let method = adyenWalletsService.getAjaxUrlParam('action', settings.url);
        if (method === 'refresh') {
            setTimeout(() => {
                adyenWalletsService.mountElements();
            }, "1000");
        }
    });
})