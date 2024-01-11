/**
 * Component for hipay settings
 * Cards selector
 */
import template from './hipay-settings-applepay.html.twig';

Shopware.Component.register('hipay-settings-applepay', {
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
      buttonTypes: [
        {
          label: this.$tc('hipay.settings.applepay.buttonType.default'),
          value: "default"
        },
        {
          label: this.$tc('hipay.settings.applepay.buttonType.buy'),
          value: "buy"
        },
        {
          label: this.$tc('hipay.settings.applepay.buttonType.setup'),
          value: "set-up"
        },
        {
          label: this.$tc('hipay.settings.applepay.buttonType.donate'),
          value: "donate"
        },
        {
          label: this.$tc('hipay.settings.applepay.buttonType.checkout'),
          value: "check-out"
        },
        {
          label: this.$tc('hipay.settings.applepay.buttonType.processing'),
          value: "processing"
        },
        {
          label: this.$tc('hipay.settings.applepay.buttonType.book'),
          value: "book"
        },
        {
          label: this.$tc('hipay.settings.applepay.buttonType.subscribe'),
          value: "subscribe"
        }],
      buttonStyles: [
        {
          label: this.$tc('hipay.settings.applepay.buttonStyle.black'),
          value: "black"
        },
        {
          label: this.$tc('hipay.settings.applepay.buttonStyle.white'),
          value: "white"
        },
        {
          label: this.$tc('hipay.settings.applepay.buttonStyle.whiteOutline'),
          value: "white-outline"
        }],
    };
  },
  methods: {
    updateMerchantName(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, merchantName: newValue };
      } else {
        this.paymentMethod.customFields.merchantName = newValue;
      }
    },
    updateButtonType(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, buttonType: newValue };
      } else {
        this.paymentMethod.customFields.buttonType = newValue;
      }
    },
    updateButtonStyle(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, buttonStyle: newValue };
      } else {
        this.paymentMethod.customFields.buttonStyle = newValue;
      }
    },
    updateMerchantId(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, merchantId: newValue };
      } else {
        this.paymentMethod.customFields.merchantId = newValue;
      }
    },
  }
});
