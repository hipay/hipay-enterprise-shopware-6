import HipayHostedFieldsPlugin from '../hipay-hosted-fields.plugin';

/**
 * Plugin hipay for hosted fields
 */
export default class HandlerHipaySepadirectdebitPlugin extends HipayHostedFieldsPlugin {
  getPaymentDefaultOption() {
    return {
      firstname: 'hipay-sdd-firstname',
      lastname: 'hipay-sdd-lastname',
      iban: 'hipay-sdd-iban',
      gender: 'hipay-sdd-gender',
      bank_name: 'hipay-sdd-bank-name',
      genderValue: 'U',
      firstnameValue: '',
      lastnameValue: ''
    };
  }

  getPaymentName() {
    return 'sdd';
  }

  /**
   * Generate hosted fields configuration
   */
  getConfigHostedFields() {
    const config = {
      fields: {
        firstname: {
          selector: this.options.firstname,
          defaultValue: this.options.firstnameValue
        },
        lastname: {
          selector: this.options.lastname,
          defaultValue: this.options.lastnameValue
        },
        iban: {
          selector: this.options.iban
        },
        gender: {
          selector: this.options.gender,
          defaultValue: this.options.genderValue
        },
        bank_name: {
          selector: this.options.bank_name
        }
      }
    };

    if (this.options.styles) {
      config.styles = this.options.styles;
    }

    return config;
  }
}