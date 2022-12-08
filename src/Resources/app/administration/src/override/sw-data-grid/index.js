import template from './sw-data-grid.html.twig';
import './sw-data-grid.scss';

/**
 * Data grid component
 */
Shopware.Component.override('sw-data-grid', {
  template,
  methods: {
    onValueChange(item, value) {
      this.$emit('quantity-change', value, item);
    }
  }
});
