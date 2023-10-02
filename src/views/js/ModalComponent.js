if (!window.AdyenFE) {
    window.AdyenFE = {};
}

if (!window.AdyenFE.components) {
    window.AdyenFE.components = {};
}

(function () {
    /**
     * @typedef ButtonConfig
     * @property {string} label
     * @property {string?} className
     * @property {'primary' | 'secondary'} type
     * @property {() => void} onClick
     */

    /**
     * @typedef ModalConfiguration
     * @property {string?} title
     * @property {string?} className
     * @property {HTMLElement} content The content of the body.
     * @property {ButtonConfig[]} buttons Footer buttons.
     * @property {(modal: HTMLDivElement) => void?} onOpen Will fire after the modal is opened.
     * @property {() => boolean?} onClose Will fire before the modal is closed.
     *      If the return value is false, the modal will not be closed.
     * @property {boolean} [footer=false] Indicates whether to use footer. Defaults to false.
     * @property {boolean} [canClose=true] Indicates whether to use an (X) button or click outside the modal
     * to close it. Defaults to true.
     * @property {boolean} [fullWidthBody=false] Indicates whether to make body full width
     */

    /**
     * @param {ModalConfiguration} configuration
     * @constructor
     */
    function ModalComponent(configuration) {
        const { templateService, translationService, utilities, elementGenerator } = AdyenFE,
            config = configuration;

        /**
         * @type {HTMLDivElement}
         */
        let modal;

        /**
         * Closes the modal on Esc key.
         *
         * @param {KeyboardEvent} event
         */
        const closeOnEsc = (event) => {
            if (event.key === 'Escape') {
                this.close();
            }
        };

        /**
         * Closes the modal.
         */
        this.close = () => {
            if (!config.onClose || config.onClose()) {
                window.removeEventListener('keyup', closeOnEsc);
                modal?.remove();
            }
        };

        /**
         * Opens the modal.
         */
        this.open = () => {
            const modalTemplate =
                '<div id="adl-modal" class="adl-modal adls--hidden">\n' +
                '    <div class="adlp-modal-content">' +
                '        <button class="adl-button adlt--ghost adlm--icon-only adlp-close-button"><span></span></button>' +
                '        <div class="adlp-title"></div>' +
                '        <div class="adlp-body"></div>' +
                '        <div class="adlp-footer"></div>' +
                '    </div>' +
                '</div>';

            modal = AdyenFE.elementGenerator.createElementFromHTML(modalTemplate);
            const closeBtn = modal.querySelector('.adlp-close-button'),
                closeBtnSpan = modal.querySelector('.adlp-close-button span'),
                title = modal.querySelector('.adlp-title'),
                body = modal.querySelector('.adlp-body'),
                footer = modal.querySelector('.adlp-footer');

            utilities.showElement(modal);
            if (config.canClose === false) {
                utilities.hideElement(closeBtn);
            } else {
                window.addEventListener('keyup', closeOnEsc);
                closeBtn.addEventListener('click', this.close);
                closeBtnSpan.style.display = 'flex';
                modal.addEventListener('click', (event) => {
                    if (event.target.id === 'adl-modal') {
                        event.preventDefault();
                        this.close();

                        return false;
                    }
                });
            }

            if (config.title) {
                title.innerHTML = translationService.translate(config.title);
            } else {
                utilities.hideElement(title);
            }

            if (config.className) {
                modal.classList.add(config.className);
            }

            body.append(...(Array.isArray(config.content) ? config.content : [config.content]));
            if (configuration.fullWidthBody) {
                body.classList.add('adlm--full-width');
            }

            if (config.footer === false || !config.buttons) {
                utilities.hideElement(footer);
            } else {
                config.buttons.forEach((button) => {
                    footer.appendChild(elementGenerator.createButton(button));
                });
            }

            templateService.getMainPage().parentNode.appendChild(modal);
            if (config.onOpen) {
                config.onOpen(modal);
            }
        };
    }

    AdyenFE.components.Modal = {
        /** @param {ModalConfiguration} config */
        create: (config) => new ModalComponent(config)
    };
})();
