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

            if(!(AdyenFE.hasOwnProperty(name) && typeof AdyenFE[name] === 'function')) {
                return null;
            }

            return new AdyenFE[name](configuration);
        };
    }

    AdyenFE.pageControllerFactory = new PageControllerFactory();
})();
