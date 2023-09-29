# Contributing to the HiPay Enterprise plugin for Shopware 6

> :warning: This module is supporting versions 6.4 and higher of Shopware 6

> :warning: This repository is a mirror of a private repository for this plugin, so we are not able to merge your PRs directly in GitHub. Any open PRs will be added to the main repository and closed in GitHub. Any contributor will be credited in the plugin's changelog.

Contributions to the HiPay Enterprise module for Shopware 6 should be made via GitHub [pull requests][pull-requests] and discussed using GitHub [issues][issues].

## Before you start

If you would like to make a significant change, please open an issue to discuss it, in order to minimize duplication of effort.

## Development

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

### Startup container

Go in the project root folder and enter this command:

```bash
./shopware.sh init
```

Your container is loading: wait for a few seconds while Docker installs Shopware and the HiPay module.

You can now test the HiPay Enterprise module in a browser with this URL: <http://localhost>

To connect to the back office, go to this URL: <http://localhost/admin>

The login and password are the default: admin / shopware.

You can test the module with your HiPay account configuration.

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
