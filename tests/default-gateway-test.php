<?php
// Run: php tests/default-gateway-test.php
// Checks that the plugin leaves other gateways alone: an order never changes the site's default
// gateway, and the checkout page is changed only when Coinsnap is the gateway in use.
if (PHP_SAPI !== 'cli') {
    exit;
}
define('ABSPATH', '/');
$GLOBALS['opts'] = ['pmpro_gateway' => 'stripe'];
$GLOBALS['hooks'] = [];
function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['hooks'][$h][] = $cb; }
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['hooks'][$h][] = $cb; }
function do_action($h, ...$args) { foreach ($GLOBALS['hooks'][$h] ?? [] as $cb) { call_user_func_array($cb, $args); } }
function is_admin() { return true; }
function get_option($k, $d = false) { return $GLOBALS['opts'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
function pmpro_setOption($k, $v) { $GLOBALS['opts']['pmpro_' . $k] = $v; }
function pmpro_getOption($k) { return $GLOBALS['opts']['pmpro_' . $k] ?? ''; }
function pmpro_getGateway() { return $GLOBALS['opts']['pmpro_gateway'] ?? ''; }
function hooked($h) { return !empty($GLOBALS['hooks'][$h]); }
class PMProGateway {}

require dirname(__DIR__) . '/coinsnap-for-pmpro-class.php';
PMProGateway_coinsnap::init();

$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS ' : 'FAIL ') . $m . "\n"; if (!$c) { $fail++; } }

$stripeOrder = (object) ['gateway' => 'stripe', 'id' => 1];
do_action('pmpro_add_order', $stripeOrder);
ok(get_option('pmpro_gateway') === 'stripe', 'a Stripe order keeps the default gateway stripe');

$coinsnapOrder = (object) ['gateway' => 'coinsnap', 'id' => 2];
do_action('pmpro_add_order', $coinsnapOrder);
ok(get_option('pmpro_gateway') === 'stripe', 'a Coinsnap order does not change the default gateway either');

// Checkout page of another gateway: card fields and its submit button stay.
ok(!hooked('pmpro_include_payment_information_fields'), 'Stripe checkout keeps its payment fields');
ok(!hooked('pmpro_include_billing_address_fields'), 'Stripe checkout keeps its billing address');
ok(!hooked('pmpro_required_billing_fields'), 'Stripe checkout keeps its required billing fields');
ok(!hooked('pmpro_checkout_default_submit_button'), 'Stripe checkout keeps its own submit button');

// Coinsnap is the gateway in use: no card fields, Coinsnap button.
$GLOBALS['hooks'] = [];
$GLOBALS['opts']['pmpro_gateway'] = 'coinsnap';
PMProGateway_coinsnap::init();
ok(hooked('pmpro_include_payment_information_fields') && hooked('pmpro_checkout_default_submit_button'), 'Coinsnap checkout hides card fields and shows the Coinsnap button');
ok(hooked('pmpro_checkout_before_change_membership_level'), 'Coinsnap checkout still creates the invoice');

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
