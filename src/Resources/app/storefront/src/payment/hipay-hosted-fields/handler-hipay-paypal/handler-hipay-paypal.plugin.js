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
    };

    init() {
        const tosElement = document.querySelector('#tos');
        if(tosElement) {
            tosElement.checked = false;
        }
        // Remove global payment button
        let element = document.querySelector('#confirmFormSubmit');
        if (element) {
            element.remove();
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
                locale: this.options.locale
            }
        };

        this._form = document.querySelector('#' + this.options.idResponse).form;

        const paypalErrorMessage = document.querySelector('#paypal-error-message');
        if(paypalErrorMessage) {
            paypalErrorMessage.style.display = 'inline';
        }

        this._paypalInstance = this._hipayInstance.create('paypal', config);

        this.setupTermsOfServiceValidation();

        this._paypalInstance.on('paymentAuthorized', (function (data) {
            const inputResponse = document.querySelector('#' + this.options.idResponse);
            inputResponse.setAttribute('value', JSON.stringify(data));
            this._form.submit();
        }).bind(this));
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
            paypalField.style.display = '';
            if (paypalErrorMessage) {
                paypalErrorMessage.style.display = 'none';
            }
        } else {
            paypalField.style.display = 'none';
            if (paypalErrorMessage) {
                paypalErrorMessage.style.display = 'inline';
            }
        }
    }
}
