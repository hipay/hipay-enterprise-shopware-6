#!/bin/bash

container=hipay-enterprise-shopware-6

if [ "$1" = '' ] || [ "$1" = '--help' ]; then
    printf "\n                                                      "
    printf "\n ==================================================== "
    printf "\n                  HIPAY'S HELPER                      "
    printf "\n ==================================================== "
    printf "\n                                                      "
    printf "\n      - init                      : Build images and run containers   "
    printf "\n      - init-without-sources      : Build images and run containers without mounting point  "
    printf "\n      - restart                   : Run containers if they exist yet  "
    printf "\n      - command                   : Run custom command in container   "
    printf "\n      - l                         : Show container logs               "
    printf "\n"
fi

if [ "$1" = 'init' ]; then
    docker-compose down -v
    rm -rf web/
    COMPOSE_HTTP_TIMEOUT=200
    docker-compose up -d --build
    docker cp $container:/var/www/html/ web/

    # Activate custom module
    docker exec hipay-enterprise-shopware-6 bash -c "
        composer req hipay/hipay-fullservice-sdk-php;
        bin/console plugin:refresh --quiet;
        bin/console plugin:install --activate HiPayPaymentPlugin;
        bin/console cache:clear --quiet;
    "
if [ "$1" = 'init-without-sources' ]; then
    docker-compose down -v
    COMPOSE_HTTP_TIMEOUT=200
    docker-compose up -d --build

    # Activate custom module
    docker exec hipay-enterprise-shopware-6 bash -c "
        composer req hipay/hipay-fullservice-sdk-php;
        bin/console plugin:refresh --quiet;
        bin/console plugin:install --activate HiPayPaymentPlugin;
        bin/console cache:clear --quiet;
    "
elif [ "$1" = 'restart' ]; then
    docker-compose stop
    docker-compose up -d
elif [ "$1" = 'command' ]; then
    docker exec $container $2
elif [ "$1" = 'l' ]; then
    docker compose logs -f
fi
