if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(function () {
    function TemplateService() {
        /**
         * The configuration object for all templates.
         */
        let templates = {};
        let mainPlaceholder = '#adl-main-page-holder';

        /**
         * Gets the main page DOM element.
         *
         * @returns {HTMLElement}
         */
        this.getMainPage = () => document.querySelector(mainPlaceholder);

        /**
         * Gets the main header element.
         *
         * @returns {HTMLElement}
         */
        this.getHeaderSection = () => document.getElementById('adl-header-section');

        /**
         * Clears the main page.
         *
         * @return {HTMLElement}
         */
        this.clearMainPage = () => {
            this.clearComponent(this.getMainPage());
        };

        /**
         * Sets the content templates.
         *
         * @param {{}} configuration
         */
        this.setTemplates = (configuration) => {
            templates = configuration;
        };

        /**
         * Gets the template with translated text.
         *
         * @param {string} templateId
         *
         * @return {string} HTML as string.
         */
        this.getTemplate = (templateId) => translate(templates[templateId]);

        /**
         * Removes component's children.
         *
         * @param {Element} component
         */
        this.clearComponent = (component) => {
            while (component.firstChild) {
                component.removeChild(component.firstChild);
            }
        };

        /**
         * Replaces all translation keys in the provided HTML.
         *
         * @param {string} html
         * @return {string}
         */
        const translate = (html) => {
            return html ? AdyenFE.translationService.translateHtml(html) : '';
        };
    }

    AdyenFE.templateService = new TemplateService();
})();
