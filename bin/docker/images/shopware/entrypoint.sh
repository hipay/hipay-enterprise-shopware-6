#!/bin/bash

set -e

echo "*******************************************************"
echo "** CUSTOM POST ENTRYPOINT **"
echo "*******************************************************"
echo ""

echo "SETTING ACTUAL URL...."

sudo mysql -u root --password=root -D shopware -e "update sales_channel_domain set url='$APP_URL' where url='http://localhost';"
bin/console app:url-change:resolve reinstall-apps
bin/console cache:clear --quiet

echo "-----------------------------------------------------"
echo "SHOP URL: $APP_URL"

# we still need this to allow custom events
# such as our BUILD_PLUGIN feature to exit the container
if [[ ! -z "$DOCKWARE_CI" ]]; then
    # CONTAINER WAS STARTED IN NON-BLOCKING CI MODE...."
    # DOCKWARE WILL NOW EXIT THE CONTAINER"
    echo ""
else
    tail -f /dev/null
fi
