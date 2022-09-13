# HiPay Enterprise plugin for Shopware 6

[![Build Status](https://hook.hipay.org/badge-ci/build/pi-ecommerce/hipay-enterprise-shopware-6/develop?service=github)](https://hook.hipay.org/badge-ci/build/pi-ecommerce/hipay-enterprise-shopware-6/develop?service=github)
[![GitHub license](https://img.shields.io/badge/license-Apache%202-blue.svg)](https://raw.githubusercontent.com/hipay/hipay-enterprise-shopware-6/develop/LICENSE.md)

The **HiPay Enterprise module for Shopware 6** is a PHP module which allows you to accept payments in your Shopware online store, offering innovative features to reduce shopping cart abandonment rates, optimize success rates and enhance the purchasing process on merchants’ sites to significantly increase business volumes without additional investments in the solution CMS e-commerce Shopware.

## Getting started

Read the **[project documentation][doc-home]** for comprehensive information about the requirements, general workflow and installation procedure.

## Resources

- [Full project documentation][doc-home] — To have a comprehensive understanding of the workflow and get the installation procedure
- [HiPay Support Center][hipay-help] — To get technical help from HiPay
- [Issues][project-issues] — To report issues, submit pull requests and get involved (see [Apache 2.0 License][project-license])
- [Change log][project-changelog] — To check the changes of the latest versions
- [Contributing guidelines][project-contributing] — To contribute to our source code

## License

The **HiPay Enterprise module for Shopware 6** is available under the **Apache 2.0 License**. Check out the [license file][project-license] for more information.

[doc-home]: https://developer.hipay.com/doc/hipay-enterprise-shopware-6/
[hipay-help]: http://help.hipay.com
[project-issues]: https://github.com/hipay/hipay-enterprise-shopware-6/issues
[project-license]: LICENSE.md
[project-changelog]: CHANGELOG.md
[project-contributing]: CONTRIBUTING.md

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
