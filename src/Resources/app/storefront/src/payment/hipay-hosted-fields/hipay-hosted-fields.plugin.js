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
    // ensure is a abstract class
    if (this.constructor === HipayHostedFieldsPlugin) {
      throw new TypeError(
        'Class "HipayHostedFieldsPlugin" cannot be instantiated directly'
      );
    }

    this.options = {
      ...this.getPaymentDefaultOption(),
      ...this.options
    };

    this._configHostedFields = this.getConfigHostedFields();
    this._form = document.querySelector('#' + this.options.idResponse).form;

    this._cardInstance = new HiPay(this.options).create(
      this.getPaymentName(),
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
      this._cardInstance.on('change', response => {
        valid = response.valid;
        if (valid) {
          this._cardInstance.getPaymentData().then(result => {
            inputResponse.setAttribute('value', JSON.stringify(result));
          });
        } else {
          inputResponse.setAttribute('value', '');
        }
      });

      // handle errors on form validation
      inputResponse.addEventListener('invalid', e => {
        if (!valid) {
          this._cardInstance.getPaymentData().then(
            () => {},
            result => {
              inputResponse.setAttribute('value', '');
              result.forEach(element =>
                this._errorHandler(element.field, true, element.error)
              );
            }
          );
        }
      });

      this._form.addEventListener('submit', e => {
        e.preventDefault();
        const target = e.currentTarget;

        this._cardInstance.getPaymentData().then(result => {
          inputResponse.setAttribute('value', JSON.stringify(result));
          target.submit();
        });
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

    const errorNode = document.querySelector(
      '#' + this.options.errorPrefix + '-' + targetId
    );
    if (errorNode) {
      errorNode.innerHTML = errorMessage;
    }
  }

  getPaymentName() {
    throw new Error('Method "getPaymentName" must be implemented');
  }

  /**
   * Generate hosted fields configuration
   */
  getConfigHostedFields() {
    throw new Error('Method "getConfigHostedFields" must be implemented');
  }
}
