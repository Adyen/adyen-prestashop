if (!window.AdyenFE) {
    window.AdyenFE = {};
}

if (!window.AdyenFE.components) {
    window.AdyenFE.components = {};
}

(function () {
    /**
     * @typedef TableFilterParams
     *
     * @property {Option[]} options
     * @property {string?} name
     * @property {string[]?} values
     * @property {(values: string[]) => void} onChange
     * @property {string?} label
     * @property {string?} selectPlaceholder
     * @property {boolean?} isMultiselect
     */
    const { elementGenerator: generator, components } = AdyenFE;

    const preventDefaults = (e) => {
        e.preventDefault();
        e.stopPropagation();
    };

    /**
     * Compares contents of two arrays.
     *
     * @param {string[]} a1
     * @param {string[]} a2
     * @return {boolean}
     */
    const arraysHaveSameContent = (a1, a2) => {
        if (a1.length !== a2.length) {
            return false;
        }

        for (let i = 0; i < a1.length; i++) {
            if (!a2.includes(a1[i])) {
                return false;
            }
        }

        for (let i = 0; i < a2.length; i++) {
            if (!a1.includes(a2[i])) {
                return false;
            }
        }

        return true;
    };

    /**
     * Gets the label to be displayed in the main button.
     *
     * @param {string} label The default label when nothing is selected.
     * @param {string} labelPlural Label when more than two options are selected.
     * @param {Option[]} options Possible options.
     * @param {string[]} values Selected values.
     * @return {string}
     */
    const getButtonLabel = (label, labelPlural, options, values) => {
        if (values.length === 0) {
            return label;
        }

        if (values.length < 2) {
            return values.map((value) => options.find((o) => o.value === value).label).join(', ');
        }

        return `${values.length} ${labelPlural.toLowerCase()}`;
    };

    /**
     * Gets the label to be displayed in the main button.
     *
     * @param {string} label The default label when nothing is selected.
     * @param {Option[]} options Possible options.
     * @param {string[]} values Selected values.
     * @return {string}
     */
    const getButtonTooltip = (label, options, values) => {
        if (values.length === 0) {
            return '';
        }

        if (values.length < 2) {
            return label;
        }

        return values.map((value) => options.find((o) => o.value === value).label).join(', ');
    };

    /**
     * Renders the main button.
     *
     * @param {string} label
     * @param {string} labelPlural
     * @param {Option[]} options
     * @param {string[]} values
     * @param {() => void} onClick
     * @param {() => void} onClear
     * @return {HTMLButtonElement}
     */
    const renderButton = (label, labelPlural, options, values, onClick, onClear) => {
        const button = generator.createButton({
            type: 'secondary',
            className: 'adlp-filter-button' + (values.length > 0 ? ' adls--selected' : ''),
            label: getButtonLabel(label, labelPlural, options, values),
            onClick: onClick
        });

        const deleteButton = generator.createElement('button', 'adlp-delete-text-button');
        deleteButton.addEventListener('click', (e) => {
            preventDefaults(e);
            onClear();
        });

        button.append(
            deleteButton,
            generator.createElement('span', 'adlp-tooltip', getButtonTooltip(label, options, values))
        );

        return button;
    };

    /**
     * Renders selected options.
     *
     * @param {Option[]} options
     * @param {string[]} selectedValues
     * @param {(value: string) =>, void} onRemove
     * @return {HTMLElement[]}
     */
    const getOptionsList = (options, selectedValues, onRemove) => {
        return selectedValues.map((value) => {
            const deleteButton = generator.createElementFromHTML('<button class="adlt--remove-item"></button>');
            deleteButton.addEventListener('click', (e) => {
                preventDefaults(e);
                onRemove(value);
            });

            const element = generator.createElement(
                'li',
                'adlp-selected-data-item',
                options.find((o) => o.value === value).label,
                null
            );

            element.prepend(deleteButton);

            return element;
        });
    };

    /**
     * Creates a table filter element.
     *
     * @param {TableFilterParams} args
     * @return {HTMLElement}
     */
    const create = ({
        options,
        name = '',
        values = [],
        onChange,
        label = '',
        labelPlural = '',
        selectPlaceholder = '',
        isMultiselect = true
    }) => {
        let selectedValues = [...values];

        const createDropdown = () =>
            components.Dropdown.create({
                options,
                name,
                placeholder: selectPlaceholder,
                onChange: handleSelectChange,
                value: isMultiselect ? undefined : selectedValues[0],
                updateTextOnChange: !isMultiselect,
                searchable: true
            });

        const createFilterContainerContent = () => {
            dataContainer.append(
                ...[
                    generator.createElement('span', 'adlp-data-label', label),
                    createDropdown(),
                    generator.createElement(
                        'ul',
                        'adlp-selected-data',
                        '',
                        null,
                        isMultiselect
                            ? getOptionsList(options, selectedValues, (value) => handleSelectChange(value, false))
                            : []
                    )
                ]
            );

            clearButton.disabled = selectedValues.length === 0;
            applyButton.disabled = arraysHaveSameContent(selectedValues, values);
        };

        const fireOnChange = (values) => {
            selectedValues = values;
            handleSelectedValuesChange();
            filterContainer.classList.remove('adls--open');
            values.length ? button.classList.add('adls--selected') : button.classList.remove('adls--selected');
            button.firstElementChild.innerHTML = getButtonLabel(label, labelPlural, options, selectedValues);
            button.lastElementChild.innerHTML = getButtonTooltip(label, options, selectedValues);
            dataContainer.innerHTML = '';
            onChange?.(selectedValues);
        };

        const handleSelectedValuesChange = () => {
            const list = filterContainer.querySelector('.adlp-selected-data');
            if (list && isMultiselect) {
                list.innerHTML = '';
                list.append(...getOptionsList(options, selectedValues, (value) => handleSelectChange(value, false)));
            } else if (!isMultiselect && selectedValues.length === 0) {
                // reset value for the dropdown
                const previousDD = filterContainer.querySelector('.adl-single-select-dropdown');
                dataContainer.insertBefore(createDropdown(), previousDD);

                previousDD?.remove();
            }

            clearButton.disabled = selectedValues.length === 0;
            applyButton.disabled = arraysHaveSameContent(selectedValues, values);
        };

        const handleSelectChange = (value, add = true) => {
            if (add) {
                isMultiselect && !selectedValues.includes(value) && selectedValues.push(value);
                !isMultiselect && (selectedValues = [value]);
            } else if (isMultiselect) {
                selectedValues = selectedValues.filter((v) => v !== value);
            } else {
                selectedValues = [];
            }

            handleSelectedValuesChange();
        };

        const closeFilter = () => {
            selectedValues = [...values];
            filterContainer.classList.remove('adls--open');
            dataContainer.innerHTML = '';
        };

        const button = renderButton(
            label,
            labelPlural,
            options,
            values,
            () => {
                if (filterContainer.classList.contains('adls--open')) {
                    dataContainer.innerHTML = '';
                } else {
                    createFilterContainerContent();
                }

                filterContainer.classList.toggle('adls--open');
            },
            () => {
                fireOnChange([]);
            }
        );

        const clearButton = generator.createButton({
            type: 'secondary',
            size: 'small',
            label: 'general.clear',
            className: 'adlm--blue',
            disabled: values.length === 0,
            onClick: () => {
                selectedValues = [];
                handleSelectedValuesChange();
            }
        });
        const applyButton = generator.createButton({
            type: 'primary',
            size: 'small',
            label: 'general.apply',
            className: 'adlm--blue',
            disabled: true,
            onClick: () => fireOnChange(selectedValues)
        });

        const dataContainer = generator.createElement('div', 'adlp-dropdown-data');
        const filterContainer = generator.createElement('div', 'adlp-dropdown-container', '', null, [
            generator.createElement('div', 'adlp-content', '', null, [
                generator.createElement('div', 'adlp-filter-header', '', null, [
                    generator.createElement('span', '', 'payments.filter.filter'),
                    generator.createElement('button', 'adlp-close-button', '', { onClick: closeFilter })
                ]),
                dataContainer,
                generator.createElement('span', 'adlp-buttons', '', null, [clearButton, applyButton])
            ])
        ]);

        const element = generator.createElement('div', 'adl-multiselect-filter', '', null, [button, filterContainer]);

        window.addEventListener('click', (event) => {
            if (!element.contains(event.target) && event.target !== element) {
                closeFilter();
            }
        });

        return element;
    };

    AdyenFE.components.TableFilter = {
        create
    };
})();
