# Contributing to the HiPay Enterprise plugin for Shopware 6

> :warning: This module is supporting versions 6.4 and higher of Shopware 6

> :warning: This repository is a mirror of a private repository for this plugin, so we are not able to merge your PRs directly in GitHub. Any open PRs will be added to the main repository and closed in GitHub. Any contributor will be credited in the plugin's changelog.

Contributions to the HiPay Enterprise module for Shopware 6 should be made via GitHub [pull requests][pull-requests] and discussed using GitHub [issues][issues].

## Before you start

If you would like to make a significant change, please open an issue to discuss it, in order to minimize duplication of effort.

### Install

Installation with Docker for testing

You can use this project with Docker and Docker Compose.
Requirements for your environment:

- Git (<https://git-scm.com/>)
- Docker (<https://docs.docker.com/engine/installation/>)
- Docker Compose (<https://docs.docker.com/compose/>)

Here is the procedure to be applied to a Linux environment:

Open a terminal and select the folder of your choice.

Clone the HiPay Enterprise shopware project in your environment with Git:

```bash
git clone https://github.com/hipay/hipay-enterprise-shopware-6.git
```

### Branch strategy

Due to the fact Shopware 6 offers different major versions with breaking changes, we are forced to offer different versions of our module.

During development, we have to use a specific branch according to a specific version of Shopware 6.

If you want to contribute on our module, you have to use a branch based on the correct one according to the Shopware 6 version :

| Shopware | Module (feature) | Module (release/hotfix) |
| --- | --- | --- |
| 6.4 | [develop-6-4](https://github.com/hipay/hipay-enterprise-shopware-6/tree/develop-6-4) | [main-6-4](https://github.com/hipay/hipay-enterprise-shopware-6/tree/main-6-4) |
| 6.5 | [develop-6-5](https://github.com/hipay/hipay-enterprise-shopware-6/tree/develop-6-5) | [main-6-5](https://github.com/hipay/hipay-enterprise-shopware-6/tree/main-6-5) |

### Setup

You need to add an alias to your `etc/hosts` file :

```bash
127.0.0.1     hipay.shopware.com
```

Make sure you have `APP_URL=https://hipay.shopware.com` param in your .env file.

### Startup container

Go in the project root folder and enter this command:

```bash
./shopware.sh init
```

Your container is loading: wait for a few seconds while Docker installs Shopware and the HiPay module.

Once the init is complete :

```bash
./shopware.sh l
```

You can now test the HiPay Enterprise module in a browser with this URL: <https://hipay.shopware.com>

To connect to the back office, go to this URL: <https://hipay.shopware.com/admin>

The login and password are the default: admin / shopware.

You can test the module with your HiPay account configuration.

### Debug

If you want to debug locally our CMS module, here are the steps :

- Verify the value of `XDEBUG_REMOTE_HOST` in your `.env` file you have copied in last step.
  - For Linux users, it should be `172.17.0.1` (value by default)
  - For MacOS users, replace it by `host.docker.internal`
- Then, create a Xdebug launch according to your IDE (here is for VSCode) :

  ```json
  {
    "name": "Shopware",
    "type": "php",
    "request": "launch",
    "hostname": "172.17.0.1", // Only for Linux users
    "port": 9000,
    "pathMappings": {
        "/var/www/html/custom/plugins/HiPayPaymentPlugin/src": "${workspaceFolder}/src",
        "/var/www/html/custom/plugins/HiPayPaymentPlugin/composer.json": "${workspaceFolder}/composer.json",
        "/var/www/html/custom/plugins/HiPayPaymentPlugin/tests": "${workspaceFolder}/tests",
        "/var/www/html": "${workspaceFolder}/web"
    },
    "runtimeArgs": [
        "-dxdebug.idekey=VSCODE"
    ]
  }
  ```

## Quality & Testing

In order to play tests, install first dependencies: `composer install`

### Unit tests

Unit test are made with phpunit.

Run it :
`vendor/bin/phpunit`

### Mutation testing

[Infection](https://infection.github.io/) is a PHP mutation testing library based on AST (Abstract Syntax Tree) mutations. It works as a CLI tool and can be executed from your project’s root.

Run it :
`vendor/bin/infection`

### Static Analisis

[PHPStan](https://github.com/phpstan/phpstan) focuses on finding errors in your code without actually running it. It catches whole classes of bugs even before you write tests for the code. It moves PHP closer to compiled languages in the sense that the correctness of each line of the code can be checked before you run the actual line.

`vendor/bin/phpstan analyse src --level 9`

### Code style

[PHP CS Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) The PHP Coding Standards Fixer (PHP CS Fixer) tool fixes your code to follow standards.

Run it :
`vendor/bin/php-cs-fixer src @rules=@Symfony`

### Making the request

Development takes place against the `develop` branch of this repository and pull requests should be opened against that branch.

[pull-requests]: https://github.com/hipay/hipay-enterprise-shopware-6/pulls
[issues]: https://github.com/hipay/hipay-enterprise-shopware-6/issues

### Watcher

Using `dockware/dev` image as developers, we can edit the front code of the admin side and the storefront side by using watchers.

There is 2 distinct watchers for each side of the website.

#### Admin watcher

The admin watcher will use the same domain than the current application domain.

You can launch it by entering the following command : `bash shopware.sh watch admin`

With our custom APP URL, which is <https://hipay.shopware.com>, the watcher will be reachable on <https://hipay.shopware.com:8888>

When this watcher is activated, the hot reload feature is enabled. That means when you edit a file from the `Resources > app > administration` folder, the openned pages based on this domain <https://hipay.shopware.com:8888> will be reloaded.

#### Storefront watcher

With Shopware version 6.5 and later, now the storefront watcher can use the same domain than the current application domain.

You can launch it by entering the following command : `bash shopware.sh watch front`

The watcher will be reachable on <https://hipay.shopware.com:9998>

When this watcher is activated, the hot reload feature is enabled. That means when you edit a file from the `Resources > app > storefront` folder, the openned pages based on this domain <https://hipay.shopware.com:9998> will be reloaded.

If you want to turn back to a storefront without watcher, you have to enter this command : `bash shopware.sh stop-watch`

### Ngrok

You can use ngrok to mount a public address with *HTTPS* protocol linked to your local Shopware application if necessary.

To do that, enter the following command : `ngrok http 443`

Once you have your generated URL from ngrok, you have to replace several occurrences before initiating your application :

- `proxy.conf` file : replace the `server_name` of the servers on ports `80` and `443`.
- `.env` file : replace the `APP_URL` environment variable.
- `shopware.sh` file (if you want to use this file) : replace the `defaultUrl` variable on the top of the file.

The link to the admin watcher with Ngrok is not configured but it could be. However, it's not very useful for us to mount the admin watcher with a Ngrok address.

On the other hand, the link to the storefront watcher with Ngrok is unfortunately impossible. It could be useful but we are forced to use a `localhost` address in *HTTP* when using the storefront watcher.
