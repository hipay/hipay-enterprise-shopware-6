import Plugin from 'src/plugin-system/plugin.class';

/**
 * Plugin hipay for Apple Pay
 */
export default class HandlerHipayApplePayPlugin extends Plugin {

  static options = {
    username: null,
    password: null,
    environment: null,
    lang: null,
    idResponse: 'hipay-response',
    cvcHelp: false,
    errorClass: 'is-invalid',
    errorPrefix: 'error',
    styles: null,
    merchantId: null
  };

  init() {
    const parameters = {
      api_apple_pay_username: this.options.username,
      api_apple_pay_password: this.options.password,
      merchantId: this.options.merchantId,
      environment: this.options.environment,
      lang: this.options.lang,
      countryCode: this.options.countryCode,
      currency: this.options.currency,
      buttonType: this.options.styles.buttonType,
      buttonStyle: this.options.styles.buttonStyle,
      totalAmount: (this.options.amount).toString(),
      shopName: this.options.shopname
    };

    // Reset term of service checkbox on refresh
    document.querySelector('#tos').checked = false;

    // Remove global payment button
    let element = document.querySelector('#confirmFormSubmit');
    if (element) {
        element.remove();
    }

    this._form = document.querySelector('#' + this.options.idResponse).form;

    document.querySelector('#apple-pay-button').style.display = 'none';

    this._hfInstance = this.createHostedFieldsInstance(parameters);

    this.canMakeApplePayPayment(parameters.merchantId).then((canMakePayment) => {
      if (canMakePayment) {
        document.querySelector('#apple-pay-info-message').style.display = 'none';
        document.querySelector('#apple-pay-error-message').style.display = 'inline';
        document.querySelector('#apple-pay-error-message').textContent = document.querySelector('#apple-pay-termes-of-service-error-message').textContent;

        this._applePayInstance = this.createApplePayInstance(parameters);
        this.handleTermsOfService();
        this.handleApplePayEvents();
      } else {
        document.querySelector('#apple-pay-info-message').style.display = '';
        document.querySelector('#apple-pay-error-message').style.display = 'none';
      }
    });
  }

  /**
   * Create Apple Pay event handlers
   * @param this._applePayInstance
   */
  handleApplePayEvents() {
    this._applePayInstance.on('paymentAuthorized', (hipayToken) => {

      let data = {
        "payment_product": hipayToken.brand,
        "token": hipayToken.token,
        "brand": hipayToken.brand,
        "card_expiry-month": hipayToken.cardExpiryMonth,
        "card_expiry-year": hipayToken.cardExpiryYear,
        "card_holder": hipayToken.cardHolder,
        "pan": hipayToken.cardPan,
        "issuer": hipayToken.cardIssuer,
        "country": hipayToken.cardCountry,
      }

      const inputResponse = document.querySelector('#hipay-response');
      inputResponse.setAttribute('value', JSON.stringify(data));

      this._form.submit();
      this._applePayInstance.completePaymentWithSuccess();
    });

    this._applePayInstance.on('paymentUnauthorized', function () {
      // The payment is not authorized (Token creation has failed, domain validation has failed...)
      this.completePaymentWithFailure();
    });
  }

  handleTermsOfService() {
    this.checkTermeOfService();
    document.querySelector("input[name=tos]").addEventListener('change', () => {
      this.checkTermeOfService();
    });
  }

  checkTermeOfService() {
    let tosElement = document.querySelector('#tos');
    let applePayButton = document.querySelector('#apple-pay-button');
    let applePayErrorMessage = document.querySelector('#apple-pay-error-message');
    let applePayTermsOfServiceErrorMessage = document.querySelector('#apple-pay-termes-of-service-error-message');

    if (!tosElement || tosElement.checked) {
      applePayButton.style.display = '';
      applePayErrorMessage.style.display = 'none';
    } else {
      applePayButton.style.display = 'none';
      applePayErrorMessage.style.display = 'inline';
      applePayErrorMessage.textContent = applePayTermsOfServiceErrorMessage.textContent;
    }
  }

  /**
   * Check if card is available for this merchantID or if browser handles Apple Pay
   * @param merchantId merchant ID for ApplePay domain validation
   * @returns boolean
   */
  canMakeApplePayPayment(merchantId) {
    return new Promise((resolve) => {
        if (merchantId) {
            this._hfInstance
                .canMakePaymentsWithActiveCard(merchantId)
                .then((canMakePayments) => {
                    resolve(canMakePayments);
                });
        } else {
            resolve (
                window.ApplePaySession &&
                window.ApplePaySession.canMakePayments()
            );
        }
    });
  }
  /**
   * Create Hosted Fields instance
   * @returns HF instance
   */
  createHostedFieldsInstance(parameters) {
    return new HiPay({
      username: parameters.api_apple_pay_username,
      password: parameters.api_apple_pay_password,
      environment: parameters.environment,
      lang: parameters.lang
    });
  }

  /**
    * Create Apple Pay button instance
    * @returns paymentRequestButton
  */
  createApplePayInstance(parameters) {
    const total = {
      label: 'Total',
      amount: parameters.totalAmount
    }

    const request = {
      countryCode: parameters.countryCode,
      currencyCode: parameters.currency,
      total: total,
      supportedNetworks: ['visa', 'masterCard']
    };

    const applePayStyle = {
      type: parameters.buttonType,
      color: parameters.buttonStyle
    };

    const options = {
      displayName: parameters.shopName,
      request: request,
      applePayStyle: applePayStyle,
      selector: 'apple-pay-button'
    };

    return this._hfInstance.create(
      'paymentRequestButton',
      options
    );
  }
}