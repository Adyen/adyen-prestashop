if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(function () {
    /**
     * @typedef Option
     * @property {string?} label
     * @property {any} value
     */

    /**
     * @typedef {Object.<string, *>} ElementProps
     * @property {string?} name
     * @property {any?} value
     * @property {string?} className
     * @property {string?} placeholder
     * @property {(value: any) => any?} onChange
     * @property {string?} label
     * @property {string?} description
     * @property {string?} error
     */

    /**
     * @typedef {ElementProps} FormField
     * @property {'text' | 'number' | 'radio' |'dropdown' | 'checkbox' | 'file' | 'multiselect' | 'button' |
     *     'buttonLink'} type
     */

    const translationService = AdyenFE.translationService;

    /**
     * Prevents default event handling.
     * @param {Event} e
     */
    const preventDefaults = (e) => {
        e.preventDefault();
        e.stopPropagation();
    };

    /**
     * Creates a generic HTML node element and assigns provided class and inner text.
     *
     * @param {keyof HTMLElementTagNameMap} type Represents the name of the tag
     * @param {string?} className CSS class
     * @param {string?} innerHTMLKey Inner text translation key.
     * @param {Record<string, any>?} properties An object of additional properties.
     * @param {HTMLElement[]?} children
     * @returns {HTMLElement}
     */
    const createElement = (type, className, innerHTMLKey, properties, children) => {
        const child = document.createElement(type);
        className && child.classList.add(...className.trim().split(' '));
        if (innerHTMLKey) {
            let params = innerHTMLKey.split('|');
            child.innerHTML = translationService.translate(params[0], params.slice(1));
        }

        if (properties) {
            if (properties.dataset) {
                Object.assign(child.dataset, properties.dataset);
                delete properties.dataset;
            }

            Object.assign(child, properties);
            if (properties.onChange) {
                child.addEventListener('change', properties.onChange, false);
            }

            if (properties.onClick) {
                child.addEventListener('click', properties.onClick, false);
            }
        }

        if (children) {
            child.append(...children);
        }

        return child;
    };

    /**
     * Creates an element out of provided HTML markup.
     *
     * @param {string} html
     * @returns {HTMLElement}
     */
    const createElementFromHTML = (html) => {
        const element = document.createElement('div');
        element.innerHTML = html;

        return element.firstElementChild;
    };

    /**
     * Creates a button.
     *
     * @param {{ label?: string, type?: 'primary' | 'secondary' | 'ghost', size?: 'small' | 'medium', className?:
     *     string, [key: string]: any, onClick?: () => void}} props
     * @return {HTMLButtonElement}
     */
    const createButton = ({ type, size, className, onClick, label, ...properties }) => {
        const cssClass = ['adl-button'];
        type && cssClass.push('adlt--' + type);
        size && cssClass.push('adlm--' + size);
        className && cssClass.push(className);

        const button = createElement('button', cssClass.join(' '), '', { type: 'button', ...properties }, [
            createElement('span', '', label)
        ]);

        onClick &&
            button.addEventListener(
                'click',
                (event) => {
                    preventDefaults(event);
                    onClick();
                },
                false
            );

        return button;
    };

    /**
     * Creates a link that looks like a button.
     *
     * @param {{text?: string, className?: string, href: string, useDownload?: boolean, downloadFile?: string}} props
     * @return {HTMLLinkElement}
     */
    const createButtonLink = ({ text, className = '', href, useDownload, downloadFile }) => {
        const link = createElement('a', className, `<span>${text}</span>`, { href: href, target: '_blank' });
        if (useDownload) {
            link.setAttribute('download', downloadFile);
        }

        return link;
    };

    /**
     * Creates an input field wrapper around the provided input element.
     *
     * @param {HTMLElement} input The input element.
     * @param {string?} label Label translation key.
     * @param {string?} description Description translation key.
     * @param {string?} error Error translation key.
     * @return {HTMLDivElement}
     */
    const createFieldWrapper = (input, label, description, error) => {
        const field = createElement('div', 'adl-field-wrapper');
        if (label) {
            field.appendChild(createElement('h3', 'adlp-field-title', label));
        }

        if (description) {
            field.appendChild(createElement('span', 'adlp-field-subtitle', description));
        }

        field.appendChild(input);

        if (error) {
            field.appendChild(createElement('span', 'adlp-input-error', error));
        }

        return field;
    };

    /**
     * Creates store switcher.
     *
     * @param {{value: string, label: string}[]} options
     * @param {string?} name
     * @param {string?} title
     * @param {string?} value
     * @param {(value: string) => Promise<boolean>?} onBeforeChange
     * @param {(value: string) => void?} onChange
     * @param {boolean?} updateTextOnChange
     * @return {HTMLDivElement}
     */
    const createStoreSwitcher = (options, name, title, value, onBeforeChange, onChange, updateTextOnChange = true) => {
        const hiddenInput = createElement('input', 'adlp-hidden-input', '', { type: 'hidden', name, value });
        const wrapper = createElement('div', 'adl-store-switcher');
        const storeIcon = createElement('span', 'adl-store-icon');
        const switchContent = createElement('div', 'adlp-switch-content');
        const switchText = createElement('h3', 'adlp-switch-text', title);
        const list = createElement('ul', 'adlp-stores');
        const switchButton = createElement('button', 'adlp-switch-store-button adlp-field-component', '', {
            type: 'button'
        });
        const selectedItem = options.find((option) => option.value === value) || options[0];
        const buttonSpan = createElement('span', '', selectedItem.label);

        switchButton.append(buttonSpan);
        const listItems = [];

        const handleOnOptionChange = (listItem, storeId) => {
            hiddenInput.value = storeId;
            updateTextOnChange && (switchButton.firstElementChild.innerHTML = listItem.innerText);
            list.classList.remove('adls--show');

            listItems.forEach((li) => li.classList.remove('adls--selected'));
            listItem.classList.add('adls--selected');
            onChange && onChange(storeId);
        };

        options.forEach((option) => {
            const listItem = createElement('li', 'adlp-store', option.label);
            listItems.push(listItem);
            list.append(listItem);
            if (option.value === selectedItem.value) {
                listItem.classList.add('adls--selected');
            }

            listItem.addEventListener('click', () => {
                if (option.value === hiddenInput.value) {
                    list.classList.remove('adls--show');
                    return;
                }

                if (!onBeforeChange) {
                    handleOnOptionChange(listItem, option.value);
                } else {
                    onBeforeChange(option.value).then((resume) => {
                        if (resume) {
                            handleOnOptionChange(listItem, option.value);
                        } else {
                            list.classList.remove('adls--show');
                        }
                    });
                }
            });
        });

        switchButton.addEventListener('click', (event) => {
            preventDefaults(event);
            list.classList.toggle('adls--show');
        });

        document.documentElement.addEventListener('click', () => {
            list.classList.remove('adls--show');
        });

        switchContent.append(switchText, switchButton);
        wrapper.append(hiddenInput, storeIcon, switchContent, list);

        return wrapper;
    };

    /**
     * Creates dropdown wrapper around the provided dropdown element.
     *
     * @param {ElementProps & DropdownComponentModel} props The properties.
     * @return {HTMLDivElement}
     */
    const createDropdownField = ({ label, description, error, ...dropdownProps }) => {
        return createFieldWrapper(AdyenFE.components.Dropdown.create(dropdownProps), label, description, error);
    };

    /**
     * Creates dropdown wrapper around the provided dropdown element.
     *
     * @param {(ElementProps & MultiselectDropdownComponentModel)} props The properties.
     * @return {HTMLDivElement}
     */
    const createMultiselectDropdownField = ({ label, description, error, ...dropdownProps }) => {
        return createFieldWrapper(
            AdyenFE.components.MultiselectDropdown.create(dropdownProps),
            label,
            description,
            error
        );
    };

    /**
     * Creates a password input field.
     *
     * @param {ElementProps} props The properties.
     * @return {HTMLElement}
     */
    const createPasswordField = ({ className = '', label, description, error, onChange, ...rest }) => {
        const wrapper = createElement('div', `adl-password ${className}`);
        const input = createElement('input', 'adlp-field-component', '', { type: 'password', ...rest });
        const span = createElement('span');
        span.addEventListener('click', () => {
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        });
        onChange && input.addEventListener('change', (event) => onChange(event.currentTarget?.value));

        wrapper.append(input, span);

        return createFieldWrapper(wrapper, label, description, error);
    };

    /**
     * Creates a text input field.
     *
     * @param {ElementProps & { type?: 'text' | 'number' }} props The properties.
     * @return {HTMLElement}
     */
    const createTextField = ({ className = '', label, description, error, onChange, ...rest }) => {
        /** @type HTMLInputElement */
        const input = createElement('input', `adlp-field-component ${className}`, '', { type: 'text', ...rest });
        onChange && input.addEventListener('change', (event) => onChange(event.currentTarget?.value));

        return createFieldWrapper(input, label, description, error);
    };

    /**
     * Creates a number input field.
     *
     * @param {ElementProps} props The properties.
     * @return {HTMLElement}
     */
    const createNumberField = ({ onChange, ...rest }) => {
        const handleChange = (value) => onChange(value === '' ? null : Number(value));

        return createTextField({ type: 'number', step: '0.01', onChange: handleChange, ...rest });
    };

    /**
     * Creates a radio group field.
     *
     * @param {ElementProps} props The properties.
     * @return {HTMLElement}
     */
    const createRadioGroupField = ({ name, value, className, options, label, description, error, onChange }) => {
        const wrapper = createElement('div', 'adl-radio-input-group');
        options.forEach((option) => {
            const label = createElement('label', 'adl-radio-input');
            const props = { type: 'radio', value: option.value, name };
            if (value === option.value) {
                props.checked = 'checked';
            }

            label.append(createElement('input', className, '', props), createElement('span', '', option.label,
                { dataset: { value: option.value } }));
            wrapper.append(label);
            onChange && label.addEventListener('click', () => onChange(option.value));
        });

        return createFieldWrapper(wrapper, label, description, error);
    };

    /**
     * Creates a checkbox field.
     *
     * @param {ElementProps} props The properties.
     * @return {HTMLElement}
     */
    const createCheckboxField = ({ className = '', label, description, error, onChange, value, ...rest }) => {
        /** @type HTMLInputElement */
        const checkbox = createElement('input', 'adlp-toggle-input', '', { type: 'checkbox', checked: value, ...rest });
        onChange && checkbox.addEventListener('change', () => onChange(checkbox.checked));

        const field = createElement('div', 'adl-field-wrapper adlt--checkbox', '', null, [
            createElement('h3', 'adlp-field-title', label, null, [
                createElement('label', 'adl-toggle', '', null, [checkbox, createElement('span', 'adlp-toggle-round')])
            ])
        ]);

        if (description) {
            field.appendChild(createElement('span', 'adlp-field-subtitle', description));
        }

        if (error) {
            field.appendChild(createElement('span', 'adlp-input-error', error));
        }

        return field;
    };

    /**
     * Creates a button field.
     *
     * @param {ElementProps & { onClick?: () => void , buttonType?: string, buttonSize?: string,
     *     buttonLabel?: string}} props The properties.
     * @return {HTMLElement}
     */
    const createButtonField = ({ label, description, buttonType, buttonSize, buttonLabel, onClick, error }) => {
        const button = createButton({
            type: buttonType,
            size: buttonSize,
            className: '',
            label: translationService.translate(buttonLabel),
            onClick: onClick
        });

        return createFieldWrapper(button, label, description, error);
    };

    /**
     * Creates a field with a link that looks like a button.
     *
     * @param {ElementProps & {text: string, href: string}} props
     */
    const createButtonLinkField = ({ label, text, description, href, error }) => {
        const buttonLink = createButtonLink({
            text: translationService.translate(text),
            className: '',
            href: href
        });

        return createFieldWrapper(buttonLink, label, description, error);
    };

    /**
     * Creates a flash message.
     *
     * @param {string|string[]} messageKey
     * @param {'error' | 'warning' | 'success'} status
     * @param {number?} clearAfter Time in ms to remove alert message.
     * @return {HTMLElement}
     */
    const createFlashMessage = (messageKey, status, clearAfter) => {
        const hideHandler = () => {
            wrapper.remove();
        };
        const wrapper = createElement('div', `adl-alert adlt--${status}`);
        let messageBlock;
        if (Array.isArray(messageKey)) {
            const [titleKey, descriptionKey] = messageKey;
            messageBlock = createElement('div', 'adlp-alert-title', '', null, [
                createElement('span', 'adlp-message', '', null, [
                    createElement('span', 'adlp-message-title', titleKey),
                    createElement('span', 'adlp-message-description', descriptionKey)
                ])
            ]);
        } else {
            messageBlock = createElement('span', 'adlp-alert-title', messageKey);
        }

        const button = createButton({ onClick: hideHandler });

        if (clearAfter) {
            setTimeout(hideHandler, clearAfter);
        }

        wrapper.append(messageBlock, button);

        return wrapper;
    };

    /**
     * Adds a label with a hint.
     *
     * @param {string} label
     * @param {string} hint
     * @param {'left' | 'right' | 'top' | 'bottom'} position
     * @param {string?} className
     * @returns HTMLElement
     */
    const createHint = (label, hint, position, className = '') => {
        const element = createElement('div', `adl-hint ${className}`, label);
        element.append(createElement('span', 'adlp-tooltip adlt--' + position, hint));
        element.addEventListener('mouseenter', () => {
            element.classList.add('adls--active');
        });
        element.addEventListener('mouseout', () => {
            element.classList.remove('adls--active');
        });

        return element;
    };

    /**
     * Creates a toaster message.
     *
     * @param {string} label
     * @param {number} timeout Clear timeout in ms.
     * @returns {HTMLElement}
     */
    const createToaster = (label, timeout = 5000) => {
        const toaster = createElement('div', 'adl-toaster', '', null, [
            createElement('span', 'adlp-toaster-title', label),
            createElement('button', 'adl-button', '', null, [createElement('span')])
        ]);

        toaster.children[1].addEventListener('click', () => toaster.remove());

        setTimeout(() => toaster.remove(), timeout);

        return toaster;
    };

    /**
     *
     * @param {ElementProps & { supportedMimeTypes: string[] }} props
     * @returns {HTMLDivElement}
     */
    const createFileUploadField = ({
        name,
        placeholder,
        label,
        description,
        error,
        value,
        onChange,
        supportedMimeTypes
    }) => {
        const setActive = (e) => {
            preventDefaults(e);
            wrapper.classList.add('adls--active');
        };

        const setInactive = (e) => {
            preventDefaults(e);
            wrapper.classList.remove('adls--active');
        };

        const previewFile = (file, img) => {
            let reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onloadend = function () {
                img.src = reader.result;
            };
        };

        const handleDrop = (e) => {
            const file = e.dataTransfer?.files?.[0] || null;
            if (file) {
                handleFileChange(file);
            }
        };

        const handleFileChange = (file) => {
            if (!supportedMimeTypes.includes(file.type)) {
                AdyenFE.validationService.setError(wrapper, 'validation.invalidImageType');
                return;
            }

            if (file.size > 10000000) {
                AdyenFE.validationService.setError(wrapper, 'validation.invalidImageSize');
                return;
            }

            onChange(file);
            AdyenFE.validationService.removeError(wrapper);
            textElem.classList.remove('adls--empty');
            textElem.innerText = file.name;
            const img = createElement('img');
            textElem.prepend(img);
            previewFile(file, img);
        };

        const wrapper = createElement('div', 'adl-file-drop-zone adlp-field-component');
        const labelElem = createElement('label', 'adlp-input-file-label');
        const textElem = createElement('span', 'adlp-file-label' + (!value ? ' adls--empty' : ''), placeholder);
        if (value) {
            textElem.prepend(createElement('img', '', '', { src: value }));
        }

        const fileUpload = createElement('input', 'adlp-input-file', '', {
            type: 'file',
            accept: 'image/*',
            name: name
        });
        fileUpload.addEventListener('change', () => handleFileChange(fileUpload.files?.[0]));

        labelElem.append(textElem, fileUpload);
        wrapper.append(labelElem);

        ['dragenter', 'dragover'].forEach((eventName) => {
            wrapper.addEventListener(eventName, setActive, false);
        });
        ['dragleave', 'drop'].forEach((eventName) => {
            wrapper.addEventListener(eventName, setInactive, false);
        });
        wrapper.addEventListener('drop', handleDrop, false);

        return createFieldWrapper(wrapper, label, description, error);
    };

    /**
     * Adds a form footer with save and cancel buttons.
     *
     * @param {() => void} onSave
     * @param {() => void} onCancel
     * @param {string} cancelLabel
     * @param {HTMLButtonElement[]} extraButtons
     * @returns HTMLElement
     */
    const createFormFooter = (onSave, onCancel, cancelLabel = 'general.cancel', extraButtons = []) => {
        return createElement('div', 'adl-form-footer', '', null, [
            createElement('span', 'adlp-changes-count', 'general.unsavedChanges'),
            createElement('div', 'adlp-actions', '', null, [
                ...extraButtons,
                createButton({
                    type: 'secondary',
                    className: 'adlp-cancel',
                    label: cancelLabel,
                    onClick: onCancel,
                    disabled: true
                }),
                createButton({
                    type: 'primary',
                    name: 'saveChangesButton',
                    className: 'adlp-save',
                    label: 'general.saveChanges',
                    onClick: onSave,
                    disabled: true
                })
            ])
        ]);
    };

    /**
     * Creates form fields based on the fields configurations.
     *
     * @param {FormField[]} fields
     */
    const createFormFields = (fields) => {
        /** @type HTMLElement[] */
        const result = [];
        fields.forEach(({ type, ...rest }) => {
            switch (type) {
                case 'text':
                    result.push(createTextField({ ...rest, className: 'adl-text-input' }));
                    break;
                case 'number':
                    result.push(createNumberField({ ...rest, className: 'adl-text-input' }));
                    break;
                case 'dropdown':
                    result.push(createDropdownField(rest));
                    break;
                case 'multiselect':
                    result.push(createMultiselectDropdownField(rest));
                    break;
                case 'radio':
                    result.push(createRadioGroupField(rest));
                    break;
                case 'checkbox':
                    result.push(createCheckboxField(rest));
                    break;
                case 'file':
                    result.push(createFileUploadField(rest));
                    break;
                case 'button':
                    result.push(createButtonField(rest));
                    break;
                case 'buttonLink':
                    result.push(createButtonLinkField(rest));
                    break;
            }

            rest.className && result[result.length - 1].classList.add(...rest.className.trim().split(' '));
        });

        return result;
    };

    /**
     * Creates a main header item.
     *
     * @param {string} title
     * @param {string} text
     * @returns {[HTMLElement,HTMLElement]}
     */
    const createHeaderItem = (title, text) => {
        return [
            createElement('span', 'adlp-nav-item-icon', ''),
            createElement('div', 'adlp-nav-item-text', '', null, [
                createElement('h3', 'adlp-nav-item-title', title),
                createElement('span', 'adlp-nav-item-subtitle', text)
            ])
        ];
    };

    AdyenFE.elementGenerator = {
        createElement,
        createElementFromHTML,
        createButton,
        createHint,
        createDropdownField,
        createMultiselectDropdownField,
        createPasswordField,
        createTextField,
        createNumberField,
        createRadioGroupField,
        createFlashMessage,
        createStoreSwitcher,
        createFileUploadField,
        createButtonField,
        createButtonLinkField,
        createFormFields,
        createFormFooter,
        createToaster,
        createHeaderItem
    };
})();
