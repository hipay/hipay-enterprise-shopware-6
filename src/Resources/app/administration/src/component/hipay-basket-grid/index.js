import template from './hipay-basket-grid.html.twig';
import './hipay-basket-grid.scss';


Shopware.Component.register('hipay-basket-grid', {
  template,
  methods: {
    onValueChange(item, value) {
      this.$emit('quantity-change', value, item);
    }
  }
});