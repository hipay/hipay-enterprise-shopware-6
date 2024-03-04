import './service/hipayService';

import './config/hipay-html-bloc';
import './config/hipay-help-bloc';
import './config/hipay-help-info';
import './config/hipay-check-server-access';
import './config/hipay-download-logs';

import './settings/hipay-settings-cards-selector';
import './settings/hipay-settings-multibanco';
import './settings/hipay-settings-applepay';
import './settings/hipay-settings-paypal';

import './override/sw-data-grid';
import './override/sw-order-detail-details';
import './override/sw-order-detail';
import './override/sw-settings-payment-detail';
import './override/sw-order-state-history-card';

import localeEn from './snippet/en-GB.json';
import localeDe from './snippet/de-DE.json';

Shopware.Locale.extend('en-GB', localeEn);
Shopware.Locale.extend('de-DE', localeDe);
