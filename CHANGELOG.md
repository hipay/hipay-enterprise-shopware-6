# CHANGELOG

## UNRELEASE
- **Fix** : fix currency error on install
- **Fix** : fix empty description error for non-product line items
- **Fix** : fix an issue related to card network filtering
- **Fix** : fix MyBank redirects cancellations to accept_url

## 3.2.0
- **Fix** : fix some unit tests/ phpstan
- **Fix** : fix shipping address for Paypal v2

## 3.1.0

- **Add** : Added support for Shopware version **6.7**
- **Add** : Added new HiPay logo
- **Fix** : Fixed country list for *Alma* payment methods
- **Fix** : Fixed duplicate labels in rule builder panel
- **Fix** : Fixed PayPal button when *Terms Of Conditions* are not accepted
- **Fix** : Fixed HiPay orders issue about IP address of customers behind proxies

## 3.0.3

- **Fix** : Fixed the partial refund issue when one or more items were selected.

## 3.0.2

- **Fix** : Fixed the missing save button in the order detail view when the order was not created using HiPay.
- **Fix** : Fixed missing status update for orders with multiple transactions

## 3.0.1

- **Fix** : Fixed store context in whole HiPay module
- **Fix** : Fixed code base according to code audit

## 3.0.0

- **BREAKING CHANGE** : Added support for Shopware version **6.6**

> :warning: This version is not compatible with Shopware version **6.5**

## 2.3.1

- **Fix** : Fixed the missing save button in the order detail view when the order was not created using HiPay.
- **Fix** : Fixed missing status update for orders with multiple transactions

## 2.3.0

- **Add** : Added **Klarna** payment method
- **Fix** : Fixed notification flow on transactions with **Sepa Direct Debit** payment method
- **Fix** : Fixed **Sepa Direct Debit** logo

## 2.2.0

- **Add** : Add PayPal v2
- **Fix** : Fixed getter method to recover Shopware version
- **Fix** : Fixed technical name for payment methods to prepare Shopware version **6.7**

## 2.1.0

- **Add** : Added new payment means
  - ApplePay
  - Alma 3x
  - Alma 4x

## 2.0.3

- **Fixed** : Fixed Shopware migration when reinstalling HiPay module

## 2.0.2

- **Fixed** : Fixed german translation

## 2.0.1

- **Fixed** :  Fixed MySQL migration script

## 2.0.0

- **BREAKING CHANGE** : Added support for Shopware version **6.5**

> :warning: This version is not compatible with Shopware version **6.4**

## 1.1.1

- **Fixed** :  Fixed PHP SDK version

## 1.1.0

- **Add** : Add cancel button option to hosted page
- **Add** : Update Giropay Logo
- **Fixed** :  Add `iDeal` bank choice when submitting checkout

## 1.0.3

- **Fix** : Fixed Database definition

## 1.0.2

- **Fix** : Fixed `Sofort` authorized country list

## 1.0.1

- **Fix** : Load config form sdk json directly

## 1.0.0

- **Add** : Added downloading technical logs feature
- **Add** : Added One clic payment for credit cards
- **Add** : Added order cancellation
- **Add** : Added Sofort payment method
- **Add** : Added Ideal payment method
- **Add** : Added MBway payment method
- **Add** : Added Multibanco payment method
- **Add** : Added Bancontact (BCMC via PPRO) payment method

## 0.0.1

- **Add** : Added HiPay notification system
- **Add** : Added refund and capture features
- **Add** : Added credit card payment implementation with hosted fields and hosted page
- **New** : Official version of HiPay Shopware plugin
