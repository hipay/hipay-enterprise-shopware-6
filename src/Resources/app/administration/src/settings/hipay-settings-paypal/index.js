/**
 * Component for hipay settings
 * Cards selector
 */
import template from './hipay-settings-paypal.html.twig';

Shopware.Component.register('hipay-settings-paypal', {
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
      colors: [
        {
          label: this.$tc('hipay.settings.paypal.color.gold'),
          value: "gold"
        },
        {
          label: this.$tc('hipay.settings.paypal.color.blue'),
          value: "blue"
        },
        {
          label: this.$tc('hipay.settings.paypal.color.black'),
          value: "black"
        },
        {
          label: this.$tc('hipay.settings.paypal.color.silver'),
          value: "silver"
        },
        {
          label: this.$tc('hipay.settings.paypal.color.white'),
          value: "white"
        }],
      shapes: [
        {
          label: this.$tc('hipay.settings.paypal.shape.pill'),
          value: "pill"
        },
        {
          label: this.$tc('hipay.settings.paypal.shape.rect'),
          value: "rect"
        }],
      labels: [
        {
          label: this.$tc('hipay.settings.paypal.label.paypal'),
          value: "paypal"
        },
        {
          label: this.$tc('hipay.settings.paypal.label.pay'),
          value: "pay"
        },
        {
          label: this.$tc('hipay.settings.paypal.label.subscribe'),
          value: "subscribe"
        },
        {
          label: this.$tc('hipay.settings.paypal.label.checkout'),
          value: "checkout"
        },
        {
          label: this.$tc('hipay.settings.paypal.label.buynow'),
          value: "buynow"
        }]
    };
  },
  methods: {
    updateMerchantPayPalId(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, merchantPayPalId: newValue };
      } else {
        this.paymentMethod.customFields.merchantPayPalId = newValue;
      }
    },
    updateColor(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, color: newValue };
      } else {
        this.paymentMethod.customFields.color = newValue;
      }
    },
    updateShape(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, shape: newValue };
      } else {
        this.paymentMethod.customFields.shape = newValue;
      }
    },
    updateLabel(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, label: newValue };
      } else {
        this.paymentMethod.customFields.label = newValue;
      }
    },
    updateHeight(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, height: newValue }; // TODO MIN AND MAX HEIGHT
      } else {
        this.paymentMethod.customFields.height = newValue;
      }
    },
  }
});
