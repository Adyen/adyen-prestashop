if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(function () {
    function PageControllerFactory() {
        /**
         * Instantiates page controller;
         *
         * @param {string} controller
         * @param {Record<string, any>} configuration
         */
        this.getInstance = (controller, configuration) => {
            let parts = controller.split('-');
            let name = '';
            for (let part of parts) {
                part = part.charAt(0).toUpperCase() + part.slice(1);
                name += part;
            }

            name += 'Controller';

            return AdyenFE[name] ? new AdyenFE[name](configuration) : null;
        };
    }

    AdyenFE.pageControllerFactory = new PageControllerFactory();
})();
