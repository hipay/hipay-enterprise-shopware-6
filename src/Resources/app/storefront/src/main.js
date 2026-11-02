// Register them via the existing PluginManager
const PluginManager = window.PluginManager;

PluginManager.register(
  'HandlerHipayApplePayPlugin',
  () => import('./payment/hipay-hosted-fields/handler-hipay-applepay/handler-hipay-applepay.plugin'),
  '[handler-hipay-apple-pay-plugin]'
);

PluginManager.register(
  'HandlerHipayPaypalPlugin',
  () => import('./payment/hipay-hosted-fields/handler-hipay-paypal/handler-hipay-paypal.plugin'),
  '[handler-hipay-paypal-plugin]'
);

PluginManager.register(
  'HandlerHipayCreditcardPlugin',
  () => import('./payment/hipay-hosted-fields/handler-hipay-creditcard/handler-hipay-creditcard.plugin'),
  '[handler-hipay-creditcard-plugin]'
);

PluginManager.register(
  'HandlerHipayGiropayPlugin',
  () => import('./payment/hipay-hosted-fields/handler-hipay-giropay/handler-hipay-giropay.plugin'),
  '[handler-hipay-giropay-plugin]'
);

PluginManager.register(
  'HandlerHipayIdealPlugin',
  () => import('./payment/hipay-hosted-fields/handler-hipay-ideal/handler-hipay-ideal.plugin'),
  '[handler-hipay-ideal-plugin]'
);

PluginManager.register(
  'HandlerHipayMbwayPlugin',
  () => import('./payment/hipay-hosted-fields/handler-hipay-mbay/handler-hipay-mbway.plugin'),
  '[handler-hipay-mbway-plugin]'
);

PluginManager.register(
  'HipayPendingPollingPlugin',
  () => import('./pending-polling/hipay-pending-polling.plugin'),
  '[data-hipay-pending-polling]'
);

PluginManager.register(
  'HandlerHipayBizumPlugin',
  () => import('./payment/hipay-hosted-fields/handler-hipay-bizum/handler-hipay-bizum.plugin'),
  '[handler-hipay-bizum-plugin]'
);

PluginManager.register(
  'HandlerHipayBancomatpayPlugin',
  () => import('./payment/hipay-hosted-fields/handler-hipay-bancomatpay/handler-hipay-bancomatpay.plugin'),
  '[handler-hipay-bancomatpay-plugin]'
);

PluginManager.register(
  'HandlerHipaySepadirectdebitPlugin',
  () => import('./payment/hipay-hosted-fields/handler-hipay-sepa-direct-debit/handler-hipay-sepadirectdebit.plugin'),
  '[handler-hipay-sepadirectdebit-plugin]'
);

PluginManager.register(
  'HandlerHipayDefaultPlugin',
  () => import('./payment/hipay-hosted-fields/handler-hipay-default/handler-hipay-default.plugin'),
  '[handler-hipay-default-plugin]'
);

PluginManager.register(
  'HipayManageCreditCardPlugin',
  () => import('./account/payment/hipay-manage-creditcard/hipay-manage-creditcard.plugin'),
  '[hipay-manage-creditcard-plugin]'
);
