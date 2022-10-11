import Plugin from "src/plugin-system/plugin.class";

/**
 * Plugin hipay for hosted fields
 */
export default class HipayHostedFieldsPlugin extends Plugin {
    static options = {
        username: null,
        password: null,
        environment: null,
        lang: null,
        idCardHolder: 'hipay-card-holder',
        idCardNumber: 'hipay-card-number',
        idExpiryDate: 'hipay-expiry-date',
        idCvc: 'hipay-cvc',
        idResponse: 'hipay-response',
        cvcHelp: false,
        errorClass: 'is-invalid',
        errorPrefix: 'error',
        styles: null
    };

    /**
     * Plugin initialisation
     */
    init() {
        const options = this.options;
        const configHostedFields = this._getConfigHostedFields();

        const hipay = HiPay(options);

        const cardInstance = hipay.create('card', configHostedFields);
        cardInstance.on('ready', function () {

            const errorHandler = function (fieldControl) {
                const targetId = configHostedFields.fields[fieldControl.element].selector;
                const node = document.querySelector('#' + targetId);

                if (!fieldControl.validity.valid && node.classList.contains('HiPayField--invalid')) {
                    node.classList.add(options.errorClass);
                } else {
                    node.classList.remove(options.errorClass);
                }

                document.querySelector('#' + options.errorPrefix + '-' + targetId).innerHTML = fieldControl.validity.error;

            };

            // error handler
            cardInstance.on('inputChange', errorHandler);
            cardInstance.on('blur', errorHandler);

            // bind hosted fields response into payment form
            cardInstance.on('change', function () {

                cardInstance.getPaymentData().then(
                    function (result) {
                        document.querySelector('#' + options.idResponse).setAttribute('value', JSON.stringify(result));
                    },
                    function (result) {
                        document.querySelector('#' + options.idResponse).setAttribute('value', '');
                    }
                );
            });
        });
    }

    /**
     * Handle Error
     */
    _handleError(targetId, message) {
        const node = document.querySelector('#' + targetId);

        if (node.classList.contains('HiPayField--invalid')) {
            node.classList.add(this.options.errorClass);
        } else {
            node.classList.remove(this.options.errorClass);
        }

        document.querySelector('#' + this.options.errorPrefix + '-' + targetId).innerHTML = message;

    }

    /**
     * Generate hosted fields configuration
     */
    _getConfigHostedFields() {
        const config = {
            fields: {
                cardHolder: {
                    selector: this.options.idCardHolder
                },
                cardNumber: {
                    selector: this.options.idCardNumber
                },
                expiryDate: {
                    selector: this.options.idExpiryDate
                },
                cvc: {
                    selector: this.options.idCvc,
                    helpButton: this.options.cvcHelp,
                }
            }
        };

        if (this.options.styles) {
            config.styles = this.options.styles;
        }

        return config;
    }
}