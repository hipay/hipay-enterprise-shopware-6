import template from './hipay-help-info.html.twig';

/**
 * Component for the plugin configuration
 * Add stylized help info text
 */
Shopware.Component.register('hipay-help-info', {
  template,
  data() {
    return {
      text: this.$parent.bind.value
    };
  }
});
