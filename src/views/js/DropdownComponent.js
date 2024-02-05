if (!window.AdyenFE) {
    window.AdyenFE = {};
}

if (!window.AdyenFE.components) {
    window.AdyenFE.components = {};
}

(function () {
    /**
     * @typedef DropdownComponentModel
     *
     * @property {Option[]} options
     * @property {string?} name
     * @property {string?} value
     * @property {string?} placeholder
     * @property {(value: string) => void?} onChange
     * @property {boolean?} updateTextOnChange
     * @property {boolean?} searchable
     */

    /**
     * Single-select dropdown component.
     *
     * @param {DropdownComponentModel} props
     *
     * @constructor
     */
    const DropdownComponent = ({
        options,
        name,
        value = '',
        placeholder,
        onChange,
        updateTextOnChange = true,
        searchable = false
    }) => {
        const { elementGenerator: generator, translationService } = AdyenFE;
        const filterItems = (text) => {
            const filteredItems = text
                ? options.filter((option) => option.label.toLowerCase().includes(text.toLowerCase()))
                : options;

            if (filteredItems.length === 0) {
                selectButton.classList.add('adls--no-results');
            } else {
                selectButton.classList.remove('adls--no-results');
            }

            renderOptions(filteredItems);
        };

        const renderOptions = (options) => {
            list.innerHTML = '';
            options.forEach((option) => {
                const listItem = generator.createElement(
                    'li',
                    'adlp-dropdown-list-item' + (option === selectedItem ? ' adls--selected' : ''),
                    option.label,
                    { dataset: { value: option.value } }
                );
                list.append(listItem);

                listItem.addEventListener('click', () => {
                    hiddenInput.value = option.value;
                    updateTextOnChange && (buttonSpan.innerHTML = translationService.translate(option.label));
                    list.classList.remove('adls--show');
                    list.childNodes.forEach((node) => node.classList.remove('adls--selected'));
                    listItem.classList.add('adls--selected');
                    wrapper.classList.remove('adls--active');
                    buttonSpan.classList.add('adls--selected');
                    selectButton.classList.remove('adls--search-active');
                    onChange && onChange(option.value);
                });
            });
        };

        const hiddenInput = generator.createElement('input', 'adlp-hidden-input', '', { type: 'hidden', name, value });
        const wrapper = generator.createElement('div', 'adl-single-select-dropdown');

        const selectButton = generator.createElement('button', 'adlp-dropdown-button adlp-field-component', '', {
            type: 'button',
            dataset: {selection:  "merchant-account-selection"}
        });
        const selectedItem = options.find((option) => option.value === value);
        const buttonSpan = generator.createElement(
            'span',
            selectedItem ? 'adls--selected' : '',
            selectedItem ? selectedItem.label : placeholder
        );
        selectButton.append(buttonSpan);

        const searchInput = generator.createElement('input', 'adl-text-input', '', {
            type: 'text',
            placeholder: translationService.translate('general.search')
        });
        searchInput.addEventListener('input', (event) => filterItems(event.currentTarget?.value || ''));
        if (searchable) {
            selectButton.append(searchInput);
        }

        const list = generator.createElement('ul', 'adlp-dropdown-list');
        renderOptions(options);

        selectButton.addEventListener('click', () => {
            list.classList.toggle('adls--show');
            wrapper.classList.toggle('adls--active');
            if (searchable) {
                selectButton.classList.toggle('adls--search-active');
                if (selectButton.classList.contains('adls--search-active')) {
                    searchInput.focus();
                    searchInput.value = '';
                    filterItems('');
                }
            }
        });

        document.documentElement.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target) && event.target !== wrapper) {
                list.classList.remove('adls--show');
                wrapper.classList.remove('adls--active');
                selectButton.classList.remove('adls--search-active');
            }
        });

        wrapper.append(hiddenInput, selectButton, list);

        return wrapper;
    };

    AdyenFE.components.Dropdown = {
        /**
         * @param {DropdownComponentModel} config
         * @returns {HTMLElement}
         */
        create: (config) => DropdownComponent(config)
    };
})();
