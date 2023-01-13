import HandlerHipayCreditcardPlugin from './payment/hipay-hosted-fields/handler-hipay-creditcard/handler-hipay-creditcard.plugin';
import HandlerHipayGiropayPlugin from './payment/hipay-hosted-fields/handler-hipay-giropay/handler-hipay-giropay.plugin';
import HandlerHipaySepadirectdebitPlugin from './payment/hipay-hosted-fields/handler-hipay-sepa-direct-debit/handler-hipay-sepadirectdebit.plugin';
import HandlerHipayIdealPlugin from './payment/hipay-hosted-fields/handler-hipay-ideal/handler-hipay-ideal.plugin';


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

PluginManager.register(
  'HandlerHipaySepadirectdebitPlugin',
  HandlerHipaySepadirectdebitPlugin,
  '[handler-hipay-sepadirectdebit-plugin]'
);

PluginManager.register(
  'HandlerHipayIdealPlugin',
  HandlerHipayIdealPlugin,
  '[handler-hipay-ideal-plugin]'
);


