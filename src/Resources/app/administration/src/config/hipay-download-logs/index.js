import template from './hipay-download-logs.html.twig';

const { Component, Mixin } = Shopware;

/**
 * Component for the plugin configuration
 * Add a button who test keys with HiPay Api
 */
Component.register('hipay-download-logs', {
  template,
  inject: ['hipayService'],
  mixins: [Mixin.getByName('notification')],

  props: {
    value: {
      required: false
    }
  },

  data() {
    return {
      isLoading: false,
      success: false
    };
  },

  methods: {
    completeSucess() {
      this.sucess = false;
    },
    downloadLogs() {
      this.isLoading = true;
      const title = this.$tc('hipay.config.logs.title');

      this.hipayService.getLogsArrayBuffer()
        .then((response) => {
          const blob = new Blob([response.data], {type: "application/octet-stream"});
          const objectUrl = window.URL.createObjectURL(blob);          

          const link = document.createElement('a');
          link.href = objectUrl;
          const name = response.headers['content-disposition'].split('"');
          link.download = name[1];

          document.body.appendChild(link);

          link.dispatchEvent(
            new MouseEvent('click', {bubbles: true, cancelable: true, view: window})
          );
          window.URL.revokeObjectURL(objectUrl);
          document.body.removeChild(link);
        })
        .catch((error) => {
          this.createNotificationError({
            title,
            message:
              error.message || this.$tc('hipay.config.logs.failure')
          });
        })
        .finally(() => (this.isLoading = false));
    },
  }
});
