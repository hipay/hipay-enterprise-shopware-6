/**
 * Component for the plugin configuration
 * Add HTML
 */
Shopware.Component.register('hipay-html-bloc', {
    template: `<div v-html="html()"></div>`,
    methods: {
        html() {
            const tag = this.$attrs.name.slice(this.$attrs.name.lastIndexOf('.') + 1);
            return '<' + tag + '>' + this.$tc(this.$parent.bind.value) + '</' + tag + '>';
        }
    }
});