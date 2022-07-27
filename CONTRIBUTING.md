# Contributing to the HiPay Enterprise module for Shopware 6

Contributions to the HiPay Enterprise module for Shopware 6 should be made via GitHub [pull
requests][pull-requests] and discussed using
GitHub [issues][issues].

## Before you start

If you would like to make a significant change, please open
an issue to discuss it, in order to minimize duplication of effort.

## Development

Installation with Docker for testing

If you are a developer or a QA developer, you can use this project with Docker and Docker Compose.
Requirements for your environment:

- Git (<https://git-scm.com/>)
- Docker (<https://docs.docker.com/engine/installation/>)
- Docker Compose (<https://docs.docker.com/compose/>)

Here is the procedure to be applied to a Linux environment:

Open a terminal and select the folder of your choice.

Clone the HiPay Enterprise PrestaShop project in your environment with Git:

```bash
git clone https://github.com/hipay/hipay-enterprise-shopware-6.git
```

Go in the project root folder and enter this command:

```bash
./shopware.sh init
```

Your container is loading: wait for a few seconds while Docker installs Shopware and the HiPay module.

You can now test the HiPay Enterprise module in a browser with this URL: <http://localhost:8076>

To connect to the back office, go to this URL: <http://localhost:8076/admin>

The login and password are admin / shopware.
You can test the module with your account configuration.

### Making the request

Development takes place against the `develop` branch of this repository and pull
requests should be opened against that branch.

## Licensing

The HiPay Enterprise module for Shopware 6 is released under an [Apache
2.0][project-license] license. Any code you submit will be
released under that license.

[project-license]: LICENSE.md
[pull-requests]: https://github.com/hipay/hipay-enterprise-shopware-6/pulls
[issues]: https://github.com/hipay/hipay-enterprise-shopware-6/issues
