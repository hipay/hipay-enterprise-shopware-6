/**
 * Plugin hipay for hosted fields
 */
export default class HipayHostedFieldsPlugin extends window.PluginBaseClass {


  static options = {
    username: null,
    password: null,
    environment: null,
    lang: null,
    idResponse: 'hipay-response',
    cvcHelp: false,
    errorClass: 'is-invalid',
    errorPrefix: 'error',
    styles: null,
    brand: null
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
    this._registerTosGuard();
  }

  destroy() {
    document.removeEventListener('submit', this._onTosSubmit, true);
    super.destroy();
  }

  _getTos() {
    return document.querySelector('input[name=tos]');
  }

  _isTosAccepted() {
    const tos = this._getTos();

    return !tos || tos.checked;
  }

  _registerTosGuard() {
    this._onTosSubmit = (event) => {
      if (event.target !== this._form || this._isTosAccepted()) {
        return;
      }

      event.preventDefault();
      event.stopImmediatePropagation();
      this._scrollToTos();
    };
    document.addEventListener('submit', this._onTosSubmit, true);
  }

  _scrollToTos() {
    const tos = this._getTos();
    if (!tos) {
      return;
    }

    tos.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (tos.reportValidity) {
      tos.reportValidity();
    }
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
          this._cardInstance.getPaymentData().then(
            (result) => {
              inputResponse.setAttribute('value', JSON.stringify(result));
            },
            (errors) => {
              inputResponse.setAttribute('value', '');
              errors.forEach((element) =>
                this._errorHandler(element.field, true, element.error)
              );
            }
          );
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

      this._form.addEventListener('submit', (e) => {
        if (this.hasPaymentData()) {
          return;
        }

        e.preventDefault();
        const target = e.currentTarget;

        this._cardInstance.getPaymentData().then(
          (result) => {
            inputResponse.setAttribute('value', JSON.stringify(result));
            target.submit();
          },
          (errors) => {
            inputResponse.setAttribute('value', '');
            errors.forEach((element) =>
              this._errorHandler(element.field, true, element.error)
            );
          }
        );
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
      `#${this.options.errorPrefix}-${targetId}`
    );
    if (errorNode) {
      errorNode.innerHTML = errorMessage;
    }
  }

  hasPaymentData() {
    return false;
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
