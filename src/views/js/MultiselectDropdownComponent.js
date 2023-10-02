if (!window.AdyenFE) {
    window.AdyenFE = {};
}

if (!window.AdyenFE.components) {
    window.AdyenFE.components = {};
}

(function () {
    const preventDefaults = (e) => {
        e.preventDefault();
        e.stopPropagation();
    };

    /**
     * @typedef MultiselectDropdownComponentModel
     *
     * @property {Option[]} options
     * @property {string?} name
     * @property {string[]?} values
     * @property {string?} placeholder
     * @property {string?} selectedText
     * @property {(values: string[]) => void} onChange
     * @property {boolean?} updateTextOnChange
     * @property {boolean?} useAny
     * @property {string?} className
     * */

    /**
     * Multiselect dropdown component.
     *
     * @param {MultiselectDropdownComponentModel} params
     * @returns {HTMLElement}
     * @constructor
     */
    const MultiselectDropdownComponent = ({
        options,
        name = '',
        values = [],
        placeholder,
        selectedText,
        onChange,
        updateTextOnChange = true,
        useAny = true,
        className = ''
    }) => {
        const { elementGenerator: generator, translationService } = AdyenFE;

        options.forEach((option) => {
            option.label = translationService.translate(option.label);
        });

        const handleDisplayedItems = (fireChange = true) => {
            hiddenInput.value = selectedItems.map((item) => item.value).join(',');
            if (useAny) {
                const anyItem = list.querySelector('.adlt--any');
                if (selectedItems.length > 0) {
                    anyItem?.classList.remove('adls--selected');
                } else {
                    anyItem.classList.toggle('adls--selected');

                    list.querySelectorAll(':not(.adlt--any)').forEach((listItem) => {
                        listItem.classList.remove('adls--selected');
                        if (anyItem.classList.contains('adls--selected')) {
                            listItem.classList.add('adls--disabled');
                        } else {
                            listItem.classList.remove('adls--disabled');
                        }
                    });
                }
            }

            let textToDisplay;
            if (selectedItems.length > 2) {
                textToDisplay = translationService.translate(selectedText, [selectedItems.length]);
            } else {
                textToDisplay =
                    selectedItems.map((item) => item.label).join(', ') || translationService.translate(placeholder);
            }

            updateTextOnChange && (selectButton.firstElementChild.innerHTML = textToDisplay);
            fireChange && onChange?.(selectedItems.map((item) => item.value));
        };

        const createListItem = (additionalClass, label, htmlKey) => {
            const item = generator.createElement('li', `adlp-dropdown-list-item ${additionalClass}`, label, htmlKey, [
                generator.createElement('input', 'adlp-checkbox', '', { type: 'checkbox' })
            ]);
            list.append(item);
            return item;
        };

        const renderOption = (option) => {
            const listItem = createListItem(values?.includes(option.value) ? 'adls--selected' : '', option.label, null);

            selectedItems.forEach((item) => {
                if (option.value === item.value) {
                    listItem.classList.add('adls--selected');
                }
            });

            listItem.addEventListener('click', () => {
                listItem.classList.toggle('adls--selected');
                listItem.childNodes[0].checked = listItem.classList.contains('adls--selected');
                if (!selectedItems.includes(option)) {
                    selectedItems.push(option);
                } else {
                    const index = selectedItems.indexOf(option);
                    selectedItems.splice(index, 1);
                }

                handleDisplayedItems();
            });
        };

        let selectedItems = options.filter((option) => values?.includes(option.value));

        const hiddenInput = generator.createElement('input', 'adlp-hidden-input', '', {
            type: 'hidden',
            name,
            value: values?.join(',') || ''
        });
        const wrapper = generator.createElement('div', 'adl-multiselect-dropdown' + (className ? ' ' + className : ''));
        const selectButton = generator.createElement(
            'button',
            'adlp-dropdown-button adlp-field-component',
            '',
            {
                type: 'button'
            },
            [generator.createElement('span', selectedItems ? 'adls--selected' : '', placeholder)]
        );

        const list = generator.createElement('ul', 'adlp-dropdown-list');
        if (useAny) {
            const anyItem = createListItem(
                'adlt--any' + (!values?.length ? ' adls--selected' : ''),
                'general.any',
                null
            );

            anyItem.addEventListener('click', () => {
                selectedItems = [];
                anyItem.childNodes[0].checked = anyItem.classList.contains('adls--selected');

                handleDisplayedItems();
            });
        }

        options.forEach(renderOption);

        selectButton.addEventListener('click', (event) => {
            preventDefaults(event);
            list.classList.toggle('adls--show');
            wrapper.classList.toggle('adls--active');
        });

        window.addEventListener('click', (event) => {
            if (!list.contains(event.target) && event.target !== list) {
                list.classList.remove('adls--show');
                wrapper.classList.remove('adls--active');
            }
        });

        wrapper.append(hiddenInput, selectButton, list);

        values?.length && handleDisplayedItems(false);

        return wrapper;
    };

    AdyenFE.components.MultiselectDropdown = {
        /**
         * @param {MultiselectDropdownComponentModel} config
         * @returns {HTMLElement}
         */
        create: (config) => MultiselectDropdownComponent(config)
    };
})();
