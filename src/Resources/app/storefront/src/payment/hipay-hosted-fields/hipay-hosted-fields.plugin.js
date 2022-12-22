import Plugin from 'src/plugin-system/plugin.class';

/**
 * Plugin hipay for hosted fields
 */
export default class HipayHostedFieldsPlugin extends Plugin {
  static options = {
    username: null,
    password: null,
    environment: null,
    lang: null,
    idCardHolder: 'hipay-card-holder',
    idCardNumber: 'hipay-card-number',
    idExpiryDate: 'hipay-expiry-date',
    idCvc: 'hipay-cvc',
    idResponse: 'hipay-response',
    cvcHelp: false,
    errorClass: 'is-invalid',
    errorPrefix: 'error',
    styles: null
  };

  /**
   * Plugin initialisation
   */
  init() {
    this._configHostedFields = this._getConfigHostedFields();
    this._form = document.querySelector('#' + this.options.idResponse).form;

    this._cardInstance = HiPay(this.options).create(
      'card',
      this._configHostedFields
    );

    this._registerEvents();
  }

  _registerEvents() {
    this._cardInstance.on('ready', () => {
      // error handler
      this._cardInstance.on('inputChange', this._inputErrorHandler.bind(this));
      this._cardInstance.on('blur', this._inputErrorHandler.bind(this));

      const inputResponse = document.querySelector(
        '#' + this.options.idResponse
      );

      let valid = false;
      // Generate
      this._cardInstance.on('change', (response) => {
        valid = response.valid;
        if (valid) {
          this._cardInstance.getPaymentData().then((result) => {
            inputResponse.setAttribute('value', JSON.stringify(result));
          });
        } else {
          inputResponse.setAttribute('value', '');
        }
      });

      // handle errors on form validation
      inputResponse.addEventListener('invalid', (e) => {
        if (!valid) {
          this._cardInstance.getPaymentData().then(
            () => {},
            (result) => {
              inputResponse.setAttribute('value', '');
              result.forEach((element) =>
                this._errorHandler(element.field, true, element.error)
              );
            }
          );
        }
      });
    });
  }

  _inputErrorHandler(fieldControl) {
    this._errorHandler(
      fieldControl.element,
      !fieldControl.validity.valid,
      fieldControl.validity.error
    );
  }

  _errorHandler(element, hasError = false, errorMessage = '') {
    const targetId = this._configHostedFields.fields[element].selector;
    const node = document.querySelector('#' + targetId);

    if (hasError) {
      node.classList.add(this.options.errorClass);
    } else {
      node.classList.remove(this.options.errorClass);
    }

    document.querySelector(
      '#' + this.options.errorPrefix + '-' + targetId
    ).innerHTML = errorMessage;
  }

  /**
   * Generate hosted fields configuration
   */
  _getConfigHostedFields() {
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
