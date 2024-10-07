import Plugin from 'src/plugin-system/plugin.class';

/**
 * Plugin hipay for PayPal
 */
export default class HandlerHipayPaypalPlugin extends Plugin {
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

        this._paypalInstance = this._hipayInstance.create('paypal', config);

        this._paypalInstance.on('paymentAuthorized', (function (data) {
            const inputResponse = document.querySelector('#' + this.options.idResponse);
            inputResponse.setAttribute('value', JSON.stringify(data));
            this._form.submit();
        }).bind(this));
    }
}