# Coinsnap for Paid Memberships Pro

![Coinsnap for Paid Memberships Pro](https://resources.coinsnap.org/products/paidmembershipspro/images/1.png)

**Bitcoin payment add-on for Paid Memberships Pro — accept Bitcoin and Lightning for memberships, subscriptions, recurring plans, day passes, restricted content and other member-based business models.**

Coinsnap for Paid Memberships Pro adds Bitcoin as a payment option to the existing Paid Memberships Pro checkout and membership workflow.

Members can pay with **Bitcoin via Lightning or on-chain** for membership levels, time-based access, subscriptions, recurring plans, supporter memberships, consulting access and gated content.

Coinsnap can be used alongside existing payment methods such as Stripe or PayPal, or as a standalone Bitcoin payment option.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

## Key features

- Accept **Bitcoin and Lightning payments** in Paid Memberships Pro
- Support for **Lightning and Bitcoin on-chain**
- Works with Paid Memberships Pro membership levels and checkout
- Suitable for one-time memberships, recurring plans, subscriptions and time-based access
- Support for day passes, monthly access and other custom membership durations
- Use Bitcoin alongside Stripe, PayPal and other gateways
- Support for **Coinsnap** and **BTCPay Server**
- BTCPay Server setup wizard
- Customizable Bitcoin payment button text
- Configurable redirect and return URL after payment
- Payment-status mapping for Expired, Processing and Settled
- Optional **Bitcoin discount** as a fixed amount or percentage
- PMPro order and membership activation after successful payment
- No own Lightning node required when using Coinsnap
- **Non-custodial / self-custody** payment model
- Optional settlement to a bank account through supported Coinsnap partners
- Open source and free to install

## Current public release

The current WordPress.org release is **1.0.6**.

WordPress.org currently lists:

- WordPress **5.2 or newer**
- PHP **7.4 or newer**
- Tested up to **WordPress 7.0.4**
- Paid Memberships Pro compatibility tested up to **3.7.3**

> **Developer note:** the current GitHub `main` branch still identifies the plugin as version **1.0.5**, tested with WordPress 6.9 and Paid Memberships Pro 3.7.1. The repository code metadata and public WordPress.org release should be synchronized separately.

## Typical use cases

Coinsnap for Paid Memberships Pro can be used for:

- monthly memberships
- annual memberships
- day passes
- short-term or time-based access
- restricted articles and premium content
- member-only downloads
- online courses
- community access
- supporter memberships
- paid consulting or one-on-one sessions
- low-priced memberships and micro-subscriptions
- global membership programs

## How it works

1. Install and activate **Paid Memberships Pro**.
2. Install and activate **Coinsnap for Paid Memberships Pro**.
3. Go to **Memberships → Settings → Payments**.
4. Select **Coinsnap** as a payment gateway.
5. Open the Coinsnap gateway settings.
6. Choose **Coinsnap** or your own **BTCPay Server**.
7. Enter the required Store ID and API credentials.
8. Configure the payment button, redirect behavior and payment-status mapping.
9. Create or configure your PMPro membership levels.
10. Make a test Bitcoin payment before going live.

## Coinsnap or BTCPay Server

The add-on supports two payment-provider modes:

- **Coinsnap** — for site owners who want to accept Bitcoin without operating their own Bitcoin or Lightning payment infrastructure.
- **BTCPay Server** — for users who want to connect their own self-hosted BTCPay Server.

## Memberships and recurring plans

The Coinsnap product supports Bitcoin payments for membership levels, subscriptions and recurring plans defined in Paid Memberships Pro.

A Bitcoin payment can activate the corresponding membership term, such as one month, one year or another configured duration.

**Important:** Bitcoin payments are not automatically renewed in the same way as a card-on-file subscription. Where a recurring membership requires a new Bitcoin payment, the member must complete the next payment manually unless the selected Lightning setup provides compatible subscription functionality.

## Bitcoin discount

The current Coinsnap Paid Memberships Pro product page supports an optional **Bitcoin discount**.

A fixed or percentage-based discount can be used to encourage members to choose Bitcoin when multiple payment methods are available.

## Payment status mapping

Coinsnap tracks the payment states:

- **Expired**
- **Processing**
- **Settled**

These can be mapped to the corresponding Paid Memberships Pro order/payment statuses.

For most installations, the default mapping is recommended.

## Checkout and redirects

The Bitcoin payment option can be customized in the Paid Memberships Pro checkout.

You can change the payment-button text and configure whether the member is redirected automatically after successful payment. A custom return or thank-you URL can also be used.

## Settlement

With Coinsnap, payments can be settled to a compatible Bitcoin or Lightning wallet.

One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Coinsnap follows a **non-custodial / self-custody** approach and does not hold merchant Bitcoin.

Depending on the merchant setup and supported partner integrations, Bitcoin payments can also be automatically converted and settled to a bank account.

## Fees

Coinsnap charges a **1% transaction fee**. The fee is handled separately through the merchant's Coinsnap balance and is not deducted from the Bitcoin payment itself.

## Installation

### Install from WordPress.org

1. Install and activate **Paid Memberships Pro**.
2. In WordPress, go to **Plugins → Add New**.
3. Search for **Bitcoin payment for Paid Memberships Pro**.
4. Install and activate the Coinsnap add-on.
5. Open **Memberships → Settings → Payments**.
6. Select Coinsnap and open its settings.
7. Connect Coinsnap or BTCPay Server.
8. Configure redirects and status mapping.
9. Save the settings.
10. Make a test membership purchase with Bitcoin.

### Install from GitHub

1. Download this repository as a ZIP archive.
2. Upload it through **Plugins → Add New → Upload Plugin**.
3. Install and activate the add-on.
4. Make sure Paid Memberships Pro is installed and active.
5. Configure Coinsnap in the PMPro payment settings.

## Resources

- [Coinsnap for Paid Memberships Pro product page](https://coinsnap.io/wp-add-ons/paid-memberships-pro/)
- [Paid Memberships Pro Bitcoin User Guide](https://coinsnap.io/user-guides/paid-memberships-pro-bitcoin-user-guide/)
- [WordPress Plugin Directory](https://wordpress.org/plugins/coinsnap-for-paid-memberships-pro/)
- [Paid Memberships Pro demo](https://paidmembershippro.coinsnap.org/)
- [Coinsnap Wallet](https://coinsnap.io/wallet/)
- [Coinsnap API documentation](https://docs.coinsnap.io/)
- [Coinsnap merchant account](https://app.coinsnap.io/register)
- [Telegram community and support](https://t.me/coinsnap)

> **Brand note:** Coinsnap is Bitcoin payment software for merchants and is not the provider of the similarly named coin identifier app "CoinSnap".

## Frequently Asked Questions

### Can Paid Memberships Pro accept Bitcoin payments?

Yes. Coinsnap adds Bitcoin and Lightning payments to the Paid Memberships Pro checkout.

### Can members pay with both Lightning and Bitcoin on-chain?

Yes. The integration supports both Lightning and Bitcoin on-chain.

### Can I use Bitcoin for recurring membership plans?

Yes. Bitcoin can be used with recurring membership plans defined in Paid Memberships Pro. However, Bitcoin payments are not automatically renewed like a stored-card subscription; a new payment may need to be completed manually for the next membership period.

### Can I use Bitcoin for one-time or short-term access?

Yes. Examples include one-time memberships, monthly access, day passes and other custom time-based membership periods.

### Can Bitcoin be offered alongside Stripe or PayPal?

Yes. Coinsnap can be used alongside other Paid Memberships Pro payment gateways or as a standalone Bitcoin option.

### Can I use my own BTCPay Server?

Yes. BTCPay Server is supported as an alternative to Coinsnap.

### Does the plugin support a Bitcoin discount?

Yes. The current Coinsnap product page documents an optional fixed or percentage-based Bitcoin discount.

### Can I customize the payment button and return page?

Yes. The current plugin settings include customizable checkout text and redirect/return URL options.

### When is a membership activated?

The membership is activated after the Bitcoin payment is successfully settled.

### Do I need my own Lightning node?

No. When using Coinsnap, no own Lightning node or Bitcoin payment server is required.

### Does Coinsnap hold my Bitcoin?

No. Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

### Can payments be settled to a bank account?

Depending on the merchant setup and supported partner integrations, Bitcoin payments can be converted and settled to a bank account.

### What does Coinsnap cost?

Coinsnap charges a 1% transaction fee.

## Changelog

### 1.0.6 — 2026-05-24
- Compatibility with Paid Memberships Pro 3.7.3 tested.
- Compatibility with WordPress 7.0 tested.

### 1.0.5 — 2026-04-07
- Updated the Coinsnap Invoice client.
- Updated the Coinsnap Store client.
- Compatibility with Paid Memberships Pro 3.7.1 tested.
- Compatibility with WordPress 6.9.4 tested.

### 1.0.4 — 2026-03-07
- Compatibility with Paid Memberships Pro 3.6.4–3.6.5 tested.
- Updated plugin description.

### 1.0.3 — 2025-12-28
- Compatibility with Paid Memberships Pro 3.6.3 tested.
- Updated PHP file protection.

### 1.0.2 — 2025-12-04
- Compatibility with Paid Memberships Pro 3.6.2 tested.
- Compatibility with WordPress 6.9 tested.

### 1.0.1 — 2025-10-13
- Added handling for currencies not supported by BTCPay Server.
- Added custom redirect after payment.

### 1.0.0 — 2025-10-09
- Initial release in the WordPress Plugin Directory.
