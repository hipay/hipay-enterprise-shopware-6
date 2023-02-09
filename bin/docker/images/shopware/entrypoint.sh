#!/bin/bash

set -e

echo "*******************************************************"
echo "** CUSTOM POST ENTRYPOINT **"
echo "*******************************************************"
echo ""

echo "SETTING ACTUAL URL...."
BASE_URL="http://localhost"

sudo chown -R www-data:www-data /var/www/html/var
sudo chmod -R g+w /var/www/html/var

if [ ! -z $APP_URL ]; then
    sudo mysql -u root --password=root -D shopware -e "update sales_channel_domain set url='$APP_URL' where url='$BASE_URL';"
    bin/console app:url-change:resolve reinstall-apps
    bin/console cache:clear --quiet
    BASE_URL="$APP_URL"
fi

echo "-----------------------------------------------------"
echo "ACTIVATING HIPAY PAYMENT MODULE"
# Activate custom module
composer require hipay/hipay-fullservice-sdk-php giggsey/libphonenumber-for-php
bin/console plugin:refresh --quiet
bin/console plugin:install --activate HiPayPaymentPlugin
bin/console cache:clear --quiet

if [ "$XDEBUG_ENABLED" = "1" ]; then
    if ! grep "xdebug.start_with_request = yes" /etc/php/$PHP_VERSION/fpm/conf.d/20-xdebug.ini >/dev/null; then
        printf "\nxdebug.start_with_request = yes\n" | sudo tee -a /etc/php/$PHP_VERSION/fpm/conf.d/20-xdebug.ini >/dev/null
    fi
    if ! grep "xdebug.start_with_request = yes" /etc/php/$PHP_VERSION/cli/conf.d/20-xdebug.ini >/dev/null; then
        printf "\nxdebug.start_with_request = yes\n" | sudo tee -a /etc/php/$PHP_VERSION/cli/conf.d/20-xdebug.ini >/dev/null
    fi

    sudo service php$PHP_VERSION-fpm reload
fi
sudo chown -R www-data:www-data /var/www/html/custom/plugins/HiPayPaymentPlugin
sudo chmod -R g+w /var/www/html/custom/plugins/HiPayPaymentPlugin

echo "-----------------------------------------------------"
echo "SHOP URL: $BASE_URL"

# we still need this to allow custom events
# such as our BUILD_PLUGIN feature to exit the container
if [[ ! -z "$DOCKWARE_CI" ]]; then
    # CONTAINER WAS STARTED IN NON-BLOCKING CI MODE...."
    # DOCKWARE WILL NOW EXIT THE CONTAINER"
    echo ""
else
    tail -f /dev/null
fi
