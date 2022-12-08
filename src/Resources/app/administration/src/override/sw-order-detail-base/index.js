const { Criteria } = Shopware.Data;

/**
 * Order details base component
 */
Shopware.Component.override('sw-order-detail-base', {
  computed: {
    orderCriteria() {
      const criteria = this.$super('orderCriteria');

      criteria.addAssociation('hipayOrder');
      criteria.addAssociation('hipayOrder.captures');
      criteria.addAssociation('hipayOrder.refunds');

      return criteria;
    }
  },
  watch: {
    order() {
      this.$root.$emit('order-loaded', this.order);
    }
  }
});
