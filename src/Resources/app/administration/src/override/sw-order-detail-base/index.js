const { Criteria } = Shopware.Data;

import template from './sw-order-detail-base.html.twig';
import './sw-order-detail-base.scss';

/**
 * Order details base component
 */
Shopware.Component.override('sw-order-detail-base', {
  template,
  inject: ['hipayService'],
  computed: {
    orderCriteria() {
      const criteria = this.$super('orderCriteria');

      criteria.addAssociation('hipayOrder');
      criteria.addAssociation('hipayOrder.captures');
      criteria.addAssociation('hipayOrder.refunds');
      criteria.addAssociation('hipayOrder.statusFlows');

      return criteria;
    },
    datasource() {
      return this.order.extensions.hipayOrder.statusFlows
        .sort(
          // antechronological sorting
          (a, b) =>
            new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
        )
        .map((statusFlow) => {
          return {
            ...statusFlow,
            description: statusFlow.name ? `${statusFlow.name} (${statusFlow.code})` : statusFlow.code,
            createdAt: new Date(statusFlow.createdAt).toLocaleString(),
            amount: this.hipayService.getCurrencyFormater(this.order.currency.isoCode).format(statusFlow.amount)
          };
        });
    },
    columns() {
      return [
        { property: 'description', label: 'Status' },
        { property: 'createdAt', label: 'Date' },
        { property: 'message', label: 'Message' },
        { property: 'amount', label: 'Amount'}
      ];
    },
    getTitle() {
      return this.$t('hipay.transaction.title') +' #'+this.order.extensions.hipayOrder.transactionReference
    },
    isHipayPayment() {
      return this.transaction.paymentMethod.formattedHandlerIdentifier.startsWith('handler_hipay')
    }
  },
  watch: {
    order() {
      this.$root.$emit('order-loaded', this.order);
    }
  }
});
