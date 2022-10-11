/**
 * Component for hipay settings
 * Cards selector
 */
import template from './hipay-settings-cards-selector.html.twig';

Shopware.Component.register('hipay-settings-cards-selector', {
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
            availableCards: [
                {
                    label: "Carte Bancaire",
                    value: "cb"
                },
                {
                    label: "VISA",
                    value: "visa"
                },
                {
                    label: "MasterCard",
                    value: "mastercard"
                },
                {
                    label: "Amercian Express",
                    value: "american-express"
                },
                {
                    label: "Bancontact",
                    value: "bancontact"
                },
                {
                    label: "Maestro",
                    value: "maestro"
                }
            ]
        };
    },
    methods: {
        updateCardsValue(newValues) {
            if (this.paymentMethod.customFields === null) {
                this.paymentMethod.customFields = { cards: newValues };
            } else {
                this.paymentMethod.customFields.cards = newValues;
            }

        }
    }
});