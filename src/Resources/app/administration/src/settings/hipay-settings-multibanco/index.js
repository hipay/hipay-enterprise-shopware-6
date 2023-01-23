/**
 * Component for hipay settings
 * Cards selector
 */
import template from './hipay-settings-multibanco.html.twig';

Shopware.Component.register('hipay-settings-multibanco', {
  template,
  props: {
    isLoading: {
      type: Boolean,
      required: true
    },
    paymentMethod: {
      type: Object,
      required: true
    }
  },
  data() {
    return {
      availableLimits: [
        {
          label: `3 ${this.$tc('hipay.config.days')}`,
          value: '3'
        },
        {
          label: `30 ${this.$tc('hipay.config.days')}`,
          value: '30'
        },
        {
          label: `90 ${this.$tc('hipay.config.days')}`,
          value: '90'
        }
      ]
    };
  },
  methods: {
    updateLimitValue(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, expiration_limit: newValue };
      } else {
        this.paymentMethod.customFields.expiration_limit = newValue;
      }
    }
  }
});
