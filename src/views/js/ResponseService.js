if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(function () {
    /**
     * The ResponseService constructor.
     *
     * @constructor
     */
    function ResponseService() {
        /**
         * Handles an error response from the submit action.
         *
         * @param {{error?: string, errorCode?: string, status?: number}} response
         * @returns {Promise<void>}
         */
        this.errorHandler = (response) => {
            if (response.status !== 401) {
                const { utilities, templateService, elementGenerator } = AdyenFE;
                let container = document.querySelector('.adlp-flash-message-wrapper');
                if (!container) {
                    container = elementGenerator.createElement('div', 'adlp-flash-message-wrapper');
                    templateService.getMainPage().prepend(container);
                }

                templateService.clearComponent(container);

                if (response.error) {
                    container.prepend(utilities.createFlashMessage(response.error, 'error'));
                } else if (response.errorCode) {
                    container.prepend(utilities.createFlashMessage('general.errors.' + response.errorCode, 'error'));
                } else {
                    container.prepend(utilities.createFlashMessage('general.errors.unknown', 'error'));
                }
            }

            return Promise.reject(response);
        };

        /**
         * Handles 401 response.
         *
         * @param {{error?: string, errorCode?: string}} response
         * @returns {Promise<void>}
         */
        this.unauthorizedHandler = (response) => {
            AdyenFE.utilities.create401FlashMessage(`general.errors.${response.errorCode}`);
            AdyenFE.state.goToState('connection');

            return Promise.reject({ ...response, status: 401 });
        };
    }

    AdyenFE.responseService = new ResponseService();
})();
