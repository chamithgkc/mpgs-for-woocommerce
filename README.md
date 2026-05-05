# WooCommerce MPGS

## Description
This plugin implements a Hosted Checkout integration for MasterCard Payment Gateway Services (MPGS) in WooCommerce. It provides a seamless and secure way to accept credit and debit card payments directly on your WooCommerce store.

## Plugin Features
* **Seamless WooCommerce Integration:** Native payment gateway integration on the checkout page.
* **Two Checkout Modes:** Choose between a redirect to a hosted payment page or an on-site lightbox popup.
* **Dynamic API Versioning:** Configure the MPGS API version provided by your bank.
* **Customizable Branding:** Add your custom merchant name, address, and payment method icon.
* **High Security:** Does not store sensitive credit card data on your server. All transactions are securely processed via MPGS.
* **Modern Compatibility:** Fully compatible with WordPress 6.8 and WooCommerce 9.8.5, including High-Performance Order Storage (HPOS) support.

## Checkout Modes
The plugin supports two checkout interaction modes based on your bank's API version:
1. **Payment Page (Redirect):** The customer is redirected to a secure payment page hosted by MasterCard to complete their transaction.
2. **Lightbox (Popup):** The customer completes the payment through a secure popup overlaid on your checkout page without leaving your website. *(Note: The Lightbox option is only supported for MPGS API versions less than 63. If your configured API version is 63 or higher, the plugin will automatically fallback to the Payment Page mode.)*

## Requirements
* **WordPress:** 5.6 or higher (Tested up to 6.8)
* **WooCommerce:** 5.0.0 or higher (Tested up to 9.8.5)
* **PHP:** 7.4 or higher
* An active merchant account with a bank that supports MasterCard Payment Gateway Services (MPGS).

## Configuration
Once installed and activated, navigate to **WooCommerce > Settings > Payments > MPGS** to configure the gateway. You will need the following details from your bank:
* **MPGS URL** (e.g., `https://ap-gateway.mastercard.com/`)
* **API Version**
* **Merchant ID**
* **Authentication Password**

## Credits
* **Original Author:** Originally created by Ali Basheer (v1.0.0 - v1.5.1).
* **Current Maintainer:** Updated, secured, and maintained by Chamith Koralage.

## Support
Support can take place through the following channels:
* [GitHub issues](https://github.com/chamithgkc/Woocommerce-MPGS)
