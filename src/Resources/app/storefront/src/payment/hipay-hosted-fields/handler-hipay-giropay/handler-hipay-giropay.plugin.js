import HipayHostedFieldsPlugin from '../hipay-hosted-fields.plugin';

/**
 * Plugin hipay for hosted fields
 */
export default class HandlerHipayGiropayPlugin extends HipayHostedFieldsPlugin {
  getPaymentDefaultOption() {
    return {
      idIssuerBank: 'hipay-giropay-issuer-bank'
    };
  }

  getPaymentName() {
    return 'giropay';
  }

  /**
   * Generate hosted fields configuration
   */
  getConfigHostedFields() {
    const config = {
      fields: {
        issuer_bank_id: {
          selector: this.options.idIssuerBank
        }
      }
    };

    if (this.options.styles) {
      config.styles = this.options.styles;
    }

    return config;
  }
}