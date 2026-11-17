<?php
// Removes only this plugin's own settings and secrets. PMPro's settings, orders and order meta stay.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function coinsnappmpro_uninstall_site() {
    $options = [
        'pmpro_coinsnap_webhook',
        'coinsnappmpro_records_since',
        'pmpro_coinsnap_provider',
        'pmpro_coinsnap_store_id',
        'pmpro_coinsnap_api_key',
        'pmpro_coinsnap_button_text',
        'pmpro_btcpay_server_url',
        'pmpro_btcpay_store_id',
        'pmpro_btcpay_api_key',
        'pmpro_btcpay_button_text',
        'pmpro_coinsnap_autoredirect',
        'pmpro_coinsnap_returnurl',
        'pmpro_coinsnap_expired_status',
        'pmpro_coinsnap_settled_status',
        'pmpro_coinsnap_processing_status',
    ];
    foreach ($options as $option) {
        delete_option($option);
    }
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
        switch_to_blog((int) $siteId);
        coinsnappmpro_uninstall_site();
        restore_current_blog();
    }
} else {
    coinsnappmpro_uninstall_site();
}
