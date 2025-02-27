=== Bitcoin payment for Paid Memberships Pro ===
Contributors: coinsnap
Tags:  Coinsnap, Paid Memberships Pro, Bitcoin, Lightning, Membership 
Tested up to: 6.7
Stable tag: 1.0.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

With this Bitcoin payment plugin for Paid Memberships Pro you can now charge for your memberships in Bitcoin!

== Description ==

[Coinsnap](https://coinsnap.io/en/) for Paid Memberships Pro allows you to process Bitcoin Lightning payments over the Lightning network. 
With the Coinsnap Bitcoin-Lightning payment plugin for Paid Memberships Pro you only need a Lightning wallet with a Lightning address to accept Bitcoin Lightning payments on your Wordpress site.

* Paid Memberships Pro Demo Donation Page: [https://paidmembershippro.coinsnap.org/](https://paidmembershippro.coinsnap.org/)
* Blog Article: [https://coinsnap.io/coinsnap-for-paid-memberships-pro-payment-plugin/](https://coinsnap.io/coinsnap-for-paid-memberships-pro-payment-plugin/)
* WordPress: [https://wordpress.org/plugins/coinsnap-for-paidmembershipspro/](https://wordpress.org/plugins/coinsnap-for-paidmembershipspro/)
* GitHub: [https://github.com/Coinsnap/Coinsnap-for-PaidMembershipsPro](https://github.com/Coinsnap/Coinsnap-for-PaidMembershipsPro)

Coinsnap’s payment plugin for Paid Memberships Pro makes it amazingly simple for your customers to purchase your offerings with Bitcoin-Lightning: They can make their transactions with just a scan of the QR code generated by the Coinsnap plugin, and the authorization of the payment. 
When authorized, the payment will be credited to your Lightning wallet in real time. 

== Bitcoin and Lightning payments in Paid Memberships Pro with Coinsnap ==

If you sell restricted content and manage membership subscriptions with recurring payments based on Paid Memberships Pro for WordPress, then you can easily integrate payment processing via Bitcoin and Lightning with the Coinsnap plugin.

With the Coinsnap Bitcoin Lightning payment processing plugin you can immediately accept Bitcoin Lightning payments on your site. You don’t need your own Lightning node or any other technical requirements - just install the Coinsnap for Paid Memberships Pro plugin.

Simply register on [Coinsnap](https://app.coinsnap.io/register), enter your own Lightning address and install the Coinsnap payment module in your wordpress backend. Add your store ID and your API key which you’ll find in your Coinsnap account, and your customers can pay you with Bitcoin Lightning right away!

= Features: =

* **All you need is a Lightning Wallet with a Lightning address. [Here you can find an overview of the matching Lightning Wallets](https://coinsnap.io/en/lightning-wallet-with-lightning-address/)**

* **Accept Bitcoin and Lightning payments** in your online store **without running your own technical infrastructure.** You do not need your own server, nor do you need to run your own Lightning Node.

* **Quick and easy registration at Coinsnap**: Just enter your email address and your Lightning address – and you are ready to integrate the payment module and start selling for Bitcoin Lightning. You will find the necessary IDs and Keys here, too.

* **100% protected privacy**:
    * We do not collect personal data.
    * For the registration you only need an e-mail address, which we will also use to inform you when we have received a payment.
    * No other personal information is required as long as you request a withdrawal to a Lightning address or Bitcoin address.

* **Only 1 % fees!**:
    * No basic fee, no transaction fee, only 1% on the invoice amount with referrer code.
    * Without referrer code the fee is 1.25%.
    * Get a referrer code from our partners and customers and save 0.25% fee.

* **No KYC needed**:
    * Direct, P2P payments (instantly to your Lightning wallet)
    * No intermediaries and paperwork
    * Transaction information is only shared between you and your customer

* **Sophisticated merchant’s admin dashboard in Coinsnap:**:
    * See all your transactions at a glance
    * Follow-up on individual payments
    * See issues with payments
    * Export reports

* **A Bitcoin payment via Lightning offers significant advantages**:
    * Lightning **payments are executed immediately.**
    * Lightning **payments are credited directly to the recipient.**
    * Lightning **payments are inexpensive.**
    * Lightning **payments are guaranteed.** No chargeback risk for the merchant.
    * Lightning **payments can be used worldwide.**
    * Lightning **payments are perfect for micropayments.**

* **Multilingual interface and support**: We speak your language

= Documentation: =

* [Coinsnap API (1.0) documentation](https://docs.coinsnap.io/)
* [Frequently Asked Questions](https://coinsnap.io/en/faq/) 
* [Terms and Conditions](https://coinsnap.io/en/general-terms-and-conditions/)
* [Privacy Policy](https://coinsnap.io/en/privacy/)


== Installation ==

### 1. Install the Coinsnap PaidMembershipsPro plug-in from the WordPress directory. ###

The Coinsnap PaidMembershipsPro plug-in can be searched and installed in the WordPress plugin directory.

In your WordPress instance, go to the Plugins > Add New section.
In the search you enter Coinsnap and get as a result the Coinsnap PaidMembershipsPro plug-in displayed.

Then click Install.

After successful installation, click Activate and then you can start setting up the plugin.

### 1.1. Add plugin ###

If you don’t want to install add-on directly via plugin, you can download Coinsnap PaidMembershipsPro plug-in from Coinsnap Github page or from WordPress directory and install it via “Upload Plugin” function:

Navigate to Plugins > Add Plugins > Upload Plugin and Select zip-archive downloaded from Github.

Click “Install now” and Coinsnap PaidMembershipsPro plug-in will be installed in WordPress.

After you have successfully installed the plugin, you can proceed with the connection to Coinsnap payment gateway.

### 1.2. Configure Coinsnap PaidMembershipsPro plug-in ###

After the Coinsnap PaidMembershipsPro plug-in is installed and activated, a notice appears that the plugin still needs to be configured.

### 1.3. Deposit Coinsnap data ###

* Navigate to Memberships > Settings > Payment Gateway and select coinsnap
* Enter Store ID and API Key
* Click Save Setting

If you don’t have a Coinsnap account yet, you can do so via the link shown: Coinsnap Registration

### 2. Create Coinsnap account ####

### 2.1. Create a Coinsnap Account ####

Now go to the Coinsnap website at: https://app.coinsnap.io/register and open an account by entering your email address and a password of your choice.

If you are using a Lightning Wallet with Lightning Login, then you can also open a Coinsnap account with it.

### 2.2. Confirm email address ####

You will receive an email to the given email address with a confirmation link, which you have to confirm. If you do not find the email, please check your spam folder.

Then please log in to the Coinsnap backend with the appropriate credentials.

### 2.3. Set up website at Coinsnap ###

After you sign up, you will be asked to provide two pieces of information.

In the Website Name field, enter the name of your online store that you want customers to see when they check out.

In the Lightning Address field, enter the Lightning address to which the Bitcoin and Lightning transactions should be forwarded.

A Lightning address is similar to an e-mail address. Lightning payments are forwarded to this Lightning address and paid out. If you don’t have a Lightning address yet, set up a Lightning wallet that will provide you with a Lightning address.

For more information on Lightning addresses and the corresponding Lightning wallet providers, click here:
https://coinsnap.io/lightning-wallet-mit-lightning-adresse/

### 3. Connect Coinsnap account with PaidMembershipsPro plug-in ###

### 3.1. PaidMembershipsPro Coinsnap Settings ###

* Navigate to Memberships > Settings > Payment Gateway and select coinsnap
* Enter Store ID and API Key
* Click Save Setting

### 4. Test payment ###

### 4.1. Test payment in PaidMembershipsPro ###

After all the settings have been made, a test payment should be made.

We make a real donation payment in our test PaidMembershipsPro site.

### 4.2. Bitcoin + Lightning payment page ###

The Bitcoin + Lightning payment page is now displayed, offering the payer the option to pay with Bitcoin or also with Lightning. Both methods are integrated in the displayed QR code.

== Upgrade Notice ==

Follow updates on plugin's GitHub page:
https://github.com/Coinsnap/Coinsnap-for-PaidMembershipsPro/

== Frequently Asked Questions ==

Plugin's page on Coinsnap website: https://coinsnap.io/en/

== Screenshots ==

1. Paid Memberships Pro Plugin
2. Plugin downloading from Github repository
3. Manual plugin installation
4. Plugins list 
5. Plugin settings
6. Coinsnap store settings
7. Currency and tax settings
8. Coinsnap register
9. E-mail address confirmation
10. Connect website with Coinsnap
11. QR code on the Bitcoin payment page
  
== Changelog ==
= 1.0 :: 2024-01-20 =
* Initial release. 
