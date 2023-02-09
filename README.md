# HiPay Enterprise plugin for Shopware 6

[![GitHub license](https://img.shields.io/badge/license-Apache%202-blue.svg)](https://raw.githubusercontent.com/hipay/hipay-enterprise-shopware-6/develop/LICENSE.md)

The **HiPay Enterprise module for Shopware 6** is a PHP plugin which allows you to accept payments in your Shopware online store, offering innovative features to reduce shopping cart abandonment rates, optimize success rates and enhance the purchasing process on merchants’ sites to significantly increase business volumes without additional investments in the solution CMS e-commerce Shopware.

## Getting started

Read the **[project documentation][doc-home]** for comprehensive information about the requirements, general workflow and installation procedure.

**Quick feature list**:
- Hosted Fields and Hosted Page integration
- Credit and debit cards
- DSP2 compliant
- More features very soon !

## Requirements

Minimum Shopware version is [6.4.0.0](https://www.shopware.com/en/changelog/)  
This plugin doesn't support Shopware version 5.

The plugin follows shopware requirements for PHP configuration:  
- Version **7.4.3** or higher
- memory_limit 512M or higher
- max_execution_time 30 seconds or higher

## Installation

**Zip/Upload**

The easiest method for testing or initial installation.  
Packages are available on every github release.

In order for shopware to install the necessary dependencies for the plugin to work properly, you must add the following shopware configuration:
```
# <shopware folder>/packages/feature.yml
shopware:
  feature:
    flags:
      - name: FEATURE_NEXT_1797
        default: true
```

Download the asset and follow the next step:
- Connect you to your Administration dashboard
- Go to _Extensions > My Extensions_.
- Click on _Upload extensions_
- Select the plugin and validate
- Install and Activate the plugin

**Composer**

This is the method we recommend because you really have control over the installation and updates.  
Go to your Shopware root directory of your project and use theses commands:

`composer require hipay/hipay-enterprise-shopware-6`

Afterward, you can easily activate this plugin via the console and start working with it:

```
bin/console plugin:refresh  
bin/console plugin:install --activate hipay-enterprise-shopware-6
 ```

## Support

HiPay frequently releases new versions of the modules. It is imperative to regularly update your platforms to be compatible with the versions of HiPay’s APIs, which are also evolving.
HiPay offers support services provided that your platforms run on maintained PHP versions and updated CMS versions with the latest security patches (see the list below).
We are obligated to follow each publisher’s minimum recommendations.

If you encounter an issue while using the modules, before contacting our Support team, we invite you to:

- analyze your platform’s PHP logs as well as the logs specific to the HiPay module,
- update the module to the most recent version,
- perform similar tests on your stage environments,
- analyze possible overloading in the code and interferences with third-party modules,
- perform tests on a blank environment without your developments or any third-party modules.

## Resources

- [Full project documentation][doc-home] — To have a comprehensive understanding of the workflow and get the installation procedure
- [HiPay Support Center][hipay-help] — To get technical help from HiPay
- [Issues][project-issues] — To report issues, submit pull requests and get involved (see [Apache 2.0 License][project-license])
- [Change log][project-changelog] — To check the changes of the latest versions
- [Contributing guidelines][project-contributing] — To contribute to our source code

## License

The **HiPay Enterprise plugin for Shopware 6** is free and available under the **Apache 2.0 License**. Check out the [license file][project-license] for more information.

[doc-home]: https://developer.hipay.com/doc/hipay-enterprise-shopware-6/
[hipay-help]: http://help.hipay.com
[project-issues]: https://github.com/hipay/hipay-enterprise-shopware-6/issues
[project-license]: LICENSE.md
[project-changelog]: CHANGELOG.md
[project-contributing]: CONTRIBUTING.md

