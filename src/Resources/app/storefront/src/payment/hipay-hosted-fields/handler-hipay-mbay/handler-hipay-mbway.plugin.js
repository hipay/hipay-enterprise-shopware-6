import HipayHostedFieldsPlugin from '../hipay-hosted-fields.plugin';

/**
 * Plugin hipay for hosted fields
 */
export default class HandlerHipayMbwayPlugin extends HipayHostedFieldsPlugin {
  getPaymentDefaultOption() {
    return {
      phone: 'hipay-mbway-phone'
    };
  }

  getPaymentName() {
    return 'mbway';
  }

  /**
   * Generate hosted fields configuration
   */
  getConfigHostedFields() {
    const config = {
      fields: {
        phone: {
          selector: this.options.phone
        }
      }
    };

    if (this.options.styles) {
      config.styles = this.options.styles;
    }

    return config;
  }
}