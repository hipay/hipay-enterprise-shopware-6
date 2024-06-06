import HandlerHipayCreditcardPlugin from './payment/hipay-hosted-fields/handler-hipay-creditcard/handler-hipay-creditcard.plugin';
import HandlerHipayGiropayPlugin from './payment/hipay-hosted-fields/handler-hipay-giropay/handler-hipay-giropay.plugin';
import HandlerHipaySepadirectdebitPlugin from './payment/hipay-hosted-fields/handler-hipay-sepa-direct-debit/handler-hipay-sepadirectdebit.plugin';
import HandlerHipayIdealPlugin from './payment/hipay-hosted-fields/handler-hipay-ideal/handler-hipay-ideal.plugin';
import HandlerHipayMbwayPlugin from './payment/hipay-hosted-fields/handler-hipay-mbay/handler-hipay-mbway.plugin';
import HandlerHipayApplePayPlugin from './payment/hipay-hosted-fields/handler-hipay-applepay/handler-hipay-applepay.plugin';
import HandlerHipayPaypalPlugin from './payment/hipay-hosted-fields/handler-hipay-paypal/handler-hipay-paypal.plugin';
import HipayManageCreditCardPlugin from './account/payment/hipay-manage-creditcard/hipay-manage-creditcard.plugin';

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;

PluginManager.register(
  'HandlerHipayApplePayPlugin',
  HandlerHipayApplePayPlugin,
  '[handler-hipay-apple-pay-plugin]'
);

PluginManager.register(
  'HandlerHipayPaypalPlugin',
  HandlerHipayPaypalPlugin,
  '[handler-hipay-paypal-plugin]'
);

PluginManager.register(
  'HandlerHipayCreditcardPlugin',
  HandlerHipayCreditcardPlugin,
  '[handler-hipay-creditcard-plugin]'
);

PluginManager.register(
  'HandlerHipayGiropayPlugin',
  HandlerHipayGiropayPlugin,
  '[handler-hipay-giropay-plugin]'
);

PluginManager.register(
  'HandlerHipayIdealPlugin',
  HandlerHipayIdealPlugin,
  '[handler-hipay-ideal-plugin]'
);

PluginManager.register(
  'HandlerHipayMbwayPlugin',
  HandlerHipayMbwayPlugin,
  '[handler-hipay-mbway-plugin]'
);

PluginManager.register(
  'HandlerHipaySepadirectdebitPlugin',
  HandlerHipaySepadirectdebitPlugin,
  '[handler-hipay-sepadirectdebit-plugin]'
);

PluginManager.register(
  'HipayManageCreditCardPlugin',
  HipayManageCreditCardPlugin,
  '[hipay-manage-creditcard-plugin]'
)