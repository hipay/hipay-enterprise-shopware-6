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
    printf "\n      - test                      : Run the configured tests  "
    printf "\n      - l                         : Show container logs               "
    printf "\n"
fi

if [ "$1" = 'init' ] || [ "$1" = 'init-without-sources' ]; then
    docker-compose down -v
    rm -rf web/

    COMPOSE_HTTP_TIMEOUT=200
    docker-compose up -d --build   
fi

if [ "$1" = 'init' ]; then
     docker cp $container:/var/www/html/ web/
elif [ "$1" = 'restart' ]; then
    docker-compose stop
    docker-compose up -d
elif [ "$1" = 'command' ]; then
    docker exec $container $2
elif [ "$1" = 'l' ]; then  
    docker compose logs -f
elif [ "$1" = 'test' ]; then

    find=false
    docker exec hipay-enterprise-shopware-6 bash -c "
        cd custom/plugins/HiPayPaymentPlugin
        composer install -q
    "

    if [ "$2" = '' ] || [ "$2" = "phpunit" ]; then  
        echo "----- PHPUNIT -----"
        docker exec hipay-enterprise-shopware-6 bash -c "
            cd custom/plugins/HiPayPaymentPlugin
            php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-html reports/coverage
        "
        find=true
    fi

    if [ "$2" = '' ] || [ "$2" = "phpstan" ]; then
        echo "----- PHPSTAN -----"
        docker exec hipay-enterprise-shopware-6 bash -c "
            cd custom/plugins/HiPayPaymentPlugin
            vendor/bin/phpstan analyse src --level 9 --xdebug --no-progress -vvv
        "
        find=true
    fi

    if [ "$2" = '' ] || [ "$2" = "infection" ]; then
        echo "----- INFECTION -----"
        docker exec hipay-enterprise-shopware-6 bash -c "
            export XDEBUG_MODE=coverage
            cd custom/plugins/HiPayPaymentPlugin
            php -d xdebug.mode=coverage vendor/bin/infection --logger-html=reports/infection.html --min-msi=90 --threads=4
        "
        find=true
    fi

    if [ "$2" = '' ] || [ "$2" = "lint" ]; then
        echo "----- PHP CS FIXER -----"
        docker exec hipay-enterprise-shopware-6 bash -c "
            cd custom/plugins/HiPayPaymentPlugin
            vendor/bin/php-cs-fixer fix src --dry-run --rules=@Symfony
        "
        find=true
    fi

    if [ ! $find ]; then
        echo "Test option \"$2\" doesn't exist. Please use \"./shopware test [<phpunit|phpstan|infection|lint>]\""
    fi
fi