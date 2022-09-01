import template from "./hipay-check-server-access.html.twig";

const { Component, Mixin } = Shopware;

/**
 * Component for the plugin configuration
 * Add a button who test keys with HiPay Api
 */
Component.register("hipay-check-server-access", {
  template,
  inject: ["hipayService"],
  mixins: [Mixin.getByName("notification")],

  props: {
    value: {
      required: false,
    },
  },

  data() {
    return {
      isLoading: false,
      success: false,
    };
  },

  methods: {
    completeSucess() {
      this.sucess = false;
    },
    validateConfig(environment) {
      this.isLoading = true;

      const title = this.$tc("hipay.config.checkAccess.title");

      this.hipayService.validateConfig(this.getConfig())
        .then((response) => {

          if (!response.success) {
            throw new Error(response.message);
          }
          
          this.createNotificationSuccess({
            title,
            message: this.$tc("hipay.config.checkAccess.success"),
          });

          this.success = true;
        })
        .catch((error) => {
          this.createNotificationError({
            title,
            message: error.message || this.$tc("hipay.config.checkAccess.failure"),
          }); 
        })
        .finally(() => (this.isLoading = false));
    },
    getConfig() {
      let systemConfigComponent = this.$parent;
      while (!systemConfigComponent.hasOwnProperty("actualConfigData")) {
        systemConfigComponent = systemConfigComponent.$parent;
      }

      const selectedSalesChannelId = systemConfigComponent.currentSalesChannelId;
      const config = systemConfigComponent.actualConfigData;

      return Object.assign(
        {},
        config.null,
        config[selectedSalesChannelId],
        {environment: this.$parent.bind.value}
      );
    },
  },
});
