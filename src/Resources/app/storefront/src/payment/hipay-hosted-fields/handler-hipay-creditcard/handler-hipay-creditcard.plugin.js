import HipayHostedFieldsPlugin from '../hipay-hosted-fields.plugin';

/**
 * Plugin hipay for hosted fields
 */
export default class HandlerHipayCreditcardPlugin extends HipayHostedFieldsPlugin {
  getPaymentDefaultOption() {
    return {
      idCardHolder: 'hipay-card-holder',
      idCardNumber: 'hipay-card-number',
      idExpiryDate: 'hipay-expiry-date',
      idCvc: 'hipay-cvc'
    };
  }

  getPaymentName() {
    return 'card';
  }

  /**
   * Generate hosted fields configuration
   */
  getConfigHostedFields() {
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
          helpButton: this.options.cvcHelp
        }
      }
    };

    if (this.options.styles) {
      config.styles = this.options.styles;
    }

    return config;
  }
}