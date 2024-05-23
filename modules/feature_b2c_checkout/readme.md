# Feature B2C Checkout

## Description
The **Feature B2C Checkout** module injects analytics event JavaScript on the billing page of the checkout process in a Drupal 7 site. It tracks specific form submissions and sends the data to Google Tag Manager.

## Requirements
- Drupal 7.x

## Installation

1. Place the `feature_b2c_checkout` module directory in your `sites/all/modules/custom/` directory:
    ```
    sites/all/modules/custom/feature_b2c_checkout/
    ```

2. Enable the module via the Drupal admin interface or Drush:
    ```bash
    drush en feature_b2c_checkout -y
    ```

## Usage

This module automatically injects JavaScript into the page when the user is on the billing page of the checkout process (`b2c/checkout/billing`).
