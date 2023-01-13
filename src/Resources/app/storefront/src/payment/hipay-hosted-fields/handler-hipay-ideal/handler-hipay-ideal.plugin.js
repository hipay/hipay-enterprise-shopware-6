import HipayHostedFieldsPlugin from '../hipay-hosted-fields.plugin';

/**
 * Plugin hipay for hosted fields
 */
export default class HandlerHipayIdealPlugin extends HipayHostedFieldsPlugin {
  getPaymentDefaultOption() {
    return {
      idIssuerBank: 'hipay-ideal-issuer-bank'
    };
  }

  getPaymentName() {
    return 'ideal';
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