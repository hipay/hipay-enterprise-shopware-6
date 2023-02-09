#!/bin/bash

set -e

version="1.0.0"
base_dir=$(pwd)
built_dir="hipay_enterprise"

function package() {
    # Copy src content to new directory
    rm -rf $built_dir
    mkdir $built_dir
    cp -R src $built_dir/src

    # Copy required files from root directory
    cp composer.json $built_dir/
    cp *.md $built_dir/

    # Prepare files before install
    sed -i "/\"shopware\\/core\"/d" $built_dir/composer.json
    sed -i "/\"shopware\\/storefront\"/d" $built_dir/composer.json

    # Install production dependencies
    cd $built_dir
    composer validate
    composer install --no-dev
    cd $base_dir

    # Prepare files after install
    cp composer.json $built_dir/

    # Zip built directory
    rm -rf package-ready-for-shopware/*.zip
    zip -r package-ready-for-shopware/hipay-enterprise-shopware-6-$version.zip $built_dir

    # Remove built directory
    rm -rf $built_dir
}

function show_help() {
    cat <<EOF
    Usage: $me [options]

    options:
        -h, --help           Show this help
        -v, --version        Configure version for package
EOF
}

function parse_args() {
    while [[ $# -gt 0 ]]; do
        opt="$1"
        shift

        case "$opt" in
        -h | \? | --help)
            show_help
            exit 0
            ;;
        esac
        case "$opt" in
        -v | --version)
            version="$1"
            shift
            ;;
        esac
    done
}

function main() {
    parse_args "$@"
    package
}

main "$@"
