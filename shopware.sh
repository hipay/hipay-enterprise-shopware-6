#!/bin/bash

container=hipay-enterprise-shopware-6

if [ "$1" = '' ] || [ "$1" = '--help' ]; then
    printf "\n                                                      "
    printf "\n ==================================================== "
    printf "\n                  HIPAY'S HELPER                      "
    printf "\n ==================================================== "
    printf "\n                                                      "
    printf "\n      - init                      : Build images and run containers                       "
    printf "\n      - copy                      : Copy Shopware root directory to local web folder      "
    printf "\n      - init-without-sources      : Build images and run containers without mounting point"
    printf "\n      - restart                   : Run containers if they exist yet                      "
    printf "\n      - command                   : Run custom command in container                       "
    printf "\n      - test                      : Run the configured tests                              "
    printf "\n      - l                         : Show container logs                                   "
    printf "\n      - watch admin               : Watch admin front                                     "
    printf "\n      - watch store               : Watch store front                                     "
    printf "\n      - stop-watch                : Stop watching store front                             "
    printf "\n      - twig-format               : Prettier twig files                                   "
    printf "\n"
fi

if [ "$1" = 'init' ] || [ "$1" = 'init-without-sources' ]; then
    docker compose down -v
    COMPOSE_HTTP_TIMEOUT=200
    docker compose up -d --build
fi

if [ "$1" = 'init' ] || [ "$1" = 'copy' ]; then
    rm -rf web/
    docker cp $container:/var/www/html/ web/
elif [ "$1" = 'restart' ]; then
    docker compose stop
    docker compose up -d --build
elif [ "$1" = 'kill' ]; then
    docker compose down -v --rmi all
elif [ "$1" = 'command' ]; then
    docker exec $container $2
elif [ "$1" = 'l' ]; then
    docker compose logs -f
elif [ "$1" = 'watch' ] && [ "$2" = 'admin' ]; then
    docker exec $container bash -c "cd ../ && make watch-admin"
elif [ "$1" = 'watch' ] && [ "$2" = 'front' ]; then
    docker exec $container bash -c "cd ../ && make watch-storefront"
elif [ "$1" = 'stop-watch' ]; then
    docker exec $container bash -c "cd ../ && make stop-watch-storefront"
elif [ "$1" = 'twig-format' ]; then
    for f in $(find ./src -name '*.html.twig'); do
        tmpFile="${f/html.twig/"bck.html.twig"}"
        cp $f $tmpFile
        sed -ri "s|@([[:alnum:]])|___\1|g; s|(\{\{.*)\\\$([[:alnum:]])|\1__X__\2|g" $tmpFile
        prettier -w $tmpFile --config .prettierrc
        sed -ri "s|___([[:alnum:]])|@\1|g; s|(\{\{.*)__X__([[:alnum:]])|\1\\\$\2|g" $tmpFile
        mv $tmpFile $f
    done
elif [ "$1" = 'test' ]; then

    find=false
    docker exec $container bash -c "
        cd custom/plugins/HiPayPaymentPlugin
        composer install -q
    "

    if [ "$2" = '' ] || [ "$2" = "phpunit" ]; then
        echo "----- PHPUNIT -----"
        docker exec $container bash -c "
            cd custom/plugins/HiPayPaymentPlugin
            php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-html reports/coverage
        "
        find=true
    fi

    if [ "$2" = '' ] || [ "$2" = "phpstan" ]; then
        echo "----- PHPSTAN -----"
        docker exec $container bash -c "
            cd custom/plugins/HiPayPaymentPlugin
            vendor/bin/phpstan --version
            vendor/bin/phpstan analyse src --level 7 --xdebug --no-progress -vvv
        "
        find=true
    fi

    if [ "$2" = '' ] || [ "$2" = "infection" ]; then
        echo "----- INFECTION -----"
        docker exec $container bash -c "
            export XDEBUG_MODE=coverage
            cd custom/plugins/HiPayPaymentPlugin
            php -d xdebug.mode=coverage vendor/bin/infection --logger-html=reports/infection.html --min-covered-msi=80 --threads=4
        "
        find=true
    fi

    if [ "$2" = '' ] || [ "$2" = "lint" ]; then
        echo "----- PHP CS FIXER -----"
        docker exec $container bash -c "
            cd custom/plugins/HiPayPaymentPlugin
            vendor/bin/php-cs-fixer fix src --dry-run --rules=@Symfony
        "
        find=true
    fi

    if ! $find; then
        echo "Test option \"$2\" doesn't exist. Please use \"./shopware test [<phpunit|phpstan|infection|lint>]\""
    fi
fi
