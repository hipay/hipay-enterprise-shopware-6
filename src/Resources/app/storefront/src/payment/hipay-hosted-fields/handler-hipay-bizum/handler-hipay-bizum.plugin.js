import HipayTosHostedFieldsPlugin from '../hipay-tos-hosted-fields.plugin';

/**
 * Plugin hipay for hosted fields
 */
export default class HandlerHipayBizumPlugin extends HipayTosHostedFieldsPlugin {
  getPaymentDefaultOption() {
    return {
      phone: 'hipay-bizum-phone'
    };
  }

  getPaymentName() {
    return 'bizum';
  }

  getFieldsGroupId() {
    return 'hipay-bizum-phone-group';
  }

  getTosMessageId() {
    return 'hipay-bizum-tos';
  }

  /**
   * Generate hosted fields configuration
   */
  getConfigHostedFields() {
    const config = {
      fields: {
        phone: {
          selector: this.options.phone,
          defaultCountry: 'ES'
        }
      }
    };

    if (this.options.styles) {
      config.styles = this.options.styles;
    }

    return config;
  }
}
