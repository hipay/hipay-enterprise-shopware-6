/**
 * Component for hipay settings
 * Google Pay
 */
import template from './hipay-settings-googlepay.html.twig';

Shopware.Component.register('hipay-settings-googlepay', {
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
  methods: {
    updateDisplayName(newValue) {
      if (!this.paymentMethod.customFields) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, displayName: newValue };
      } else {
        this.paymentMethod.customFields.displayName = newValue;
      }
    },
  }
});
