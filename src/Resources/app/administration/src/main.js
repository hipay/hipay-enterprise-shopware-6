
import './service/hipayService';

import './config/hipay-html-bloc';
import './config/hipay-help-bloc';
import './config/hipay-check-server-access';

import localeEn from './i18n/en_GB.json';
import localeDe from './i18n/de_DE.json';

Shopware.Locale.extend('en-GB', localeEn);
Shopware.Locale.extend('de_DE', localeDe);