<?php
/*
 * Plugin Name:     Bitcoin payment for Paid Memberships Pro
 * Description:     With this Bitcoin payment plugin for Paid Memberships Pro you can now charge for your memberships in Bitcoin!
 * Version:         1.0.0
 * Author:          Coinsnap
 * Author URI:      https://coinsnap.io/
 * Text Domain:     coinsnap-for-paid-memberships-pro
 * Domain Path:     /languages
 * Requires PHP:    7.4
 * Tested up to:    6.8
 * Requires at least: 5.2
 * Requires Plugins: paid-memberships-pro
 * PMPro tested up to: 3.4.6
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

function check_pmpro_dependency(){
    if (!is_plugin_active('paid-memberships-pro/paid-memberships-pro.php')) {
        add_action('admin_notices', 'pmpro_dependency_notice');
        deactivate_plugins(plugin_basename(__FILE__));
    }
}
add_action('admin_init', 'check_pmpro_dependency');

function pmpro_dependency_notice(){?>
    <div class="notice notice-error">
        <p><?php echo esc_html_e('Coinsnap for Paid Memberships Pro plugin requires Paid Memberships Pro to be installed and activated.','coinsnap-for-paid-memberships-pro'); ?></p>
    </div>
    <?php
}

function pmpro_gateway_notice(){?>
    <div class="notice notice-error">
        <p><?php echo esc_html_e("Paid Memberships Pro plugin gateway isn't loaded",'coinsnap-for-paid-memberships-pro'); ?></p>
    </div>
    <?php
}

add_action('init', function() {
    
//  Session launcher
    if ( ! session_id() ) {
        session_start();
    }
    
// Setting up and handling custom endpoint for api key redirect from BTCPay Server.
    add_rewrite_endpoint('btcpay-settings-callback', EP_ROOT);
});

// To be able to use the endpoint without appended url segments we need to do this.
add_filter('request', function($vars) {
    if (isset($vars['btcpay-settings-callback'])) {
        $vars['btcpay-settings-callback'] = true;
    }
    return $vars;
});



add_action('init', array('PMProGateway_coinsnap', 'process_webhook'));
add_action('plugins_loaded', function (): void {
    
    if (!class_exists('PMProGateway')) {
        add_action('admin_notices', 'pmpro_gateway_notice');
        return;
    }

    require_once(dirname(__FILE__) . "/library/loader.php");

    add_action('init', array('PMProGateway_coinsnap', 'init'));
    add_filter('plugin_action_links', array('PMProGateway_coinsnap', 'plugin_action_links'), 10, 2 );
    
    
    //  Coinsnap gateway class
    class PMProGateway_coinsnap extends PMProGateway {
		
        public const WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];	 
			
	function __construct($gateway = null){
            $this->gateway = ($gateway == "coinsnap" || $gateway == "btcpay")? 'coinsnap' : $gateway;
            return $this->gateway;
	}
		
	public static function init(){
            
            add_filter('pmpro_gateways', array('PMProGateway_coinsnap', 'pmpro_gateways'));						
            add_filter('pmpro_payment_options', array('PMProGateway_coinsnap', 'pmpro_payment_options'));
            add_filter('pmpro_payment_option_fields', array('PMProGateway_coinsnap', 'pmpro_payment_option_fields'), 10, 2);
            				
            $gateway = pmpro_getGateway();
				
            if($gateway == "coinsnap" || $gateway == "btcpay"){					
		add_filter('pmpro_include_billing_address_fields', '__return_false');
		add_filter('pmpro_include_payment_information_fields', '__return_false');
		add_filter('pmpro_required_billing_fields', array('PMProGateway_coinsnap', 'pmpro_required_billing_fields'));
		add_filter('pmpro_checkout_before_change_membership_level', ['PMProGateway_coinsnap', 'pmpro_checkout_before_change_membership_level'], 1, 2);
		add_filter('pmpro_checkout_default_submit_button', ['PMProGateway_coinsnap', 'pmpro_checkout_default_submit_button']);
                
                add_action('pmpro_add_order',['PMProGateway_coinsnap', 'set_gateway_to_order']);

                
                if (is_admin()) {
                    add_action('admin_notices', ['PMProGateway_coinsnap', 'coinsnap_notice']);
                    add_action('admin_enqueue_scripts', ['PMProGateway_coinsnap', 'enqueueAdminScripts'] );
                    add_action('wp_ajax_coinsnap_connection_handler', ['PMProGateway_coinsnap', 'coinsnapConnectionHandler'] );
                    add_action('wp_ajax_btcpay_server_apiurl_handler', ['PMProGateway_coinsnap', 'btcpayApiUrlHandler']);
                }
            }
            
            
            function set_gateway_to_order($morder){
                
                    pmpro_setOption( "gateway", 'coinsnap');
                    update_option("pmpro_gateway", 'coinsnap');
                
            }
            
            
        // Adding template redirect handling for btcpay-settings-callback.
        add_action( 'template_redirect', function(){
    
            global $wp_query;
            $notice = new \Coinsnap\Util\Notice();
            
            // Only continue on a btcpay-settings-callback request.    
            if (!isset( $wp_query->query_vars['btcpay-settings-callback'])) {
                return;
            }

            $CoinsnapBTCPaySettingsUrl = admin_url('admin.php?page=pmpro-paymentsettings');

            $rawData = file_get_contents('php://input');

            $btcpay_server_url = pmpro_getOption( 'btcpay_server_url');
            $btcpay_api_key  = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $client = new \Coinsnap\Client\Store($btcpay_server_url,$btcpay_api_key);
            if (count($client->getStores()) < 1) {
                $messageAbort = __('Error on verifiying redirected API Key with stored BTCPay Server url. Aborting API wizard. Please try again or continue with manual setup.', 'coinsnap-for-paid-memberships-pro');
                $notice->addNotice('error', $messageAbort);
                wp_redirect($CoinsnapBTCPaySettingsUrl);
            }

            // Data does get submitted with url-encoded payload, so parse $_POST here.
            if (!empty($_POST) || wp_verify_nonce(filter_input(INPUT_POST,'wp_nonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS),'-1')) {
                $data['apiKey'] = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;
                $permissions = (isset($_POST['permissions']) && is_array($_POST['permissions']))? $_POST['permissions'] : null;
                if (isset($permissions)) {
                    foreach ($permissions as $key => $value) {
                        $data['permissions'][$key] = sanitize_text_field($permissions[$key] ?? null);
                    }
                }
            }
    
            if (isset($data['apiKey']) && isset($data['permissions'])) {

                $apiData = new \Coinsnap\Client\BTCPayApiAuthorization($data);
                if ($apiData->hasSingleStore() && $apiData->hasRequiredPermissions()) {

                    pmpro_setOption( "btcpay_api_key", $apiData->getApiKey());
                    pmpro_setOption( "btcpay_store_id", $apiData->getStoreID());
                    pmpro_setOption( "gateway", 'btcpay');

                    $notice->addNotice('success', __('Successfully received api key and store id from BTCPay Server API. Please finish setup by saving this settings form.', 'coinsnap-for-paid-memberships-pro'));

                    // Register a webhook.
                    if (self::registerWebhook( $apiData->getStoreID(), $apiData->getApiKey(), self::get_webhook_url())) {
                        $messageWebhookSuccess = __( 'Successfully registered a new webhook on BTCPay Server.', 'coinsnap-for-paid-memberships-pro' );
                        $notice->addNotice('success', $messageWebhookSuccess);
                    }
                    else {
                        $messageWebhookError = __( 'Could not register a new webhook on the store.', 'coinsnap-for-paid-memberships-pro' );
                        $notice->addNotice('error', $messageWebhookError );
                    }

                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
                else {
                    $notice->addNotice('error', __('Please make sure you only select one store on the BTCPay API authorization page.', 'coinsnap-for-paid-memberships-pro'));
                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
            }

            $notice->addNotice('error', __('Error processing the data from Coinsnap. Please try again.', 'coinsnap-for-paid-memberships-pro'));
            wp_redirect($CoinsnapBTCPaySettingsUrl);
            exit();
        });
            
	}
        
        public static function enqueueAdminScripts() {
            // Register the CSS file
            wp_register_style( 'coinsnappmpro-admin-styles', plugins_url('assets/css/backend-style.css', __FILE__ ), array(), COINSNAPPMPRO_PLUGIN_VERSION );
            // Enqueue the CSS file
            wp_enqueue_style( 'coinsnappmpro-admin-styles' );
            //  Enqueue admin fileds handler script
            wp_enqueue_script('coinsnappmpro-admin-fields',plugins_url('assets/js/adminFields.js', __FILE__ ),[ 'jquery' ],COINSNAPPMPRO_PLUGIN_VERSION,true);
            wp_enqueue_script('coinsnappmpro-connection-check',plugins_url('assets/js/connectionCheck.js', __FILE__ ),[ 'jquery' ],COINSNAPPMPRO_PLUGIN_VERSION,true);
            wp_localize_script('coinsnappmpro-connection-check', 'coinsnappmpro_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'  => wp_create_nonce( 'coinsnappmpro-ajax-nonce' ),
            ));
        }
        
        public static function coinsnapConnectionHandler(){
            global $pmpro_currency;
            $_nonce = filter_input(INPUT_POST,'_wpnonce',FILTER_SANITIZE_STRING);

            if(empty(self::getApiUrl()) || empty(self::getApiKey())){
                $response = [
                        'result' => false,
                        'message' => __('PMPro: empty gateway URL or API Key', 'coinsnap-for-paid-memberships-pro')
                ];
                self::sendJsonResponse($response);
            }

            $_provider = self::get_payment_provider();
            $client = new \Coinsnap\Client\Invoice(self::getApiUrl(),self::getApiKey());
            $store = new \Coinsnap\Client\Store(self::getApiUrl(),self::getApiKey());

            if($_provider === 'btcpay'){
                try {
                    $storePaymentMethods = $store->getStorePaymentMethods(self::getStoreId());

                    if ($storePaymentMethods['code'] === 200) {
                        if($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                            $checkInvoice = $client->checkPaymentData(0,$pmpro_currency,'bitcoin','calculation');
                        }
                        elseif($storePaymentMethods['result']['lightning']){
                            $checkInvoice = $client->checkPaymentData(0,$pmpro_currency,'lightning','calculation');
                        }
                    }
                }
                catch (\Exception $e) {
                    $response = [
                            'result' => false,
                            'message' => __('PMPro: API connection is not established', 'coinsnap-for-paid-memberships-pro')
                    ];
                    self::sendJsonResponse($response);
                }
            }
            else {
                $checkInvoice = $client->checkPaymentData(0,$pmpro_currency,'coinsnap','calculation');
            }

            if(isset($checkInvoice) && $checkInvoice['result']){
                $connectionData = __('Min order amount is', 'coinsnap-for-paid-memberships-pro') .' '. $checkInvoice['min_value'].' '.$pmpro_currency;
            }
            else {
                $connectionData = __('No payment method is configured', 'coinsnap-for-paid-memberships-pro');
            }

            $_message_disconnected = ($_provider !== 'btcpay')? 
                __('PMPro: Coinsnap server is disconnected', 'coinsnap-for-paid-memberships-pro') :
                __('PMPro: BTCPay server is disconnected', 'coinsnap-for-paid-memberships-pro');
            $_message_connected = ($_provider !== 'btcpay')?
                __('PMPro: Coinsnap server is connected', 'coinsnap-for-paid-memberships-pro') : 
                __('PMPro: BTCPay server is connected', 'coinsnap-for-paid-memberships-pro');

            if( wp_verify_nonce($_nonce,'coinsnappmpro-ajax-nonce') ){
                $response = ['result' => false,'message' => $_message_disconnected];

                try {
                    $this_store = $store->getStore(self::getStoreId());

                    if ($this_store['code'] !== 200) {
                        self::sendJsonResponse($response);
                    }

                    $webhookExists = self::webhookExists(self::getStoreId(), self::getApiKey(), self::get_webhook_url());

                    if($webhookExists) {
                        $response = ['result' => true,'message' => $_message_connected.' ('.$connectionData.')'];
                        self::sendJsonResponse($response);
                    }

                    $webhook = self::registerWebhook( self::getStoreId(), self::getApiKey(), self::get_webhook_url());
                    $response['result'] = (bool)$webhook;
                    $response['message'] = $webhook ? $_message_connected.' ('.$connectionData.')' : $_message_disconnected.' (Webhook)';
                }
                catch (\Exception $e) {
                    $response = [
                            'result' => false,
                            'message' => __('PMPro: API connection is not established', 'coinsnap-for-paid-memberships-pro')
                    ];
                }

                self::sendJsonResponse($response);
            }      
        }

        static function sendJsonResponse(array $response): void {
            echo wp_json_encode($response);
            exit();
        }

        /**
         * Handles the BTCPay server AJAX callback from the settings form.
         */
        static function btcpayApiUrlHandler() {
            $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_STRING);
            if ( !wp_verify_nonce( $_nonce, 'coinsnappmpro-ajax-nonce' ) ) {
                wp_die('Unauthorized!', '', ['response' => 401]);
            }

            if ( current_user_can( 'manage_options' ) ) {
                $host = filter_var(filter_input(INPUT_POST,'host',FILTER_SANITIZE_STRING), FILTER_VALIDATE_URL);

                if ($host === false || (substr( $host, 0, 7 ) !== "http://" && substr( $host, 0, 8 ) !== "https://")) {
                    wp_send_json_error("Error validating BTCPayServer URL.");
                }

                $permissions = array_merge([
                    'btcpay.store.canviewinvoices',
                    'btcpay.store.cancreateinvoice',
                    'btcpay.store.canviewstoresettings',
                    'btcpay.store.canmodifyinvoices'
                ],
                [
                    'btcpay.store.cancreatenonapprovedpullpayments',
                    'btcpay.store.webhooks.canmodifywebhooks',
                ]);

                try {
                    // Create the redirect url to BTCPay instance.
                    $url = \Coinsnap\Client\BTCPayApiKey::getAuthorizeUrl(
                        $host,
                        $permissions,
                        'PMPro',
                        true,
                        true,
                        home_url('?btcpay-settings-callback'),
                        null
                    );

                    // Store the host to options before we leave the site.
                    pmpro_setOption('btcpay_server_url',$host);

                    // Return the redirect url.
                    wp_send_json_success(['url' => $url]);
                }

                catch (\Throwable $e) {

                }
            }
            wp_send_json_error("Error processing Ajax request.");
        }
        
        static function coinsnap_notice(){
        
            $page = (filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
            //$tab = (filter_input(INPUT_GET,'tab',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'tab',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';

            if($page === 'pmpro-paymentsettings'){

                $coinsnap_url = self::getApiUrl();
                $coinsnap_api_key = self::getApiKey();
                $coinsnap_store_id = self::getStoreId();
                $coinsnap_webhook_url = self::get_webhook_url();

                    if(!isset($coinsnap_store_id) || empty($coinsnap_store_id)){
                        echo '<div class="notice notice-error"><p>';
                        esc_html_e('PMPro: Coinsnap Store ID is not set', 'coinsnap-for-paid-memberships-pro');
                        echo '</p></div>';
                    }

                    if(!isset($coinsnap_api_key) || empty($coinsnap_api_key)){
                        echo '<div class="notice notice-error"><p>';
                        esc_html_e('PMPro: Coinsnap API Key is not set', 'coinsnap-for-paid-memberships-pro');
                        echo '</p></div>';
                    }

                    if(!empty($coinsnap_api_key) && !empty($coinsnap_store_id)){
                        $client = new \Coinsnap\Client\Store($coinsnap_url, $coinsnap_api_key);
                        try {
                            $store = $client->getStore($coinsnap_store_id);
                            if ($store['code'] === 200) {
                                echo '<div class="notice notice-success"><p>';
                                esc_html_e('PMPro: Established connection to Coinsnap Server', 'coinsnap-for-paid-memberships-pro');
                                echo '</p></div>';

                                if ( ! self::webhookExists( $coinsnap_store_id, $coinsnap_api_key, $coinsnap_webhook_url ) ) {
                                    if ( ! self::registerWebhook( $coinsnap_store_id, $coinsnap_api_key, $coinsnap_webhook_url ) ) {
                                        echo '<div class="notice notice-error"><p>';
                                        esc_html_e('PMPro: Unable to create webhook on Coinsnap Server', 'coinsnap-for-paid-memberships-pro');
                                        echo '</p></div>';
                                    }
                                    else {
                                        echo '<div class="notice notice-success"><p>';
                                        esc_html_e('PMPro: Successfully registered webhook on Coinsnap Server', 'coinsnap-for-paid-memberships-pro');
                                        echo '</p></div>';
                                    }
                                }
                                else {
                                    echo '<div class="notice notice-info"><p>';
                                    esc_html_e('PMPro: Webhook already exists, skipping webhook creation', 'coinsnap-for-paid-memberships-pro');
                                    echo '</p></div>';
                                }
                            }
                            else {
                                echo '<div class="notice notice-error"><p>';
                                esc_html_e('PMPro: Coinsnap connection error:', 'coinsnap-for-paid-memberships-pro');
                                echo esc_html($store['result']['message']);
                                echo '</p></div>';
                            }
                        }
                        catch (\Exception $e) {
                echo '<div class="notice notice-error"><p>';
                                esc_html_e('PMPro: Coinsnap connection error:', 'coinsnap-for-paid-memberships-pro');
                                echo '</p></div>';
            }
                    }
            }
        }

	static function pmpro_checkout_default_submit_button($show) {
            global $pmpro_requirebilling;
            $button_text = self::getButtonText();

            //show our submit buttons?>
            <span id="pmpro_submit_span">
                <input type="hidden" name="submit-checkout" value="1" />
                <input type="submit" class="<?php echo esc_html(pmpro_get_element_class( 'pmpro_btn pmpro_btn-submit-checkout', 'pmpro_btn-submit-checkout' )); ?>" value="<?php if($pmpro_requirebilling) { echo esc_html($button_text); } else { esc_html_e('Submit and Confirm', 'coinsnap-for-paid-memberships-pro' );}?>" />
            </span>
	<?php
            return false;
	}

	public static function plugin_action_links($links, $file){
            
            static $this_plugin;
            if (false === isset($this_plugin) || true === empty($this_plugin)) {
		$this_plugin = plugin_basename(__FILE__);
            }
			
            if ($file == $this_plugin) {
		$settings_link = '<a href="'.admin_url('admin.php?page=pmpro-paymentsettings').'">'.__( 'Settings', 'coinsnap-for-paid-memberships-pro' ).'</a>';
		array_unshift($links, $settings_link);
            }	
            return $links;
	}
			
	public static function pmpro_gateways($gateways){
            if(empty($gateways['coinsnap'])){
                $gateways = array_slice($gateways, 0, 1) + array("coinsnap" => 'Coinsnap') + array("btcpay" => 'BTCPay') + array_slice($gateways, 1);
            }
            return $gateways;
	}
		
	public static function getGatewayOptions(){
            $options = array(
                'coinsnap_store_id',
                'coinsnap_api_key',
                'coinsnap_button_text',
                'btcpay_server_url',
                'btcpay_store_id',
                'btcpay_api_key',
                'btcpay_button_text',
                'coinsnap_autoredirect',
                'coinsnap_expired_status',
                'coinsnap_settled_status',
                'coinsnap_processing_status',
                'currency'
            );
		
            return $options;
	}
		
	public static function pmpro_payment_options($options){
				
            $coinsnap_options = self::getGatewayOptions();					
            $options = array_merge($coinsnap_options, $options);		
            return $options;
	}
							
        static function pmpro_payment_option_fields($values, $gateway){

            $statuses = pmpro_getOrderStatuses();

            $coinsnap_expired_status = !empty($values['coinsnap_expired_status']) ? $values['coinsnap_expired_status'] : 'cancelled';
            $coinsnap_settled_status = !empty($values['coinsnap_settled_status']) ? $values['coinsnap_settled_status'] : 'success';				
            $coinsnap_processing_status = !empty($values['coinsnap_processing_status']) ? $values['coinsnap_processing_status'] : 'success';?>

            <tr class="pmpro_settings_divider gateway gateway_coinsnap" <?php if($gateway !== "coinsnap") { ?>style="display: none;"<?php } ?>>
                <td colspan="2">
                    <hr /><h2 class="title"><?php esc_html_e('Coinsnap Settings', 'coinsnap-for-paid-memberships-pro' ); ?></h2>
                    <div class="coinsnapConnectionStatus"></div></td></tr>
            <tr class="pmpro_settings_divider gateway gateway_btcpay" <?php if($gateway !== "btcpay") { ?>style="display: none;"<?php } ?>>
                <td colspan="2">
                    <hr /><h2 class="title"><?php esc_html_e('BTCPay Settings', 'coinsnap-for-paid-memberships-pro' ); ?></h2>
                    <div class="coinsnapConnectionStatus"></div></td></tr>
            <tr class="gateway gateway_coinsnap" <?php if($gateway != "coinsnap") { ?>style="display: none;"<?php } ?>>
                <th scope="row" valign="top"><label for="coinsnap_store_id"><?php esc_html_e('Store ID*', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="coinsnap_store_id" name="coinsnap_store_id" value="<?php echo esc_attr($values['coinsnap_store_id'])?>" class="regular-text code" /></td>
            </tr>
            <tr class="gateway gateway_coinsnap" <?php if($gateway != "coinsnap") { ?>style="display: none;"<?php } ?>>
                <th scope="row" valign="top"><label for="coinsnap_api_key"><?php esc_html_e('API Key*', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="coinsnap_api_key" name="coinsnap_api_key" value="<?php echo esc_attr($values['coinsnap_api_key'])?>" class="regular-text code" /></td>
            </tr>
            <tr class="gateway gateway_coinsnap" <?php if($gateway != "coinsnap") { ?>style="display: none;"<?php } ?>>
                <th scope="row" valign="top"><label for="coinsnap_button_text"><?php esc_html_e('Button text', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="coinsnap_button_text" name="coinsnap_button_text" value="<?php echo (!empty($values['coinsnap_button_text']))? esc_attr($values['coinsnap_button_text']) : esc_html__('Coinsnap (Bitcoin + Lightning)', 'coinsnap-for-paid-memberships-pro' );?>" class="regular-text code" /></td>
            </tr
            <tr class="gateway gateway_btcpay" <?php if($gateway != "btcpay") { ?>style="display: none;"<?php } ?>>
                <th scope="row" valign="top"><label for="btcpay_server_url"><?php esc_html_e('BTCPay server URL*', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" placeholder="https://" id="btcpay_server_url" name="btcpay_server_url" value="<?php echo esc_attr($values['btcpay_server_url'])?>" class="regular-text code" /><br/>
                    <a href="#" class="btcpay-apikey-link"><?php echo esc_html__('Check connection', 'coinsnap-for-paid-memberships-pro' );?></a><br/><br/>
                    <button class="button btcpay-apikey-link" type="button" id="btcpay_wizard_button" target="_blank"><?php echo esc_html__('Generate API key','coinsnap-for-paid-memberships-pro');?></button></td>
            </tr>
            <tr class="gateway gateway_btcpay" <?php if($gateway != "btcpay") { ?>style="display: none;"<?php } ?>>
                <th scope="row" valign="top"><label for="btcpay_store_id"><?php esc_html_e('Store ID*', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="btcpay_store_id" name="btcpay_store_id" value="<?php echo esc_attr($values['btcpay_store_id'])?>" class="regular-text code" /></td>
            </tr>
            <tr class="gateway gateway_btcpay" <?php if($gateway != "btcpay") { ?>style="display: none;"<?php } ?>>
                <th scope="row" valign="top"><label for="btcpay_api_key"><?php esc_html_e('API Key*', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="btcpay_api_key" name="btcpay_api_key" value="<?php echo esc_attr($values['btcpay_api_key'])?>" class="regular-text code" /></td>
            </tr>
            <tr class="gateway gateway_btcpay" <?php if($gateway != "btcpay") { ?>style="display: none;"<?php } ?>>
                <th scope="row" valign="top"><label for="btcpay_button_text"><?php esc_html_e('Button text', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="btcpay_button_text" name="btcpay_button_text" value="<?php echo (!empty($values['btcpay_button_text']))? esc_attr($values['btcpay_button_text']) : esc_html__('BTCPay server (Bitcoin)', 'coinsnap-for-paid-memberships-pro' );?>" class="regular-text code" /></td>
            </tr>
            
            <tr class="gateway gateway_coinsnap gateway_btcpay" <?php if($gateway !== "coinsnap" && $gateway !== "btcpay") { ?>style="display: none;"<?php }?>>
                <th scope="row" valign="top"><label for="coinsnap_autoredirect"><?php esc_html_e('Redirect after payment', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="checkbox"<?php echo ($values['coinsnap_autoredirect']<1)? '' : ' checked="checked"';?> id="coinsnap_autoredirect" name="coinsnap_autoredirect" value="1" class="regular-text code" /></td></tr>
            <tr class="gateway gateway_coinsnap gateway_btcpay" <?php if($gateway !== "coinsnap" && $gateway !== "btcpay") { ?>style="display: none;"<?php } ?>>
                <th scope="row" valign="top"><label for="coinsnap_expired_status"><?php esc_html_e( 'Expired Status', 'coinsnap-for-paid-memberships-pro' ); ?>:</label></th>
                <td><select id="coinsnap_expired_status" name="coinsnap_expired_status">
                    <?php foreach ( $statuses as $status ) { ?>
                        <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $coinsnap_expired_status, $status ); ?>><?php echo esc_html( $status ); ?></option>
                    <?php } ?>
                    </select></td></tr>
            
            <tr class="gateway gateway_coinsnap gateway_btcpay" <?php if($gateway !== "coinsnap" && $gateway !== "btcpay") { ?>style="display: none;"<?php } ?>>
                <th scope="row" valign="top"><label for="coinsnap_settled_status"><?php esc_html_e( 'Settled Status', 'coinsnap-for-paid-memberships-pro' ); ?>:</label></th>
                <td><select id="coinsnap_settled_status" name="coinsnap_settled_status">
                    <?php foreach ( $statuses as $status ) { ?>
			<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $coinsnap_settled_status, $status ); ?>><?php echo esc_html( $status ); ?></option>
                    <?php } ?>
                    </select></td></tr>

            <tr class="gateway gateway_coinsnap gateway_btcpay" <?php if($gateway != "coinsnap" && $gateway !== "btcpay") { ?>style="display: none;"<?php } ?>>
                <th scope="row" valign="top"><label for="coinsnap_processing_status"><?php esc_html_e( 'Processing Status', 'coinsnap-for-paid-memberships-pro' ); ?>:</label></th>
		<td><select id="coinsnap_processing_status" name="coinsnap_processing_status">
                    <?php foreach ( $statuses as $status ) { ?>
                        <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $coinsnap_processing_status, $status ); ?>><?php echo esc_html( $status ); ?></option>
                    <?php } ?>
                    </select></td></tr>
            <?php return;
	}
			
        public static function pmpro_required_billing_fields($fields){
            unset($fields['bfirstname']);
            unset($fields['blastname']);
            unset($fields['baddress1']);
            unset($fields['bcity']);
            unset($fields['bstate']);
            unset($fields['bzipcode']);
            unset($fields['bphone']);
            unset($fields['bemail']);
            unset($fields['bcountry']);
            unset($fields['CardType']);
            unset($fields['AccountNumber']);
            unset($fields['ExpirationMonth']);
            unset($fields['ExpirationYear']);
            unset($fields['CVV']);
            return $fields;
        }
        
        //  =============================== ORDER PROCESSING =======================================
		
		
        function process(&$order){
            
            global $pmpro_currency;	
            $amount = round($order->subtotal, 2);
            
            $checkInvoice = self::coinsnappmpro_amount_validation($amount,strtoupper($pmpro_currency));
                
            if($checkInvoice['result'] === true){
                return true;
            }
            else {
                if($checkInvoice['error'] === 'currencyError'){
                            $errorMessage = sprintf( 
                            /* translators: 1: Currency */
                            __( 'Currency %1$s is not supported by Coinsnap', 'coinsnap-for-paid-memberships-pro' ), strtoupper( $pmpro_currency ));
                }      
                elseif($checkInvoice['error'] === 'amountError'){
                            $errorMessage = sprintf( 
                            /* translators: 1: Amount, 2: Currency */
                            __( 'Invoice amount cannot be less than %1$s %2$s', 'coinsnap-for-paid-memberships-pro' ), $checkInvoice['min_value'], strtoupper( $pmpro_currency ));
                }
                else {
                    $errorMessage = $checkInvoice['error'];
                }
                $order->error = esc_html($errorMessage);
                return false;
            }
	}
			
	static function coinsnappmpro_amount_validation( $amount, $currency ) {
            $client =new \Coinsnap\Client\Invoice(self::getApiUrl(), self::getApiKey());
            $store = new \Coinsnap\Client\Store(self::getApiUrl(), self::getApiKey());
                    
            $_provider = self::get_payment_provider();
            
            if($_provider === 'btcpay'){

                try {
                    $storePaymentMethods = $store->getStorePaymentMethods(self::getStoreId());

                    if ($storePaymentMethods['code'] === 200) {
                        if(!$storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                            $errorMessage = __( 'No payment method is configured on BTCPay server', 'coinsnap-for-paid-memberships-pro' );
                            $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
                        }
                    }
                    else {
                        $errorMessage = __( 'Error store loading. Wrong or empty Store ID', 'coinsnap-for-paid-memberships-pro' );
                        $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
                    }

                    if($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData((float)$amount,strtoupper( $currency ),'bitcoin');
                    }
                    elseif($storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData((float)$amount,strtoupper( $currency ),'lightning');
                    }
                }
                catch (\Throwable $e){
                    $errorMessage = __( 'API connection is not established.', 'coinsnap-for-paid-memberships-pro' );
                    $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
                }
            }
            else {
                $checkInvoice = $client->checkPaymentData((float)$amount,strtoupper( $currency ));
            }
            return $checkInvoice;
                   
        }

        static function pmpro_checkout_before_change_membership_level($user_id, $morder){
            global $wpdb, $discount_code_id, $pmpro_currency;
                                
            if(empty($morder)){return;}
            $morder->user_id = $user_id;				
            $morder->saveOrder();
					
            if(!empty($discount_code_id)){
                $wpdb->insert($wpdb->pmpro_discount_codes_uses, ['code_id' => $discount_code_id, 'user_id' => $user_id, 'order_id' => $morder->id, 'timestamp' => now()], ['%s', '%s', '%s', '%s']);
            }
							
            $morder->Gateway->sendToCoinsnap($morder);
	}
        
        public function sendToCoinsnap($order){
            global $pmpro_currency;	
            $amount = round($order->subtotal, 2);
            
            $client =new \Coinsnap\Client\Invoice(self::getApiUrl(), self::getApiKey());
            $checkInvoice = self::coinsnappmpro_amount_validation($amount,strtoupper($pmpro_currency));
                
            if($checkInvoice['result'] === true){
            
                $redirectUrl = esc_url(site_url().'/membership-confirmation/?level=').$order->membership_id;
            
                $current_user = wp_get_current_user();
                $buyerEmail = $current_user->user_email;
                $buyerName =  $current_user->user_firstname . ' ' . $current_user->user_lastname;
						    	
                $metadata = [];
                $metadata['orderNumber'] = $order->id;
                $metadata['customerName'] = $buyerName;
                            
                $redirectAutomatically = (pmpro_getOption( 'coinsnap_autoredirect') > 0 )? true : false;
                $walletMessage = '';

		$camount = \Coinsnap\Util\PreciseNumber::parseFloat($amount,2);
		$invoice = $client->createInvoice(
                    self::getStoreId(),  
                    $pmpro_currency,
                    $camount,
                    $order->id,
                    $buyerEmail,
                    $buyerName, 
                    $redirectUrl,
                    COINSNAPPMPRO_REFERRAL_CODE,     
                    $metadata,
                    $redirectAutomatically,
                    $walletMessage
		);
		
    		if($payurl = $invoice->getData()['checkoutLink']){
                    wp_redirect($payurl);
                }
            }
            else {
                if($checkInvoice['error'] === 'currencyError'){
                            $errorMessage = sprintf( 
                            /* translators: 1: Currency */
                            __( 'Currency %1$s is not supported by Coinsnap', 'coinsnap-for-paid-memberships-pro' ), strtoupper( $pmpro_currency ));
                }      
                elseif($checkInvoice['error'] === 'amountError'){
                            $errorMessage = sprintf( 
                            /* translators: 1: Amount, 2: Currency */
                            __( 'Invoice amount cannot be less than %1$s %2$s', 'coinsnap-for-paid-memberships-pro' ), $checkInvoice['min_value'], strtoupper( $pmpro_currency ));
                }
                else {
                    $errorMessage = $checkInvoice['error'];
                }
                $order->error = esc_html($errorMessage);
                return false;
            }
            exit;
        }
        
        //  =============================== API OPTIONS =======================================
		
	public static function get_payment_provider() {
            return (pmpro_getOption( 'gateway') === 'btcpay')? 'btcpay' : 'coinsnap';
        }
        
        public static function get_webhook_url() {
            return esc_url_raw( add_query_arg( array( 'pmp-listener' => 'coinsnap' ), home_url( 'index.php' ) ) );
        }
        
	public static function getApiKey() {
            return (self::get_payment_provider() === 'btcpay')? pmpro_getOption( 'btcpay_api_key') : pmpro_getOption( 'coinsnap_api_key');
        }
                
        public static function getStoreId() {
            return (self::get_payment_provider() === 'btcpay')? pmpro_getOption( 'btcpay_store_id') : pmpro_getOption( 'coinsnap_store_id');
        }
                
        public static function getApiUrl() {
            return (self::get_payment_provider() === 'btcpay')? pmpro_getOption( 'btcpay_server_url') : COINSNAP_SERVER_URL;
        }
                
        public static function getButtonText() {
            return (self::get_payment_provider() === 'btcpay')? pmpro_getOption( 'btcpay_button_text') : pmpro_getOption( 'coinsnap_button_text');
        }
	
        //  =============================== WEBHOOKS =======================================
        
        
        public static function process_webhook(){
			
            if ( null === ( filter_input(INPUT_GET,'pmp-listener') ) || filter_input(INPUT_GET,'pmp-listener') !== 'coinsnap' ) {
                return;
            }
				
            $notify_json = file_get_contents('php://input');
            $notify_ar = json_decode($notify_json, true);
            $invoice_id = $notify_ar['invoiceId'];        
				
            try {
                $client = new \Coinsnap\Client\Invoice( self::getApiUrl(), self::getApiKey() );			
                $invoice = $client->getInvoice(self::getStoreId(), $invoice_id);
                $status = $invoice->getData()['status'] ;
		$order_id = $invoice->getData()['orderId'] ;
            }
            catch (\Throwable $e) {									
		echo "Error";
		exit;
            }
						
            $order_status = 'pending';
            switch($status){
                case 'Expired':
                    $order_status = pmpro_getOption('coinsnap_expired_status');
                    break;
                case 'Processing':
                    $order_status = pmpro_getOption('coinsnap_processing_status');
                    break;
                case 'Settled':
                    $order_status = pmpro_getOption('coinsnap_settled_status');
                    break;
                default: break;
            }

            if (isset($order_id)){
                $morder = new MemberOrder();
		$morder->getMemberOrderByID( $order_id );
		$morder->getMembershipLevel();					
		$morder->status = $order_status;	
		$morder->saveOrder();
				
		if ($order_status === 'success'){
						
                    // Get discount code.
                    $morder->getDiscountCode();
                    if ( ! empty( $morder->discount_code ) ) {
		
                        // Update membership level
                        $morder->getMembershipLevel(true);
			$discount_code_id = $morder->discount_code->id;
                    }
                    else {
			$discount_code_id = "";
                    }
						
                    $morder->membership_level = apply_filters("pmpro_inshandler_level", $morder->membership_level, $morder->user_id);		
                    $startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time('mysql') . "'", $morder->user_id, $morder->membership_level);
		
                    //fix expiration date
                    if(!empty($morder->membership_level->expiration_number)){
                        $enddate = "'" . date_i18n("Y-m-d", strtotime("+ " . $morder->membership_level->expiration_number . " " . $morder->membership_level->expiration_period, current_time("timestamp"))) . "'";
                    }
                    else {
                        $enddate = "NULL";
                    }
						
                    //filter the enddate (documented in preheaders/checkout.php)
                    $enddate = apply_filters("pmpro_checkout_end_date", $enddate, $morder->user_id, $morder->membership_level, $startdate);
                    
                    if($morder->user_id !== null){

                        $custom_level = array(
                            'user_id' => $morder->user_id,
                            'membership_id' => $morder->membership_level->id,
                            'code_id' => $discount_code_id,
                            'initial_payment' => $morder->membership_level->initial_payment,
                            'billing_amount' => $morder->membership_level->billing_amount,
                            'cycle_number' => $morder->membership_level->cycle_number,
                            'cycle_period' => $morder->membership_level->cycle_period,
                            'billing_limit' => $morder->membership_level->billing_limit,
                            'trial_amount' => $morder->membership_level->trial_amount,
                            'trial_limit' => $morder->membership_level->trial_limit,
                            'startdate' => $startdate,
                            'enddate' => $enddate);

                        pmpro_changeMembershipLevel($custom_level, $morder->user_id);
                    }
		}
					
            }
            echo "OK";
            exit;
	}
        
        public static function webhookExists(string $storeId, string $apiKey, string $webhook): bool {	
                    try {		
                        $whClient = new \Coinsnap\Client\Webhook( self::getApiUrl(), $apiKey );		
                        $Webhooks = $whClient->getWebhooks( $storeId );			
				
			foreach ($Webhooks as $Webhook){					
                            //self::deleteWebhook($storeId,$apiKey, $Webhook->getData()['id']);
                            if ($Webhook->getData()['url'] == $webhook){
                                return true;
                            }
			}
                    }
                    catch (\Throwable $e) {			
                        return false;
                    }
                    return false;
        }
                
        public static function registerWebhook(string $storeId, string $apiKey, string $webhook): bool {
                    try {			
                        $whClient = new \Coinsnap\Client\Webhook(self::getApiUrl(), $apiKey);
			$webhook = $whClient->createWebhook(
                            $storeId,   //$storeId
                            $webhook, //$url
                            self::WEBHOOK_EVENTS,   //$specificEvents
                            null    //$secret
			);
                        return true;
                    }
                    catch (\Throwable $e) {
                        return false;	
                    }
                    //return false;
        }
	
        public static function deleteWebhook(string $storeId, string $apiKey, string $webhookid): bool {	    
			
			try {			
				$whClient = new \Coinsnap\Client\Webhook(self::getApiUrl(), $apiKey);
				
				$webhook = $whClient->deleteWebhook(
					$storeId,   //$storeId
					$webhookid, //$url			
				);					
				return true;
			} catch (\Throwable $e) {
				
				return false;	
            }
        }
    }
    
    //  BTCPay gateway class
    class PMProGateway_btcpay extends PMProGateway {
        public static function getGatewayOptions(){
            $options = array(
                'coinsnap_store_id',
                'coinsnap_api_key',
                'coinsnap_button_text',
                'btcpay_server_url',
                'btcpay_store_id',
                'btcpay_api_key',
                'btcpay_button_text',
                'coinsnap_autoredirect',
                'coinsnap_expired_status',
                'coinsnap_settled_status',
                'coinsnap_processing_status',
                'currency'
            );
            return $options;
	}
        
        function process(&$order){
            $order->setGateway('coinsnap');
            $coinsnapGateway = new PMProGateway_coinsnap();
            $coinsnapGateway->process($order);
        }
    }
    
});
                                                                           