/**
 * Component for hipay settings
 * Cards selector
 */
import template from './hipay-settings-paypal.html.twig';

Shopware.Component.register('hipay-settings-paypal', {
  template,
  inject: ['systemConfigApiService'],
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
      config: null,
      environment: null,
      apiUsername: null,
      apiPassword: null,
      authorizationHeader: null,
      availablePaymentProducts: null,
      isPayPalV2: null,
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
  mounted() {
    this.fetchConfig();
  },
  methods: {
    fetchConfig() {
      this.systemConfigApiService.getValues('HiPayPaymentPlugin').then(async (config) => {
        this.config = config;
        this.environment = config['HiPayPaymentPlugin.config.environment'];
        this.setApiCredentials();
        this.generateAuthorizationHeader();
        const products = await this.fetchAvailablePaymentProducts();
        this.processPayPalOptions(products);
      }).catch(() => {
        this.isLoading = false;
      });
    },
    setApiCredentials() {
      const loginKey = this.environment === 'Stage' ? 'publicLoginStage' : 'publicLoginProduction';
      const passwordKey = this.environment === 'Stage' ? 'publicPasswordStage' : 'publicPasswordProduction';
      this.apiUsername = this.config[`HiPayPaymentPlugin.config.${loginKey}`];
      this.apiPassword = this.config[`HiPayPaymentPlugin.config.${passwordKey}`];
    },
    generateAuthorizationHeader() {
      const credentials = `${this.apiUsername}:${this.apiPassword}`;
      const encodedCredentials = btoa(credentials);
      this.authorizationHeader = `Basic ${encodedCredentials}`;
    },
    async fetchAvailablePaymentProducts() {
      const baseUrl = this.environment === 'Stage'
        ? 'https://stage-secure-gateway.hipay-tpp.com/rest/v2/'
        : 'https://secure-gateway.hipay-tpp.com/rest/v2/';

      const url = new URL(`${baseUrl}available-payment-products.json`);
      url.searchParams.append('eci', 7);
      url.searchParams.append('operation', 4);
      url.searchParams.append('payment_product', 'paypal');
      url.searchParams.append('with_options', 'true');

      try {
        const response = await fetch(url, {
          method: 'GET',
          headers: {
            'Authorization': this.authorizationHeader,
            'Accept': 'application/json'
          }
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        this.availablePaymentProducts = await response.json();
        return this.availablePaymentProducts;
      } catch (error) {
        console.error('Error fetching available payment products:', error);
        return null;
      }
    },
    processPayPalOptions(products) {
      if (products && Array.isArray(products)) {
        const paypalProduct = products.find(product => product.code === 'paypal');
        if (paypalProduct && paypalProduct.options) {
          this.isPayPalV2 = paypalProduct.options.payer_id !== '' &&
            paypalProduct.options.provider_architecture_version === 'v1';
        } else {
          this.resetPayPalStatus();
        }
      } else {
        this.resetPayPalStatus();
      }
    },
    resetPayPalStatus() {
      this.isPayPalV2 = false;
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
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, height: newValue };
      } else {
        this.paymentMethod.customFields.height = newValue;
      }
    },
    updateBnpl(newValue) {
      if (this.paymentMethod.customFields === null) {
        this.paymentMethod.customFields = { ...this.paymentMethod.customFields, bnpl: newValue };
      } else {
        this.paymentMethod.customFields.bnpl = newValue;
      }
    }
  }
});
