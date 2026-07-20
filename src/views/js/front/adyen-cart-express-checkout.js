$(document).ready(function () {
    let adyenWalletsService = new AdyenWallets.AdyenWalletsService();

    adyenWalletsService.mountElements();

    $(document).ajaxComplete(function (event, request, settings) {
        let method = adyenWalletsService.getAjaxUrlParam('action', settings.url);
        if (method === 'refresh') {
            setTimeout(() => {
                adyenWalletsService.mountElements();
            }, "1000");
        }
    });
})