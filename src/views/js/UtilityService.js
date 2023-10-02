if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(function () {
    function UtilityService() {
        let loaderCount = 0;

        /**
         * Shows the HTML node.
         *
         * @param {HTMLElement} element
         */
        this.showElement = (element) => {
            element?.classList.remove('adls--hidden');
        };

        /**
         * Hides the HTML node.
         *
         * @param {HTMLElement} element
         */
        this.hideElement = (element) => {
            element?.classList.add('adls--hidden');
        };

        /**
         * Enables loading spinner.
         */
        this.showLoader = () => {
            if (loaderCount === 0) {
                this.showElement(document.getElementById('adl-spinner'));
            }

            loaderCount++;
        };

        /**
         * Hides loading spinner.
         */
        this.hideLoader = () => {
            loaderCount--;
            if (loaderCount === 0) {
                this.hideElement(document.getElementById('adl-spinner'));
            }
        };

        /**
         * Shows flash message.
         *
         * @note Only one flash message will be shown at the same time.
         *
         * @param {string} message
         * @param {'error' | 'warning' | 'success'} status
         * @param {number?} clearAfter Time in ms to remove alert message.
         */
        this.createFlashMessage = (message, status, clearAfter) => {
            return AdyenFE.elementGenerator.createFlashMessage(message, status, clearAfter);
        };

        /**
         * Creates the 401 error flash message.
         *
         * @param {string} message
         */
        this.create401FlashMessage = (message) => {
            this.remove401Message();
            const messageElement = AdyenFE.elementGenerator.createFlashMessage(message, 'error');
            messageElement.classList.add('adlv--401-error');
            AdyenFE.templateService.getHeaderSection().append(messageElement);
        };

        /**
         * Removes the 401 flash message.
         */
        this.remove401Message = () => {
            AdyenFE.templateService
                .getHeaderSection()
                .querySelectorAll(`.adlv--401-error`)
                .forEach((e) => e.remove());
        };

        /**
         * Creates a toaster message.
         *
         * @param {string} message A message translation key.
         */
        this.createToasterMessage = (message) => {
            document.getElementById('adl-page').append(AdyenFE.elementGenerator.createToaster(message));
        };

        /**
         * Updates a form's footer state based on the number of changes.
         *
         * @param {number} numberOfChanges
         * @param {boolean} disableCancel
         */
        this.renderFooterState = (numberOfChanges, disableCancel = true) => {
            if (numberOfChanges) {
                document.querySelector('.adl-form-footer .adlp-changes-count')?.classList.add('adls--active');
                document.querySelector('.adl-form-footer .adlp-actions .adlp-save').disabled = false;
                document.querySelector('.adl-form-footer .adlp-actions .adlp-cancel').disabled = false;
            } else {
                document.querySelector('.adl-form-footer .adlp-changes-count')?.classList.remove('adls--active');
                document.querySelector('.adl-form-footer .adlp-actions .adlp-save').disabled = true;
                document.querySelector('.adl-form-footer .adlp-actions .adlp-cancel').disabled = disableCancel;
            }
        };

        /**
         * Creates deep clone of an object with object's properties.
         * Removes object's methods.
         *
         * @note Object cannot have values that cannot be converted to json (undefined, infinity etc).
         *
         * @param {object} obj
         * @return {object}
         */
        this.cloneObject = (obj) => JSON.parse(JSON.stringify(obj || {}));

        /**
         * Gets the first ancestor element with the corresponding class name.
         *
         * @param {HTMLElement} element
         * @param {string} className
         * @return {HTMLElement}
         */
        this.getAncestor = (element, className) => {
            let parent = element?.parentElement;

            while (parent) {
                if (parent.classList.contains(className)) {
                    break;
                }

                parent = parent.parentElement;
            }

            return parent;
        };

        /**
         * Check if two arrays are equal.
         *
         * @param {any[]} source
         * @param {any[]} target
         * @return {boolean} TRUE if arrays are equal; otherwise, FALSE.
         */
        this.compareArrays = (source, target) => {
            if (source.length !== target.length) {
                return false;
            }

            const sortedSource = source.slice().sort();
            const sortedTarget = target.slice().sort();

            for (let i = 0; i < sortedSource.length; i++) {
                if (sortedSource[i] !== sortedTarget[i]) {
                    return false;
                }
            }

            return true;
        };
    }

    AdyenFE.utilities = new UtilityService();
})();
