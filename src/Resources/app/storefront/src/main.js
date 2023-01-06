import HipayHostedFieldsPlugin from './payment/hipay-hosted-fields/hipay-hosted-fields.plugin';
import HandlerHipayCreditcardPlugin from './payment/hipay-hosted-fields/handler-hipay-creditcard/handler-hipay-creditcard.plugin';
import HandlerHipayGiropayPlugin from './payment/hipay-hosted-fields/handler-hipay-giropay/handler-hipay-giropay.plugin';
// Register them via the existing PluginManager
const PluginManager = window.PluginManager;

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


