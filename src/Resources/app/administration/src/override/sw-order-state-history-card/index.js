import template from './sw-order-state-history-card.html';

/**
 * Inject credit cards selector
 */
Shopware.Component.override('sw-order-state-history-card', {
  template,
  computed: {
    isHipayPayment() {
      return this.transaction.paymentMethod.formattedHandlerIdentifier.startsWith('handler_hipay')
    }
  },
});
