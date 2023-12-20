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
    $('#tos').prop('checked', false);
    // Remove global payment button
    $('#confirmFormSubmit').remove();

    this._form = document.querySelector('#' + this.options.idResponse).form;

    $('#apple-pay-button').hide();
    this._hfInstance = this.createHostedFieldsInstance(parameters);
    if (this.canMakeApplePayPayment(parameters.merchantId)) {
      $('#apple-pay-info-message').hide();
      $('#apple-pay-error-message').css('display', 'inline');
      $('#apple-pay-error-message').text($('#apple-pay-termes-of-service-error-message').text());

      this._applePayInstance = this.createApplePayInstance(parameters);
      this.handleTermsOfService();
      this.handleApplePayEvents();
    } else {
      $('#apple-pay-info-message').show();
      $('#apple-pay-error-message').hide();
    }
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
    $('#tos').on('change', () => {
      this.checkTermeOfService();
    });
  }

  checkTermeOfService() {
    if ($('#tos').is(":checked") || $("#tos").length === 0) {
      $('#apple-pay-button').show();
      $('#apple-pay-error-message').hide();
      $("#confirmFormSubmit[type=submit]").attr('disabled', 'true');
      $("#confirmFormSubmit[type=submit]").hide();
    } else {
      $('#apple-pay-button').hide();
      $('#apple-pay-error-message').css('display', 'inline');
      $('#apple-pay-error-message').text($('#apple-pay-termes-of-service-error-message').text());
      $("#confirmFormSubmit[type=submit]").show();
    }
  }

  /**
   * Check if card is available for this merchantID or if browser handles Apple Pay
   * @param merchantId merchant ID for ApplePay domain validation
   * @returns boolean
   */
  canMakeApplePayPayment(merchantId) {
    if(merchantId) {
      this._hfInstance.canMakePaymentsWithActiveCard().then(canMakePayments => {
        return canMakePayments;
      });
    } else {
      return window.ApplePaySession && window.ApplePaySession.canMakePayments();
    }
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
      lang: parameters.language_iso_code
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