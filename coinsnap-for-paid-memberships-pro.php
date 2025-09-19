<?php
/*
 * Plugin Name:     Coinsnap for Paid Memberships Pro
 * Description:     With this Bitcoin payment plugin for Paid Memberships Pro you can now charge for your memberships in Bitcoin!
 * Version:         1.0.0
 * Author:          Coinsnap
 * Author URI:      https://coinsnap.io/
 * Text Domain:     coinsnap-for-paid-memberships-pro
 * Domain Path:     /languages
 * Requires PHP:    7.4
 * Tested up to:    6.8
 * Requires at least: 5.2
 * PMPro tested up to: 3.5.6
 * License:         GPL2
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Network:         true
 */ 

if (!defined( 'ABSPATH' )){ exit;}
if(!defined('COINSNAPPMPRO_PLUGIN_PHP_VERSION')){define( 'COINSNAPPMPRO_PLUGIN_PHP_VERSION', '7.4' );}
if(!defined('COINSNAPPMPRO_PLUGIN_VERSION')){define( 'COINSNAPPMPRO_PLUGIN_VERSION', '1.0.0' );}
if(!defined('COINSNAPPMPRO_REFERRAL_CODE')){define( 'COINSNAPPMPRO_REFERRAL_CODE', 'D12876' );}
if(!defined('COINSNAP_SERVER_URL')){define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}
if(!defined('COINSNAP_API_PATH')){define( 'COINSNAP_API_PATH', '/api/v1/');}
if(!defined('COINSNAP_SERVER_PATH')){define( 'COINSNAP_SERVER_PATH', 'stores' );}
if(!defined('COINSNAP_CURRENCIES')){define( 'COINSNAP_CURRENCIES', array("EUR","USD","SATS","BTC","CAD","JPY","GBP","CHF","RUB") );}

if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

require_once(dirname(__FILE__) . "/library/loader.php");

function coinsnappmpro_dependency_check(){
    if (!is_plugin_active('paid-memberships-pro/paid-memberships-pro.php')) {
        add_action('admin_notices', 'coinsnappmpro_dependency_notice');
        deactivate_plugins(plugin_basename(__FILE__));
    }
}
add_action('admin_init', 'coinsnappmpro_dependency_check');

function coinsnappmpro_dependency_notice(){?>
    <div class="notice notice-error">
        <p><?php echo esc_html_e('Coinsnap for Paid Memberships Pro plugin requires Paid Memberships Pro to be installed and activated.','coinsnap-for-paid-memberships-pro'); ?></p>
    </div>
    <?php
}

function coinsnappmpro_gateway_notice(){?>
    <div class="notice notice-error">
        <p><?php echo esc_html_e("Paid Memberships Pro plugin gateway isn't loaded",'coinsnap-for-paid-memberships-pro'); ?></p>
    </div>
    <?php
}

add_action('init', function() {
    
// Setting up and handling custom endpoint for api key redirect from BTCPay Server.
    add_rewrite_endpoint('coinsnap-for-paid-memberships-pro-btcpay-settings-callback', EP_ROOT);
});

// To be able to use the endpoint without appended url segments we need to do this.
add_filter('request', function($vars) {
    if (isset($vars['coinsnap-for-paid-memberships-pro-btcpay-settings-callback'])) {
        $vars['coinsnap-for-paid-memberships-pro-btcpay-settings-callback'] = true;
        $vars['coinsnap-for-pmpro-btcpay-nonce'] = wp_create_nonce('coinsnappmpro-btcpay-nonce');
    }
    return $vars;
});

    



add_action('init', array('PMProGateway_coinsnap', 'coinsnappmpro_processWebhook'));
add_action('plugins_loaded', 'coinsnappmpro_init');

function coinsnappmpro_init(){
    
    require_once WP_CONTENT_DIR . '/plugins/paid-memberships-pro/classes/gateways/class.pmprogateway.php';
    
    require_once(dirname(__FILE__) . "/coinsnap-for-pmpro-class.php");
    new PMProGateway_coinsnap();
    
    if (!class_exists('PMProGateway')) {
        add_action('admin_notices', 'coinsnappmpro_gateway_notice');
        return;
    }    
    
    add_action('init', array('PMProGateway_coinsnap', 'init'));
    add_filter('plugin_action_links', array('PMProGateway_coinsnap', 'plugin_action_links'), 10, 2 );    
}

                                                                           