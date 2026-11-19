import { hideMethod } from './googlepay-eligibility';

/**
 * Plugin hipay for Google Pay
 */
export default class HandlerHipayGooglePayPlugin extends window.PluginBaseClass {

  static options = {
    username: null,
    password: null,
    environment: null,
    lang: null,
    debug: false,
    displayName: null,
    amount: null,
    currency: null,
    countryCode: null,
    supportedNetworks: ['visa', 'mastercard', 'maestro', 'cb'],
    idResponse: 'hipay-response',
    selector: 'hipay-googlepay'
  };

  init() {
    // Remove global payment button
    const submit = document.querySelector('#confirmFormSubmit');
    if (submit) {
      submit.remove();
    }

    this._container = this.el.querySelector('#' + this.options.selector);
    this._form = document.querySelector('#' + this.options.idResponse).form;
    this._amount = this.options.amount;
    this._unavailable = false;

    this._hipay = new HiPay({
      username: this.options.username,
      password: this.options.password,
      environment: this.options.environment,
      lang: this.options.lang,
      debug: this.options.debug
    });

    this._createWallet();
    this._registerTosEvents();
    this._registerCartEvents();
  }

  destroy() {
    this._destroyWallet();
    if (this._observer) {
      this._observer.disconnect();
    }
    clearTimeout(this._refreshTimer);
    super.destroy();
  }

  /**
   * Create the wallet component. The SDK cannot update its request,
   * so any amount change goes through destroy() then a new create().
   */
  _createWallet() {
    try {
      this._wallet = this._hipay.create('wallet', {
        selector: this.options.selector,
        provider: 'googlepay',
        displayName: this.options.displayName,
        existingPaymentMethodRequired: false,
        request: {
          amount: this._amount,
          currencyCode: this.options.currency,
          countryCode: this.options.countryCode,
          supportedNetworks: this.options.supportedNetworks
        }
      });
    } catch (error) {
      console.error('[HiPay] Google Pay create failed:', error.cause?.code, error.cause?.message);
      this._hideMethod();
      return;
    }

    this._wallet.on('ready', () => this._showContainer());
    this._wallet.on('unavailable', () => {
      this._unavailable = true;
      this._hideMethod();
    });
    this._wallet.on('paymentAuthorized', (payload) => this._onPaymentAuthorized(payload));
    this._wallet.on('paymentCanceled', () => this._showContainer());
    this._wallet.on('paymentFailed', (payload) => {
      console.error('[HiPay] Google Pay payment failed:', payload?.code, payload?.details);
      this._setFailedMessage(true);
      this._showContainer();
    });
    this._wallet.on('warning', (payload) => console.warn('[HiPay] Google Pay warning:', payload));
    this._wallet.on('error', (payload) => console.error('[HiPay] Google Pay error:', payload));
  }

  _destroyWallet() {
    if (this._wallet) {
      this._wallet = this._wallet.destroy();
    }
  }

  _onPaymentAuthorized(payload) {
    const data = {
      token: payload.token,
      payment_product: payload.payment_product,
      brand: payload.brand,
      browser_info: payload.browser_info,
      device_fingerprint: payload.device_fingerprint,
      data_id: payload.data_id
    };

    document.querySelector('#' + this.options.idResponse).setAttribute('value', JSON.stringify(data));
    this._form.submit();
  }

  /**
   * Hide the whole Google Pay payment method (not eligible)
   */
  _hideMethod() {
    hideMethod(this.el);
  }

  _showContainer() {
    this._setFailedMessage(false);
    this._checkTermsOfService();
  }

  _setFailedMessage(visible) {
    const message = this.el.querySelector('#hipay-googlepay-error-message');
    const text = this.el.querySelector('#hipay-googlepay-failed-message');
    message.textContent = visible ? text.textContent : '';
  }

  /**
   * The wallet submits the form by itself, which bypasses the browser validation:
   * the button stays hidden until the terms of service are accepted.
   */
  _registerTosEvents() {
    this._onTosChange = (event) => {
      if (event.target && event.target.matches && event.target.matches('input[name=tos]')) {
        this._checkTermsOfService();
      }
    };
    document.addEventListener('change', this._onTosChange);
    this._checkTermsOfService();
  }

  _checkTermsOfService() {
    const tos = document.querySelector('#tos');
    const error = this.el.querySelector('#hipay-googlepay-error-message');
    const tosMessage = this.el.querySelector('#hipay-googlepay-tos-message');

    if (!tos || tos.checked) {
      this._container.style.visibility = '';
      if (error.textContent === tosMessage.textContent) {
        error.textContent = '';
      }
    } else {
      this._container.style.visibility = 'hidden';
      error.textContent = tosMessage.textContent;
    }
  }

  /**
   * The confirm page reloads when the total changes, but not when the cart is edited
   * in the offcanvas: when it closes, fetch the page again and recreate the wallet
   * if the total moved.
   */
  _registerCartEvents() {
    if (!window.MutationObserver) {
      return;
    }

    this._observer = new MutationObserver((mutations) => {
      const closed = mutations.some((mutation) =>
        Array.from(mutation.removedNodes).some((node) => node.classList && node.classList.contains('offcanvas'))
      );
      if (closed) {
        clearTimeout(this._refreshTimer);
        this._refreshTimer = setTimeout(() => this._refreshAmount(), 300);
      }
    });
    this._observer.observe(document.body, { childList: true });
  }

  _refreshAmount() {
    fetch(window.location.href, { credentials: 'same-origin' })
      .then((response) => response.text())
      .then((html) => {
        const page = new DOMParser().parseFromString(html, 'text/html');
        const el = page.querySelector('[handler-hipay-google-pay-plugin]');
        if (!el) {
          return;
        }

        const options = JSON.parse(el.getAttribute('data-handler-hipay-google-pay-plugin-options'));
        if (options.amount === this._amount || this._unavailable) {
          return;
        }

        this._amount = options.amount;
        this.options.countryCode = options.countryCode;
        this._destroyWallet();
        this._createWallet();
      })
      .catch((error) => console.warn('[HiPay] Google Pay amount refresh failed:', error));
  }
}
