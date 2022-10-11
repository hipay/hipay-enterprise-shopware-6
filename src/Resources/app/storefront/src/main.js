import HipayHostedFieldsPlugin from './payment/hipay-hosted-fields/hipay-hosted-fields.plugin'

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('HipayHostedFieldsPlugin', HipayHostedFieldsPlugin, '[hipay-hosted-fields-plugin]');