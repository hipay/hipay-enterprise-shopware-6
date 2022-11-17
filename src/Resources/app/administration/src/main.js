import './service/hipayService';

import './config/hipay-html-bloc';
import './config/hipay-help-bloc';
import './config/hipay-check-server-access';

import './settings/hipay-settings-cards-selector';

import './override/sw-settings-payment-detail';

import localeEn from './snippet/en-GB.json';
import localeDe from './snippet/de-DE.json';

Shopware.Locale.extend('en-GB', localeEn);
Shopware.Locale.extend('de_DE', localeDe);
