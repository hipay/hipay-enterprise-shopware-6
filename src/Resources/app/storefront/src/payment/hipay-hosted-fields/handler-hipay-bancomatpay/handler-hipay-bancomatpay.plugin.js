import HipayTosHostedFieldsPlugin from '../hipay-tos-hosted-fields.plugin';

/**
 * Plugin hipay for hosted fields
 */
export default class HandlerHipayBancomatpayPlugin extends HipayTosHostedFieldsPlugin {
  getPaymentDefaultOption() {
    return {
      phone: 'hipay-bancomatpay-phone'
    };
  }

  getPaymentName() {
    return 'bancomatpay';
  }

  getFieldsGroupId() {
    return 'hipay-bancomatpay-phone-group';
  }

  getTosMessageId() {
    return 'hipay-bancomatpay-tos';
  }

  /**
   * Generate hosted fields configuration
   */
  getConfigHostedFields() {
    const config = {
      fields: {
        phone: {
          selector: this.options.phone,
          defaultCountry: 'IT'
        }
      }
    };

    if (this.options.styles) {
      config.styles = this.options.styles;
    }

    return config;
  }
}
