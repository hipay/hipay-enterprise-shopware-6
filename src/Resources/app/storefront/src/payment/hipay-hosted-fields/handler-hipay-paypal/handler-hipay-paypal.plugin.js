/**
 * Plugin hipay for PayPal
 */
export default class HandlerHipayPaypalPlugin extends window.PluginBaseClass {
    static options = {
        username: null,
        password: null,
        environment: null,
        canPayLater: null,
        amount: null,
        currency: null,
        lang: null,
        styles: null,
        idResponse: 'hipay-response',
        shippingAddress: null,
        translations: null,
    };

    init() {
        // Remove global payment button
        let element = document.querySelector('#confirmFormSubmit');
        if (element) {
            element.remove();
        }

        this._form = document.querySelector('#' + this.options.idResponse).form;

        const validationResult = this.validateAddressLocally();
        if (!validationResult.isValid) {
            this.showAddressError(validationResult.missingFields);
            return;
        }

        this._hipayInstance = new HiPay({
            username: this.options.username,
            password: this.options.password,
            environment: this.options.environment,
            lang: this.options.lang
        });

        const config = {
            template: 'auto',
            selector: 'paypal-field',
            canPayLater: this.options.canPayLater,
            paypalButtonStyle: {
                shape: this.options.styles.shape,
                height: Number(this.options.styles.height),
                color: this.options.styles.color,
                label: this.options.styles.label
            },
            request: {
                amount: this.options.amount,
                currency: this.options.currency,
                locale: this.options.locale,
                customerShippingInformation: this.buildCustomerShippingInformation()
            }
        };

        const paypalErrorMessage = document.querySelector('#paypal-error-message');
        if(paypalErrorMessage) {
            paypalErrorMessage.removeAttribute('hidden');
        }

        const originalHandler = window.onunhandledrejection;
        window.onunhandledrejection = (event) => {
            if (event.reason && event.reason.message && event.reason.message.includes('HIPAY_CREATE')) {
                event.preventDefault();
                this.handleSDKError(event.reason);
                return;
            }
            if (originalHandler) {
                originalHandler.call(window, event);
            }
        };

        try {
            this._paypalInstance = this._hipayInstance.create('paypal', config);

            this.setupTermsOfServiceValidation();

            this._paypalInstance.on('paymentAuthorized', (function (data) {
                const inputResponse = document.querySelector('#' + this.options.idResponse);
                inputResponse.setAttribute('value', JSON.stringify(data));
                this._form.submit();
            }).bind(this));
        } catch (error) {
            this.handleSDKError(error);
        }
    }

    setupTermsOfServiceValidation() {
        const paypalField = document.querySelector('#paypal-field');

        const tosInput = document.querySelector("input[name=tos]");
        if (tosInput) {
            tosInput.addEventListener('change', () => {
                this.checkTermsOfService();
            });
        }

        const observer = new MutationObserver(() => {
            this.checkTermsOfService();
        });

        if (paypalField) {
            observer.observe(paypalField, { childList: true, subtree: true });
        }

        this.checkTermsOfService();
    }

    checkTermsOfService() {
        let tosElement = document.querySelector('#tos');
        let paypalField = document.querySelector('#paypal-field');
        let paypalErrorMessage = document.querySelector('#paypal-error-message');

        if (!paypalField) return;

        if (!tosElement || tosElement.checked) {
            paypalField.removeAttribute('hidden');
            if (paypalErrorMessage) {
                paypalErrorMessage.setAttribute('hidden', '');
            }
        } else {
            paypalField.setAttribute('hidden', '');
            if (paypalErrorMessage) {
                paypalErrorMessage.removeAttribute('hidden');
            }
        }
    }

    buildCustomerShippingInformation() {
        const address = this.options.shippingAddress;

        if (!address) {
            return null;
        }

        const shippingInfo = {
            zipCode: address.zipcode,
            city: address.city,
            country: address.country,
            streetaddress: address.streetaddress
        };

        if (address.streetaddress2 && address.streetaddress2.trim() !== '') {
            shippingInfo.streetaddress2 = address.streetaddress2;
        }
        if (address.firstname && address.firstname.trim() !== '') {
            shippingInfo.firstname = address.firstname;
        }
        if (address.lastname && address.lastname.trim() !== '') {
            shippingInfo.lastname = address.lastname;
        }
        if (address.recipientinfo && address.recipientinfo.trim() !== '') {
            shippingInfo.recipientinfo = address.recipientinfo;
        }

        return shippingInfo;
    }

    validateAddressLocally() {
        const address = this.options.shippingAddress;
        const missingFields = [];

        if (!address) {
            return {
                isValid: false,
                missingFields: ['streetaddress', 'zipcode', 'city', 'country']
            };
        }

        if (!address.streetaddress || address.streetaddress.trim() === '') {
            missingFields.push('streetaddress');
        }
        if (!address.zipcode || address.zipcode.trim() === '') {
            missingFields.push('zipcode');
        }
        if (!address.city || address.city.trim() === '') {
            missingFields.push('city');
        }
        if (!address.country || address.country.trim() === '') {
            missingFields.push('country');
        }

        return {
            isValid: missingFields.length === 0,
            missingFields: missingFields
        };
    }

    showAddressError(missingFields) {
        const paypalField = document.querySelector('#paypal-field');
        if (paypalField) {
            paypalField.innerHTML = '';
            paypalField.setAttribute('hidden', '');
        }

        let errorContainer = document.querySelector('#paypal-address-error');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.id = 'paypal-address-error';
            errorContainer.className = 'alert alert-danger';
            errorContainer.style.display = 'block';
            errorContainer.style.marginBottom = '1rem';

            let container = this.el || document.querySelector('#hipay-form');

            if (container) {
                container.insertBefore(errorContainer, container.firstChild);
            }
        }

        const translations = this.options.translations || {};

        const fieldNames = missingFields.map(field => {
            const translationKey = 'field-' + field;
            const translatedField = translations[translationKey] || field;
            return translatedField;
        }).join(', ');

        const errorTemplate = translations['invalid-address'] ||
            'Invalid delivery address. Please check or correct the following fields: {fields}.';

        const errorMessage = errorTemplate.replace('{fields}', fieldNames);

        errorContainer.textContent = errorMessage;
        errorContainer.removeAttribute('hidden');
        errorContainer.style.display = 'block';
    }

    hideAddressError() {
        const errorContainer = document.querySelector('#paypal-address-error');
        if (errorContainer) {
            errorContainer.innerHTML = '';
            errorContainer.setAttribute('hidden', '');
        }

        const paypalField = document.querySelector('#paypal-field');
        if (paypalField) {
            paypalField.innerHTML = '';
            paypalField.removeAttribute('hidden');
        }
    }

    handleSDKError(error) {
        console.log('SDK Error caught:', error);

        const paypalField = document.querySelector('#paypal-field');
        if (paypalField) {
            paypalField.innerHTML = '';
            paypalField.setAttribute('hidden', '');
        }

        const missingFields = this.parseSDKError(error);

        if (missingFields.length > 0) {
            this.showAddressError(missingFields);
        } else {
            this.showGenericError(error);
        }
    }

    parseSDKError(error) {
        const missingFields = [];
        const errorMessage = error.message || error.toString();

        console.log('Parsing SDK error:', errorMessage);

        const fieldMapping = {
            'zipCode': 'zipcode',
            'city': 'city',
            'country': 'country',
            'streetaddress': 'streetaddress'
        };

        Object.entries(fieldMapping).forEach(([sdkField, internalField]) => {
            if (errorMessage.includes(sdkField) && !missingFields.includes(internalField)) {
                missingFields.push(internalField);
            }
        });

        console.log('Extracted missing fields:', missingFields);
        return missingFields;
    }

    showGenericError(error) {
        let errorContainer = document.querySelector('#paypal-address-error');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.id = 'paypal-address-error';
            errorContainer.className = 'alert alert-danger';
            errorContainer.style.display = 'block';
            errorContainer.style.marginBottom = '1rem';

            let container = this.el || document.querySelector('#hipay-form');
            if (container) {
                container.insertBefore(errorContainer, container.firstChild);
            }
        }

        const translations = this.options.translations || {};
        errorContainer.textContent = translations['invalid-address'] ||
            'Unable to initialize PayPal. Please check your shipping address.';
        errorContainer.removeAttribute('hidden');
        errorContainer.style.display = 'block';
    }
}
