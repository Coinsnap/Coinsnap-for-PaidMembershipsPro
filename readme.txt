=== Bitcoin payment for Paid Memberships Pro ===
Contributors: coinsnap
Tags: bitcoin, lightning, paid memberships pro, membership, btcpay
Requires at least: 5.2
Tested up to: 7.0.4
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin and Lightning for Paid Memberships Pro memberships, subscriptions, recurring plans, day passes and restricted content.

== Description ==

= Add Bitcoin and Lightning payments to Paid Memberships Pro =

Coinsnap for Paid Memberships Pro adds Bitcoin as a payment option to the existing Paid Memberships Pro checkout and membership workflow.

Members can pay with **Bitcoin via Lightning or on-chain** for membership levels, subscriptions, recurring plans, time-based access, supporter memberships, consulting access and restricted content.

Coinsnap can be offered alongside existing gateways such as Stripe or PayPal, or used as a standalone Bitcoin payment option.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

= Key features =

* Accept **Bitcoin and Lightning payments** in Paid Memberships Pro
* Support for **Lightning and Bitcoin on-chain**
* Works with PMPro membership levels and checkout
* Suitable for one-time memberships, subscriptions, recurring plans and time-based access
* Support for day passes, monthly access and custom membership durations
* Use Bitcoin alongside Stripe, PayPal and other gateways
* Support for **Coinsnap** and **BTCPay Server**
* BTCPay Server setup wizard
* Customizable Bitcoin payment-button text
* Redirect and custom return URL after payment
* Payment-status mapping for Expired, Processing and Settled
* Optional **Bitcoin discount**
* Membership activation after successful payment
* No own Lightning node required when using Coinsnap
* Non-custodial / self-custody payment model
* Optional bank settlement through supported Coinsnap partners
* Open source and free to install

= Typical use cases =

Use Coinsnap for Paid Memberships Pro for:

* monthly memberships
* annual memberships
* day passes
* short-term access
* restricted articles and premium content
* member-only downloads
* online courses
* community access
* supporter memberships
* paid consulting or one-on-one sessions
* low-priced memberships and micro-subscriptions
* global membership programs

= How it works =

1. Install and activate Paid Memberships Pro.
2. Install and activate Coinsnap for Paid Memberships Pro.
3. Go to **Memberships → Settings → Payments**.
4. Select Coinsnap as a payment gateway.
5. Open the Coinsnap gateway settings.
6. Choose Coinsnap or BTCPay Server.
7. Enter the required connection details.
8. Configure payment button, redirects and payment-status mapping.
9. Create or configure your membership levels.
10. Make a test Bitcoin payment.

= Coinsnap or BTCPay Server =

The add-on supports:

* **Coinsnap** — accept Bitcoin without operating your own Bitcoin or Lightning payment infrastructure.
* **BTCPay Server** — connect your own self-hosted BTCPay Server.

= Memberships and recurring plans =

Bitcoin can be used with membership levels, subscriptions and recurring plans configured in Paid Memberships Pro.

A successful Bitcoin payment can activate the configured membership period.

Bitcoin payments are not automatically renewed like a stored-card subscription. A member may need to complete the next Bitcoin payment manually unless the selected Lightning setup provides compatible subscription functionality.

= Bitcoin discount =

The current Coinsnap Paid Memberships Pro product supports an optional **Bitcoin discount**.

A fixed or percentage-based discount can be used to encourage members to choose Bitcoin.

= Payment status mapping =

Coinsnap tracks three payment states:

* **Expired**
* **Processing**
* **Settled**

These can be mapped to the corresponding Paid Memberships Pro order/payment statuses.

= Checkout and redirects =

The Bitcoin payment option can be customized in PMPro checkout.

You can change the payment-button text, enable automatic redirect after successful payment and configure a custom return or thank-you URL.

= Settlement =

With Coinsnap, payments can be settled to a compatible Bitcoin or Lightning wallet.

One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

Depending on the merchant setup and supported partner integrations, Bitcoin payments can also be automatically converted and settled to a bank account.

= Fees =

Coinsnap charges a **1% transaction fee**. The fee is handled separately through the merchant's Coinsnap balance and is not deducted from the Bitcoin payment.

= More information =

* Product page: https://coinsnap.io/wp-add-ons/paid-memberships-pro/
* User Guide: https://coinsnap.io/user-guides/paid-memberships-pro-bitcoin-user-guide/
* WordPress Plugin Directory: https://wordpress.org/plugins/coinsnap-for-paid-memberships-pro/
* Demo: https://paidmembershippro.coinsnap.org/
* GitHub: https://github.com/Coinsnap/Coinsnap-for-PaidMembershipsPro
* Coinsnap Wallet: https://coinsnap.io/wallet/
* Coinsnap API: https://docs.coinsnap.io/
* Coinsnap account: https://app.coinsnap.io/register

== Installation ==

1. Install and activate **Paid Memberships Pro**.
2. In WordPress, go to **Plugins → Add New**.
3. Search for **Bitcoin payment for Paid Memberships Pro**.
4. Install and activate the Coinsnap add-on.
5. Go to **Memberships → Settings → Payments**.
6. Select Coinsnap and open the Coinsnap settings.
7. Connect Coinsnap or BTCPay Server.
8. Configure payment button, redirects and status mapping.
9. Save the settings.
10. Make a test membership purchase with Bitcoin.

== Frequently Asked Questions ==

= Can Paid Memberships Pro accept Bitcoin payments? =

Yes. Coinsnap adds Bitcoin and Lightning payments directly to Paid Memberships Pro checkout.

= Can members pay with both Lightning and Bitcoin on-chain? =

Yes. The integration supports both Lightning and Bitcoin on-chain.

= Can I use Bitcoin for recurring membership plans? =

Yes. Bitcoin can be used with recurring membership plans defined in Paid Memberships Pro. Bitcoin payments are not automatically renewed like stored-card subscriptions, so a new Bitcoin payment may be required for the next membership period.

= Can I use Bitcoin for one-time or short-term access? =

Yes. Examples include one-time memberships, monthly access, day passes and other custom membership durations.

= Can Bitcoin be offered alongside Stripe or PayPal? =

Yes. Coinsnap can be offered alongside other PMPro gateways or used as a standalone Bitcoin option.

= Can I use my own BTCPay Server? =

Yes. BTCPay Server is supported as an alternative to Coinsnap.

= Does the plugin support a Bitcoin discount? =

Yes. The current Coinsnap product documentation includes an optional fixed or percentage-based Bitcoin discount.

= Can I customize the payment button and return page? =

Yes. The plugin supports customizable checkout text and redirect/return URL settings.

= When is a membership activated? =

The membership is activated after the Bitcoin payment is successfully settled.

= Do I need my own Lightning node? =

No. When using Coinsnap, no own Lightning node or Bitcoin payment server is required.

= Does Coinsnap hold my Bitcoin? =

No. Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

= Can payments be settled to a bank account? =

Depending on the merchant setup and supported partner integrations, Bitcoin payments can be converted and settled to a bank account.

= What does Coinsnap cost? =

Coinsnap charges a 1% transaction fee.

== Screenshots ==

1. Install Bitcoin payment for Paid Memberships Pro from the WordPress Plugin Directory
2. Paid Memberships Pro payment settings
3. Select Coinsnap as payment gateway
4. Choose Coinsnap or BTCPay Server
5. BTCPay Server setup wizard
6. Customize Bitcoin checkout button and redirect settings
7. Configure payment-status mapping
8. Paid Memberships Pro membership checkout
9. Bitcoin and Lightning payment QR code
10. Successful payment and membership activation

== Changelog ==

= 1.0.6 :: 2026-05-24 =
* Compatibility with Paid Memberships Pro 3.7.3 is tested.
* Compatibility with WordPress 7.0 is tested.

= 1.0.5 :: 2026-04-07 =
* Updated Coinsnap Invoice client.
* Updated Coinsnap Store client.
* Compatibility with Paid Memberships Pro 3.7.1 is tested.
* Compatibility with WordPress 6.9.4 is tested.

= 1.0.4 :: 2026-03-07 =
* Compatibility with Paid Memberships Pro 3.6.4 - 3.6.5 is tested.
* Updated plugin description.

= 1.0.3 :: 2025-12-28 =
* Compatibility with Paid Memberships Pro 3.6.3 is tested.
* Updated PHP files protection.

= 1.0.2 :: 2025-12-04 =
* Compatibility with Paid Memberships Pro 3.6.2 is tested.
* Compatibility with WordPress 6.9 is tested.

= 1.0.1 :: 2025-10-13 =
* Added handling for currencies not supported by BTCPay Server.
* Added custom redirect after payment.

= 1.0.0 :: 2025-10-09 =
* Initial release in WordPress Plugin Directory.
