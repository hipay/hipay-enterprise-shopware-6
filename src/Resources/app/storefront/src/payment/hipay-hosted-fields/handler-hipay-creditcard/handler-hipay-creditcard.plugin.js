import HipayHostedFieldsPlugin from '../hipay-hosted-fields.plugin';

/**
 * Plugin hipay for hosted fields
 */
export default class HandlerHipayCreditcardPlugin extends HipayHostedFieldsPlugin {

  init() {
    super.init();

    const inputResponse =  document.querySelector('#' + this.options.idResponse);
    const cardInstance = this._cardInstance;
    document.querySelector('#hipay-multiuse').addEventListener('change', function(e) {
      cardInstance.setMultiUse(e.target.checked);
    });

    document.querySelectorAll('input[name="hipay-token"]').forEach(
      radio => radio.addEventListener('change', function(e) {
          var value = '';
          var displayNewCardBlock = 'block';

          if(e.target.getAttribute('value')) {
            displayNewCardBlock = 'none';
            value = {
              token: e.target.value,
              device_fingerprint: cardInstance.sdkInstance.getDeviceFingerprint(),
              browser_info: cardInstance.sdkInstance.getBrowserInfo(),
              payment_product: e.target.dataset.brand             
            };
          }
          inputResponse.setAttribute('value', JSON.stringify(value));
          document.querySelector('#hipay-new-creditcard-block').style.display = displayNewCardBlock;          
      })
    );
  }

  getPaymentDefaultOption() {
    return {
      idCardHolder: 'hipay-card-holder',
      idCardNumber: 'hipay-card-number',
      idExpiryDate: 'hipay-expiry-date',
      idCvc: 'hipay-cvc',
      firstnameValue: '',
      lastnameValue: ''
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
          selector: this.options.idCardHolder,
          defaultFirstname: this.options.firstnameValue,
          defaultLastname: this.options.lastnameValue
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