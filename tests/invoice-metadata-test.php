<?php
// Runs inside WordPress with Paid Memberships Pro and this plugin active: wp eval-file tests/invoice-metadata-test.php
// Every HTTP call is answered by a fake (no API key needed). Checks the create-invoice request for Coinsnap
// and BTCPay: orderId = PMPro order ID (the webhook loads the order by it) plus the order metadata.
if (!defined('ABSPATH')) { exit; }

$failures = 0;
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$ok) { $failures++; }
};

$bodies = [];
$fake = function ($pre, $args, $url) use (&$bodies) {
    $reply = function ($body) {
        return ['headers' => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary(['content-type' => 'application/json']),
            'body' => wp_json_encode($body), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    };
    if (strpos($url, 'coingecko') !== false) {
        return $reply(['rates' => ['btc' => ['value' => 1], 'eur' => ['value' => 90000], 'usd' => ['value' => 100000]]]);
    }
    if (strpos($url, '/payment-methods') !== false) {
        return $reply([['paymentMethodId' => 'BTC-LN', 'enabled' => true]]);
    }
    if (strpos($url, '/rates') !== false) {
        return $reply([['currencyPair' => 'BTC_EUR', 'rate' => '90000', 'errors' => []], ['currencyPair' => 'BTC_USD', 'rate' => '100000', 'errors' => []]]);
    }
    if (($args['method'] ?? '') === 'POST' && preg_match('~/invoices$~', $url)) {
        $bodies[] = json_decode((string) $args['body'], true);
        return $reply(['id' => 'inv' . count($bodies), 'checkoutLink' => 'https://pay.example/i/x']);
    }
    return $reply([]);
};
$stop = function ($location) { throw new \RuntimeException('redirect ' . $location); };
add_filter('pre_http_request', $fake, 10, 3);
add_filter('wp_redirect', $stop, 1);

$optionKeys = ['coinsnap_provider', 'coinsnap_api_key', 'coinsnap_store_id', 'btcpay_server_url', 'btcpay_api_key', 'btcpay_store_id'];
$savedOptions = [];
foreach ($optionKeys as $k) { $savedOptions[$k] = get_option('pmpro_' . $k, null); }

global $wpdb, $pmpro_currency;
$pmpro_currency = 'EUR';
$wpdb->insert($wpdb->pmpro_membership_levels, ['name' => 'Gold', 'description' => '', 'initial_payment' => 12, 'billing_amount' => 12,
    'cycle_number' => 1, 'cycle_period' => 'Month', 'billing_limit' => 0, 'trial_amount' => 0, 'trial_limit' => 0, 'allow_signups' => 1]);
$levelId = (int) $wpdb->insert_id;
$userId = wp_insert_user(['user_login' => 'csmeta_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'user_email' => 'ana' . wp_rand() . '@example.com',
    'first_name' => 'Ana', 'last_name' => 'Buyer']);
wp_set_current_user($userId);
$email = get_userdata($userId)->user_email;

$order = new MemberOrder();
$order->user_id = $userId;
$order->membership_id = $levelId;
$order->gateway = 'coinsnap';
$order->subtotal = '12';
$order->tax = '0';
$order->total = '12';
$order->status = 'token';
$order->billing = new stdClass();
$order->billing->name = 'Ana Buyer';
$order->billing->street = 'Street 1';
$order->billing->street2 = '';
$order->billing->city = 'Belgrade';
$order->billing->state = '';
$order->billing->zip = '11000';
$order->billing->country = 'RS';
$order->billing->phone = '+381111';
$order->saveOrder();

try {
    foreach (['coinsnap', 'btcpay'] as $provider) {
        foreach (['coinsnap_provider' => $provider, 'coinsnap_api_key' => 'fake', 'coinsnap_store_id' => 'fake-store',
            'btcpay_server_url' => 'https://btcpay.example', 'btcpay_api_key' => 'fake', 'btcpay_store_id' => 'fake-store'] as $k => $v) {
            update_option('pmpro_' . $k, $v);
        }
        $morder = new MemberOrder($order->id);
        $before = count($bodies);
        try {
            (new PMProGateway_coinsnap())->coinsnappmpro_getInvoice($morder);
        } catch (\RuntimeException $e) {
        }
        $body = count($bodies) > $before ? end($bodies) : [];
        $m = $body['metadata'] ?? [];
        $check($body !== [], "$provider: invoice request sent");
        $check((string) ($body['orderId'] ?? '') === (string) $order->id, "$provider: top-level orderId = PMPro order ID");
        $check((string) ($m['orderId'] ?? '') === (string) $order->id, "$provider: metadata.orderId = order ID (webhook match)");
        $check(($m['orderNumber'] ?? '') === (string) $morder->code && $morder->code !== '', "$provider: orderNumber = order code");
        $check(strpos((string) ($m['orderUrl'] ?? ''), 'page=pmpro-orders&order=' . $order->id) !== false, "$provider: orderUrl");
        $check(($m['itemDesc'] ?? '') === 'Membership: Gold' && ($m['itemCode'] ?? '') === (string) $levelId, "$provider: itemDesc, itemCode");
        $check(($m['planName'] ?? '') === 'Gold' && ($m['membershipLevelId'] ?? 0) === $levelId && ($m['recurring'] ?? false) === true
            && ($m['billingPeriod'] ?? '') === '1 Month', "$provider: plan, recurring");
        $li = $m['lineItems'][0] ?? [];
        $check(($li['name'] ?? '') === 'Gold' && ($li['quantity'] ?? 0) === 1 && (float) ($li['total'] ?? 0) === 12.0, "$provider: lineItems");
        $check(($m['platform'] ?? '') === 'paidmembershipspro' && ($m['pluginVersion'] ?? '') === COINSNAPPMPRO_PLUGIN_VERSION, "$provider: platform, pluginVersion");
        $check(($body['buyerEmail'] ?? '') === $email && ($m['buyerEmail'] ?? '') === $email, "$provider: buyerEmail");
        $check(($m['buyerName'] ?? '') === 'Ana Buyer' && ($m['customerName'] ?? '') === 'Ana Buyer' && ($m['buyerPhone'] ?? '') === '+381111', "$provider: name, phone");
        $check(($m['buyerAddress1'] ?? '') === 'Street 1' && ($m['buyerCity'] ?? '') === 'Belgrade' && ($m['buyerZip'] ?? '') === '11000' && ($m['buyerCountry'] ?? '') === 'RS', "$provider: address");
        $check(!in_array('', $m, true) && !in_array(null, $m, true), "$provider: no empty values");
    }
} finally {
    remove_filter('pre_http_request', $fake, 10);
    remove_filter('wp_redirect', $stop, 1);
    foreach ($savedOptions as $k => $v) {
        if ($v === null) { delete_option('pmpro_' . $k); } else { update_option('pmpro_' . $k, $v); }
    }
    $wpdb->delete($wpdb->pmpro_membership_orders, ['id' => $order->id]);
    $wpdb->delete($wpdb->pmpro_membership_levels, ['id' => $levelId]);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($userId);
}

echo $failures === 0 ? "OK\n" : "$failures FAILED\n";
