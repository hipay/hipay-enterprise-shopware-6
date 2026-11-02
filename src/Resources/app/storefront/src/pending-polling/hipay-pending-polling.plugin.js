/**
 * Polls the HiPay transaction status and routes the customer to the Shopware finalize step
 */
export default class HipayPendingPollingPlugin extends window.PluginBaseClass {
  static options = {
    statusUrl: null,
    finalizeUrl: null,
    interval: 5000,
    timeout: 600000
  };

  init() {
    this._startedAt = Date.now();
    this._poll();
  }

  _poll() {
    fetch(this.options.statusUrl, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then((response) => (response.ok ? response.json() : { status: 'pending' }))
      .catch(() => ({ status: 'pending' }))
      .then((result) => this._handle(result.status));
  }

  _handle(status) {
    if (status === 'success') {
      this._redirect(this.options.finalizeUrl);
    } else if (status === 'failed') {
      this._redirect(this._withParam(this.options.finalizeUrl, 'return', 'error'));
    } else if (Date.now() - this._startedAt >= this.options.timeout) {
      this._redirect(this.options.finalizeUrl);
    } else {
      window.setTimeout(() => this._poll(), this.options.interval);
    }
  }

  _withParam(url, key, value) {
    return url + (url.includes('?') ? '&' : '?') + key + '=' + encodeURIComponent(value);
  }

  _redirect(url) {
    window.location.href = url;
  }
}
