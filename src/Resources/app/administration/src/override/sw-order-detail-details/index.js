import template from './sw-order-detail-details.html.twig';
import './sw-order-detail-details.scss';

/**
 * Order details details component
 */
Shopware.Component.override('sw-order-detail-details', {
  template,
  inject: ['hipayService'],
  computed: {
    datasource() {
      return this.order.extensions.hipayOrder.statusFlows
        .sort(
          // antechronological sorting
          (a, b) =>
            new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
        )
        .map(statusFlow => {
          return {
            ...statusFlow,
            description: statusFlow.name
              ? `${statusFlow.name} (${statusFlow.code})`
              : statusFlow.code,
            createdAt: new Date(statusFlow.createdAt).toLocaleString(),
            amount: this.hipayService
              .getCurrencyFormater(this.order.currency.isoCode)
              .format(statusFlow.amount)
          };
        });
    },
    columns() {
      return [
        { property: 'description', label: 'Status' },
        { property: 'createdAt', label: 'Date' },
        { property: 'message', label: 'Message' },
        { property: 'amount', label: 'Amount' }
      ];
    },
    getTitle() {
      return `${this.$t('hipay.transaction.title')} #${
        this.order.extensions.hipayOrder.transactionReference
      }`;
    },
    isHipayPayment() {
      return this.transaction.paymentMethod.formattedHandlerIdentifier.startsWith(
        'handler_hipay'
      );
    }
  }
});
