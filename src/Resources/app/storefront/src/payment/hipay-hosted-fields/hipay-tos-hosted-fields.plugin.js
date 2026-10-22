import HipayHostedFieldsPlugin from './hipay-hosted-fields.plugin';

/**
 * Hosted fields gated by the terms of service checkbox:
 */
export default class HipayTosHostedFieldsPlugin extends HipayHostedFieldsPlugin {
  getFieldsGroupId() {
    throw new Error('Method "getFieldsGroupId" must be implemented');
  }

  getTosMessageId() {
    throw new Error('Method "getTosMessageId" must be implemented');
  }

  init() {
    super.init();

    this._fieldsGroup = this.el.querySelector('#' + this.getFieldsGroupId());
    this._tosMessage = this.el.querySelector('#' + this.getTosMessageId());
    this._valid = false;

    this._onTosChange = (event) => {
      if (event.target && event.target.matches && event.target.matches('input[name=tos]')) {
        this._toggleFields();
      }
    };
    document.addEventListener('change', this._onTosChange);

    this._hiddenByTos = new Set();
    this._wrapper = this.el.closest('.hipay-hosted-fields-wrapper') || this.el.parentElement;

    if (this._wrapper && window.MutationObserver) {
      this._observer = new MutationObserver(() => this._toggleFields());
      this._observer.observe(this._wrapper, { childList: true, subtree: true });
    }

    this._toggleFields();

    this._cardInstance.on('ready', () => {
      this._cardInstance.on('change', (response) => {
        this._valid = !!response.valid;
        this._updateSubmitState();
      });
    });
  }

  _getTos() {
    return document.querySelector('input[name=tos]');
  }

  _getSubmitButton() {
    return document.querySelector('#confirmFormSubmit');
  }

  _isTosAccepted() {
    const tos = this._getTos();

    return !tos || tos.checked;
  }

  _toggleFields() {
    const accepted = this._isTosAccepted();

    if (this._fieldsGroup) {
      this._fieldsGroup.classList.toggle('d-none', !accepted);
      this._fieldsGroup.hidden = !accepted;
    }
    if (this._tosMessage) {
      this._tosMessage.classList.toggle('d-none', accepted);
      this._tosMessage.hidden = accepted;
    }

    this._toggleSdkElements(accepted);
    this._updateSubmitState();
  }

  _toggleSdkElements(accepted) {
    const containers = [this._wrapper, this.el].filter(Boolean);

    containers.forEach((container) => {
      Array.from(container.children).forEach((child) => {
        if (this._isKept(child)) {
          return;
        }

        if (!accepted) {
          this._hiddenByTos.add(child);
          child.classList.add('d-none');
          child.hidden = true;
        } else if (this._hiddenByTos.has(child)) {
          this._hiddenByTos.delete(child);
          child.classList.remove('d-none');
          child.hidden = false;
        }
      });
    });
  }

  _isKept(element) {
    return (
      element === this.el ||
      element === this._fieldsGroup ||
      element === this._tosMessage ||
      element.hasAttribute('data-hipay-tos-keep') ||
      element.matches('script, style, input') ||
      element.contains(this._tosMessage)
    );
  }

  _updateSubmitState() {
    const button = this._getSubmitButton();

    if (button) {
      button.disabled = !(this._isTosAccepted() && this._valid);
    }
  }

  destroy() {
    document.removeEventListener('change', this._onTosChange);
    if (this._observer) {
      this._observer.disconnect();
    }

    const button = this._getSubmitButton();
    if (button) {
      button.disabled = false;
    }
    if (super.destroy) {
      super.destroy();
    }
  }
}
