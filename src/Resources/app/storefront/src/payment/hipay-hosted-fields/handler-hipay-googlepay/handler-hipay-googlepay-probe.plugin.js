import { hideMethod, markChecked } from './googlepay-eligibility';

/**
 * Checks Google Pay eligibility on page load
 */
export default class HandlerHipayGooglePayProbePlugin extends window.PluginBaseClass {

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
    selector: 'hipay-googlepay-probe'
  };

  init() {
    this._revealTimer = setTimeout(() => markChecked(this.el), 8000);

    try {
      this._hipay = new HiPay({
        username: this.options.username,
        password: this.options.password,
        environment: this.options.environment,
        lang: this.options.lang,
        debug: this.options.debug
      });

      this._wallet = this._hipay.create('wallet', {
        selector: this.options.selector,
        provider: 'googlepay',
        displayName: this.options.displayName,
        existingPaymentMethodRequired: false,
        request: {
          amount: this.options.amount,
          currencyCode: this.options.currency,
          countryCode: this.options.countryCode,
          supportedNetworks: this.options.supportedNetworks
        }
      });
    } catch (error) {
      console.error('[HiPay] Google Pay probe failed:', error.cause?.code, error.cause?.message);
      markChecked(this.el);
      return;
    }

    this._wallet.on('ready', () => {
      markChecked(this.el);
      this._destroyWallet();
    });
    this._wallet.on('unavailable', () => {
      hideMethod(this.el);
      markChecked(this.el);
      this._destroyWallet();
    });
  }

  destroy() {
    clearTimeout(this._revealTimer);
    this._destroyWallet();
    super.destroy();
  }

  _destroyWallet() {
    if (this._wallet) {
      this._wallet = this._wallet.destroy();
    }
  }
}
