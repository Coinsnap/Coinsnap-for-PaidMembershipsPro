<?php
if (!defined( 'ABSPATH' )){
    exit;
}
use Coinsnap\Client\Webhook;

//  Coinsnap gateway class
    class PMProGateway_coinsnap extends PMProGateway {
		
        public const COINSNAP_WEBHOOK_EVENTS = ['New','Expired','Settled','Processing','Invalid'];
        public const BTCPAY_WEBHOOK_EVENTS = ['InvoiceCreated','InvoiceExpired','InvoiceSettled','InvoiceProcessing','InvoiceInvalid'];
        private static $btcpayUrlChanged = false;
        private static $btcpayKeyRefused = false;
			
	public function __construct($gateway = null){
            $this->gateway = $gateway;
            return $this->gateway;
	}
		
	public static function init(){
            
            add_filter('pmpro_gateways', array('PMProGateway_coinsnap', 'pmpro_gateways'));						
            add_filter('pmpro_payment_options', array('PMProGateway_coinsnap', 'pmpro_payment_options'));
            add_filter('pmpro_payment_option_fields', array('PMProGateway_coinsnap', 'pmpro_payment_option_fields'), 10, 2);
            				
            add_filter('pmpro_include_billing_address_fields', '__return_false');
            add_filter('pmpro_include_payment_information_fields', '__return_false');
            add_filter('pmpro_required_billing_fields', array('PMProGateway_coinsnap', 'pmpro_required_billing_fields'));
            add_filter('pmpro_checkout_before_change_membership_level', ['PMProGateway_coinsnap', 'coinsnappmpro_checkout_before_change_membership_level'], 1, 2);
            add_filter('pmpro_checkout_default_submit_button', ['PMProGateway_coinsnap', 'pmpro_checkout_default_submit_button']);
                
            add_action('pmpro_add_order',['PMProGateway_coinsnap', 'set_gateway_to_order']);
                
            if (is_admin()) {
                    add_action('admin_notices', ['PMProGateway_coinsnap', 'coinsnap_notice']);
                    add_action('admin_enqueue_scripts', ['PMProGateway_coinsnap', 'enqueueAdminScripts'] );
                    add_action('wp_ajax_coinsnap_connection_handler', ['PMProGateway_coinsnap', 'coinsnapConnectionHandler'] );
                    add_action('wp_ajax_pmpro_btcpay_server_apiurl_handler', ['PMProGateway_coinsnap', 'btcpayApiUrlHandler']);
                    add_filter('pre_update_option_pmpro_btcpay_server_url', ['PMProGateway_coinsnap', 'noteBtcpayUrlChange'], 10, 2);
                    add_filter('pre_update_option_pmpro_coinsnap_api_key', ['PMProGateway_coinsnap', 'keepStoredApiKey'], 10, 3);
                    add_filter('pre_update_option_pmpro_btcpay_api_key', ['PMProGateway_coinsnap', 'keepStoredApiKey'], 10, 3);
                    add_filter('pre_update_option_pmpro_btcpay_api_key', ['PMProGateway_coinsnap', 'checkPastedBtcpayKey'], 11, 2);
            }
            
            
            
        // Adding template redirect handling for coinsnap-for-paid-memberships-pro-btcpay-settings-callback.
        add_action( 'template_redirect', function(){
    
            global $wp_query;
            $notice = new \Coinsnap\Util\Notice();
            
            // Only continue on a coinsnap-for-paid-memberships-pro-btcpay-settings-callback request.    
            if (!isset( $wp_query->query_vars['coinsnap-for-paid-memberships-pro-btcpay-settings-callback'])) {
                return;
            }
            
            $CoinsnapBTCPaySettingsUrl = admin_url('admin.php?page=pmpro-paymentsettings&edit_gateway=coinsnap');

            // BTCPay posts here from the admin's browser; only the state handed out by btcpayApiUrlHandler() is accepted, once.
            $state = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['state'])) : '';
            $stateHost = ($state !== '') ? get_transient('coinsnappmpro_btcpay_state_' . $state) : false;
            if ($state !== '') {
                delete_transient('coinsnappmpro_btcpay_state_' . $state);
            }
            $btcpay_server_url = is_string($stateHost) ? $stateHost : '';
            if ($btcpay_server_url === '') {
                status_header(403);
                exit();
            }

            $apiKey = isset($_POST['apiKey']) ? sanitize_text_field(wp_unslash($_POST['apiKey'])) : '';
            // What the key may do, and for which store, is asked from BTCPay; POSTed permissions are ignored.
            $storeId = ($apiKey !== '') ? \CoinsnapPMPro\BtcpayKey::verify($btcpay_server_url, $apiKey) : null;
            if ($storeId === null) {
                $notice->addNotice('error', __('The API key from BTCPay Server was refused. It must be limited to one store and have only the permissions this plugin asks for. Please try again or continue with manual setup.', 'coinsnap-for-paid-memberships-pro'));
                wp_redirect($CoinsnapBTCPaySettingsUrl);
                exit();
            }

            pmpro_setOption( "btcpay_server_url", $btcpay_server_url);
            pmpro_setOption( "btcpay_api_key", $apiKey);
            pmpro_setOption( "btcpay_store_id", $storeId);
            pmpro_setOption( "coinsnap_provider", 'btcpay');

            $notice->addNotice('success', __('Successfully received api key and store id from BTCPay Server API. Please finish setup by saving this settings form.', 'coinsnap-for-paid-memberships-pro'));

            // Register a webhook.
            if (self::registerWebhook( $btcpay_server_url, $apiKey, $storeId)) {
                $notice->addNotice('success', __( 'Successfully registered a new webhook on BTCPay Server.', 'coinsnap-for-paid-memberships-pro' ));
            }
            else {
                $notice->addNotice('error', __( 'Could not register a new webhook on the store.', 'coinsnap-for-paid-memberships-pro' ));
            }

            wp_redirect($CoinsnapBTCPaySettingsUrl);
            exit();
        });
            
	}
        
        public static function set_gateway_to_order($morder){
            pmpro_setOption( "gateway", 'coinsnap');
            update_option("pmpro_gateway", 'coinsnap');
        }
        
        public static function enqueueAdminScripts() {
            
            // Register the CSS file
            wp_register_style( 'coinsnappmpro-admin-styles', plugins_url('assets/css/backend-style.css', __FILE__ ), array(), COINSNAPPMPRO_PLUGIN_VERSION );
            // Enqueue the CSS file
            wp_enqueue_style( 'coinsnappmpro-admin-styles' );
            //  Enqueue admin fileds handler script
            if('coinsnap' === filter_input(INPUT_GET,'edit_gateway',FILTER_SANITIZE_FULL_SPECIAL_CHARS)){
                wp_enqueue_script('coinsnappmpro-admin-fields',plugins_url('assets/js/adminFields.js', __FILE__ ),[ 'jquery' ],COINSNAPPMPRO_PLUGIN_VERSION,true);
            }
            wp_enqueue_script('coinsnappmpro-connection-check',plugins_url('assets/js/connectionCheck.js', __FILE__ ),[ 'jquery' ],COINSNAPPMPRO_PLUGIN_VERSION,true);
            wp_localize_script('coinsnappmpro-connection-check', 'coinsnappmpro_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'  => wp_create_nonce( 'coinsnappmpro-ajax-nonce' ),
            ));
        }
        
        public static function coinsnapConnectionHandler(){
            global $pmpro_currency;
            $_nonce = filter_input(INPUT_POST,'_wpnonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            // Before any API call: it can re-register the webhook and replace its secret.
            if (!wp_verify_nonce((string) $_nonce, 'coinsnappmpro-ajax-nonce') || !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Not allowed'], 403);
            }

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
                catch (\Throwable $e) {
                    $response = [
                            'result' => false,
                            'message' => __('PMPro: API connection is not established', 'coinsnap-for-paid-memberships-pro')
                    ];
                    self::sendJsonResponse($response);
                }
            }
            else {
                try {
                    $checkInvoice = $client->checkPaymentData(0,$pmpro_currency,'coinsnap','calculation');
                }
                catch (\Throwable $e) {
                    $checkInvoice = ['result' => false];
                }
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

                    $webhookExists = self::webhookExists(self::getApiUrl(), self::getApiKey(), self::getStoreId());

                    if($webhookExists) {
                        $response = ['result' => true,'message' => $_message_connected.' ('.$connectionData.')'];
                        self::sendJsonResponse($response);
                    }

                    $webhook = self::registerWebhook(self::getApiUrl(), self::getApiKey(), self::getStoreId());
                    $response['result'] = (bool)$webhook;
                    $response['message'] = $webhook ? $_message_connected.' ('.$connectionData.')' : $_message_disconnected.' (Webhook)';
                }
                catch (\Throwable $e) {
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
            $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if ( !wp_verify_nonce( $_nonce, 'coinsnappmpro-ajax-nonce' ) ) {
                wp_die('Unauthorized!', '', ['response' => 401]);
            }

            if ( current_user_can( 'manage_options' ) ) {
                $host = filter_var(trim((string) filter_input(INPUT_POST,'host',FILTER_UNSAFE_RAW)), FILTER_VALIDATE_URL);

                if ($host === false || (substr( $host, 0, 7 ) !== "http://" && substr( $host, 0, 8 ) !== "https://")) {
                    wp_send_json_error("Error validating BTCPayServer URL.");
                }

                // Only what the plugin uses, for one store (strict + selectiveStores below).
                $permissions = \CoinsnapPMPro\BtcpayKey::NEEDED_PERMISSIONS;

                $state = wp_generate_password(32, false);
                set_transient('coinsnappmpro_btcpay_state_' . $state, $host, 15 * MINUTE_IN_SECONDS);

                try {
                    // Create the redirect url to BTCPay instance.
                    $url = \Coinsnap\Client\BTCPayApiKey::getAuthorizeUrl(
                        $host,
                        $permissions,
                        'PMPro',
                        true,
                        true,
                        add_query_arg('state', $state, home_url('?coinsnap-for-paid-memberships-pro-btcpay-settings-callback')),
                        null
                    );

                    // The host is saved only by the callback, once a key for it is verified; the stored key never goes to an unverified host.

                    // Return the redirect url.
                    wp_send_json_success(['url' => $url]);
                }

                catch (\Throwable $e) {
                    wp_send_json_error("Error processing Ajax request.");
                }
            }
            wp_send_json_error("Error processing Ajax request.");
        }
        
        static function coinsnap_notice(){
        
            $page = (filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';

            if($page === 'pmpro-paymentsettings'){

                // Results of the BTCPay connect callback.
                (new \Coinsnap\Util\Notice())->showNotices();

                $coinsnap_url = self::getApiUrl();
                $coinsnap_api_key = self::getApiKey();
                $coinsnap_store_id = self::getStoreId();

                    if(null === $coinsnap_store_id || empty($coinsnap_store_id)){
                        echo '<div class="notice notice-error"><p>';
                        esc_html_e('PMPro: Coinsnap Store ID is not set', 'coinsnap-for-paid-memberships-pro');
                        echo '</p></div>';
                    }

                    if(null === $coinsnap_api_key || empty($coinsnap_api_key)){
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

                                // A page view only shows the status. The webhook is checked and registered by the
                                // connection check (AJAX, nonce and manage_options), never by a plain GET.
                                $stored = get_option('pmpro_coinsnap_webhook');
                                if (!is_array($stored) || empty($stored['secret'])) {
                                    echo '<div class="notice notice-error"><p>';
                                    esc_html_e('PMPro: No webhook is registered yet. It is registered by the connection check on this page.', 'coinsnap-for-paid-memberships-pro');
                                    echo '</p></div>';
                                }
                            }
                            else {
                                echo '<div class="notice notice-error"><p>';
                                esc_html_e('PMPro: Coinsnap connection error. Check the Store ID and API key.', 'coinsnap-for-paid-memberships-pro');
                                echo '</p></div>';
                            }
                        }
                        catch (\Throwable $e) {
                            echo '<div class="notice notice-error"><p>';
                            esc_html_e('PMPro: Coinsnap connection error. Check the Store ID and API key.', 'coinsnap-for-paid-memberships-pro');
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
                $gateways = array_slice($gateways, 0, 1) + array("coinsnap" => 'Coinsnap') + array_slice($gateways, 1);
            }
            return $gateways;
	}
		
	public static function getGatewayOptions(){
            $options = array(
                'coinsnap_provider',
                'coinsnap_store_id',
                'coinsnap_api_key',
                'coinsnap_button_text',
                'btcpay_server_url',
                'btcpay_store_id',
                'btcpay_api_key',
                'btcpay_button_text',
                'coinsnap_autoredirect',
                'coinsnap_returnurl',
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

            // Not "cancelled": PMPro 3 saves that status as "success".
            $coinsnap_expired_status = (!empty($values['coinsnap_expired_status']) && $values['coinsnap_expired_status'] !== 'cancelled') ? $values['coinsnap_expired_status'] : 'error';
            $coinsnap_settled_status = !empty($values['coinsnap_settled_status']) ? $values['coinsnap_settled_status'] : 'success';				
            $coinsnap_processing_status = !empty($values['coinsnap_processing_status']) ? $values['coinsnap_processing_status'] : 'pending';
            
            ?>

            <tr class="pmpro_settings_divider gateway" <?php if($gateway !== "coinsnap") { ?>style="display: none;"<?php } ?>>
                <td colspan="2">
                    <hr /><h2 class="title"><?php esc_html_e('Coinsnap Settings', 'coinsnap-for-paid-memberships-pro' );?></h2>
                    <div class="coinsnapConnectionStatus"></div></td>
            </tr>
            <tr class="gateway">
                <th scope="row" valign="top"><label for="coinsnap_provider"><?php esc_html_e( 'Payment provider', 'coinsnap-for-paid-memberships-pro' ); ?>:</label></th>
                <td><select id="coinsnap_provider" name="coinsnap_provider">
                        <option value="coinsnap" <?php selected( $values['coinsnap_provider'], 'coinsnap' ); ?>><?php echo esc_html('Coinsnap'); ?></option>
                        <option value="btcpay" <?php selected( $values['coinsnap_provider'], 'btcpay' ); ?>><?php echo esc_html('BTCPay Server'); ?></option>
                    </select></td>
            </tr>
            <tr class="gateway gateway_coinsnap">
                <th scope="row" valign="top"><label for="coinsnap_store_id"><?php esc_html_e('Store ID*', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="coinsnap_store_id" name="coinsnap_store_id" value="<?php echo esc_attr($values['coinsnap_store_id'])?>" class="regular-text code" /></td>
            </tr>
            <tr class="gateway gateway_coinsnap">
                <th scope="row" valign="top"><label for="coinsnap_api_key"><?php esc_html_e('API Key*', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><?php self::renderApiKeyField('coinsnap_api_key', (string) $values['coinsnap_api_key']); ?></td>
            </tr>
            <tr class="gateway gateway_coinsnap">
                <th scope="row" valign="top"><label for="coinsnap_button_text"><?php esc_html_e('Button text', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="coinsnap_button_text" name="coinsnap_button_text" value="<?php echo (!empty($values['coinsnap_button_text']))? esc_attr($values['coinsnap_button_text']) : esc_html__('Coinsnap (Bitcoin + Lightning)', 'coinsnap-for-paid-memberships-pro' );?>" class="regular-text code" /></td>
            </tr>
            <tr class="gateway gateway_btcpay">
                <th scope="row" valign="top"><label for="btcpay_server_url"><?php esc_html_e('BTCPay server URL*', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" placeholder="https://" id="btcpay_server_url" name="btcpay_server_url" value="<?php echo esc_attr($values['btcpay_server_url'])?>" class="regular-text code" /><br/>
                    <a href="#" class="pmpro-btcpay-apikey-link"><?php echo esc_html__('Check connection', 'coinsnap-for-paid-memberships-pro' );?></a><br/><br/>
                    <button class="button pmpro-btcpay-apikey-link" type="button" id="btcpay_wizard_button" target="_blank"><?php echo esc_html__('Generate API key','coinsnap-for-paid-memberships-pro');?></button></td>
            </tr>
            <tr class="gateway gateway_btcpay">
                <th scope="row" valign="top"><label for="btcpay_store_id"><?php esc_html_e('Store ID*', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="btcpay_store_id" name="btcpay_store_id" value="<?php echo esc_attr($values['btcpay_store_id'])?>" class="regular-text code" /></td>
            </tr>
            <?php if (self::$btcpayKeyRefused) { ?>
            <tr class="gateway gateway_btcpay">
                <td colspan="2"><div class="notice notice-error inline"><p><?php esc_html_e('The BTCPay API key was not saved. It must be limited to the store ID above, with only the permissions this plugin needs (view and create invoices, view store settings, modify webhooks).', 'coinsnap-for-paid-memberships-pro'); ?></p></div></td>
            </tr>
            <?php } ?>
            <tr class="gateway gateway_btcpay">
                <th scope="row" valign="top"><label for="btcpay_api_key"><?php esc_html_e('API Key*', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><?php self::renderApiKeyField('btcpay_api_key', (string) $values['btcpay_api_key']); ?></td>
            </tr>
            <tr class="gateway gateway_btcpay">
                <th scope="row" valign="top"><label for="btcpay_button_text"><?php esc_html_e('Button text', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="btcpay_button_text" name="btcpay_button_text" value="<?php echo (!empty($values['btcpay_button_text']))? esc_attr($values['btcpay_button_text']) : esc_html__('BTCPay server (Bitcoin)', 'coinsnap-for-paid-memberships-pro' );?>" class="regular-text code" /></td>
            </tr>
            
            <tr class="gateway">
                <th scope="row" valign="top"><label for="coinsnap_autoredirect"><?php esc_html_e('Redirect after payment', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="checkbox"<?php echo ($values['coinsnap_autoredirect']<1)? '' : ' checked="checked"';?> id="coinsnap_autoredirect" name="coinsnap_autoredirect" value="1" class="regular-text code" /></td></tr>
            <tr class="gateway">
                <th scope="row" valign="top"><label for="coinsnap_returnurl"><?php esc_html_e('Return URL after payment', 'coinsnap-for-paid-memberships-pro' );?>:</label></th>
                <td><input type="text" id="coinsnap_returnurl" name="coinsnap_returnurl" value="<?php echo esc_attr($values['coinsnap_returnurl'])?>" class="regular-text code" /></td>
            </tr>
            <tr class="gateway">
                <th scope="row" valign="top"><label for="coinsnap_expired_status"><?php esc_html_e( 'Expired Status', 'coinsnap-for-paid-memberships-pro' ); ?>:</label></th>
                <td><select id="coinsnap_expired_status" name="coinsnap_expired_status">
                    <?php foreach ( $statuses as $status ) { ?>
                        <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $coinsnap_expired_status, $status ); ?>><?php echo esc_html( $status ); ?></option>
                    <?php } ?>
                    </select></td></tr>
            
            <tr class="gateway">
                <th scope="row" valign="top"><label for="coinsnap_settled_status"><?php esc_html_e( 'Settled Status', 'coinsnap-for-paid-memberships-pro' ); ?>:</label></th>
                <td><select id="coinsnap_settled_status" name="coinsnap_settled_status">
                    <?php foreach ( $statuses as $status ) { ?>
			<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $coinsnap_settled_status, $status ); ?>><?php echo esc_html( $status ); ?></option>
                    <?php } ?>
                    </select></td></tr>

            <tr class="gateway">
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
            // The total includes tax; the subtotal left tax unpaid.
            $amount = round((float) ( ( isset( $order->total ) && '' !== (string) $order->total ) ? $order->total : $order->subtotal ), 2);
            
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
                            $errorMessage = __( 'No payment method is configured on BTCPay server ', 'coinsnap-for-paid-memberships-pro' );
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
                try {
                    $checkInvoice = $client->checkPaymentData((float)$amount,strtoupper( $currency ));
                }
                catch (\Throwable $e){
                    $checkInvoice = array('result' => false,'error' => esc_html__( 'API connection is not established.', 'coinsnap-for-paid-memberships-pro' ));
                }
            }
            return $checkInvoice ?? array('result' => false,'error' => esc_html__( 'No payment method is configured on BTCPay server ', 'coinsnap-for-paid-memberships-pro' ));
                   
        }

        static function coinsnappmpro_checkout_before_change_membership_level($user_id, $morder){
            global $wpdb, $discount_code_id;

            // This action runs for every gateway's checkout.
            if (empty($morder) || $morder->gateway !== 'coinsnap') {
                return;
            }
            $morder->user_id = $user_id;
            // Waiting for payment; the webhook marks it paid and grants the membership.
            $morder->status = 'token';
            $morder->saveOrder();

            if(!empty($discount_code_id)){
                $wpdb->insert($wpdb->pmpro_discount_codes_uses, ['code_id' => $discount_code_id, 'user_id' => $user_id, 'order_id' => $morder->id, 'timestamp' => current_time('mysql')], ['%s', '%s', '%s', '%s']);
            }

            $error = $morder->Gateway->coinsnappmpro_getInvoice($morder);
            // Returning would let PMPro grant the membership without a payment.
            $morder->status = 'error';
            $morder->saveOrder();
            wp_die(esc_html($error), esc_html__('Payment error', 'coinsnap-for-paid-memberships-pro'), ['response' => 503, 'back_link' => true]);
	}
        
        public function coinsnappmpro_getInvoice($order){
            global $pmpro_currency;
            
            $currency = strtoupper($pmpro_currency);
            // The total includes tax; the subtotal left tax unpaid.
            $amount = round((float) ( ( isset( $order->total ) && '' !== (string) $order->total ) ? $order->total : $order->subtotal ), 2);
            
            $client =new \Coinsnap\Client\Invoice(self::getApiUrl(), self::getApiKey());
            $checkInvoice = self::coinsnappmpro_amount_validation($amount,strtoupper($currency));
            
            if($checkInvoice['result'] === true){
            
                $redirectUrl = (!empty(pmpro_getOption('coinsnap_returnurl')))? pmpro_getOption('coinsnap_returnurl') : esc_url(site_url().'/membership-confirmation/?level=').$order->membership_id;
            
                $current_user = wp_get_current_user();
                $buyerEmail = $current_user->user_email;
                $buyerName =  $current_user->user_firstname . ' ' . $current_user->user_lastname;
						    	
                $metadata = [];
                $metadata['orderNumber'] = $order->id;
                $metadata['customerName'] = $buyerName;
            
                if(self::get_payment_provider() === 'btcpay') {
                    $metadata['orderId'] = $order->id;
                }
                            
                $redirectAutomatically = (pmpro_getOption( 'coinsnap_autoredirect') > 0 )? true : false;
                $walletMessage = '';
                
                if($this->get_payment_provider() === 'btcpay' && $currency !== 'BTC'){
                    $store = new \Coinsnap\Client\Store($this->getApiUrl(), $this->getApiKey());
                    $btcpayCurrencies = $store -> getStoreCurrenciesRates($this->getStoreId(),array($currency));
                    $isCurrency = true;
                    if(!isset($btcpayCurrencies['result']['error']) && count($btcpayCurrencies['result']['currencies'])>0){
                        if(!isset($btcpayCurrencies['result']['currencies']['BTC_'.$currency])){
                            $isCurrency = false;
                        }
                    }
                    else {
                        $isCurrency = false;
                    }
                    
                    // Handle currencies non-supported by BTCPay Server, we need to change them BTC and adjust the amount.
                    if( !$isCurrency ){
                        $currency = 'BTC';
                        $rate = 1/$checkInvoice['rate'];
                        $amountBTC = bcdiv(strval($amount), strval($rate), 8);
                        $amount = (float)$amountBTC;
                    }
                }

		$camount = ($currency === 'BTC')? \Coinsnap\Util\PreciseNumber::parseFloat($amount,8) : \Coinsnap\Util\PreciseNumber::parseFloat($amount,2);
                
                try {
                    $invoiceData = $client->createInvoice(
                        self::getStoreId(),
                        $currency,
                        $camount,
                        $order->id,
                        $buyerEmail,
                        $buyerName,
                        $redirectUrl,
                        COINSNAPPMPRO_REFERRAL_CODE,
                        $metadata,
                        $redirectAutomatically,
                        $walletMessage
                    )->getData();
                } catch (\Throwable $e) {
                    $invoiceData = [];
                }

                if (empty($invoiceData['checkoutLink']) || empty($invoiceData['id'])) {
                    return __('The payment could not be started. Please try again later.', 'coinsnap-for-paid-memberships-pro');
                }

                // Only invoices recorded on the order can pay it, and only for the amount and currency sent.
                $invoices = get_pmpro_membership_order_meta($order->id, 'coinsnap_invoices', true);
                $invoices = is_array($invoices) ? $invoices : [];
                $invoices[(string) $invoiceData['id']] = ['amount' => (string) $camount, 'currency' => $currency];
                update_pmpro_membership_order_meta($order->id, 'coinsnap_invoices', $invoices);

                wp_redirect($invoiceData['checkoutLink']);
                exit();
            }
            else {
                if($checkInvoice['error'] === 'currencyError'){
                            $errorMessage = sprintf( 
                            /* translators: 1: Currency */
                            __( 'Currency %1$s is not supported by Coinsnap', 'coinsnap-for-paid-memberships-pro' ), $currency);
                }      
                elseif($checkInvoice['error'] === 'amountError'){
                            $errorMessage = sprintf( 
                            /* translators: 1: Amount, 2: Currency */
                            __( 'Invoice amount cannot be less than %1$s %2$s', 'coinsnap-for-paid-memberships-pro' ), $checkInvoice['min_value'], $currency);
                }
                else {
                    $errorMessage = $checkInvoice['error'];
                }
                return (string) $errorMessage;
            }
        }
        
        //  =============================== API OPTIONS =======================================
		
	public static function get_payment_provider() {
            return (pmpro_getOption( 'coinsnap_provider') === 'btcpay')? 'btcpay' : 'coinsnap';
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
        
        
        public static function coinsnappmpro_processWebhook(){
            if (filter_input(INPUT_GET, 'pmp-listener', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== 'coinsnap') {
                return;
            }
            $provider = self::get_payment_provider();
            $webhook = get_option('pmpro_coinsnap_webhook');
            $secret = is_array($webhook) ? (string) ($webhook['secret'] ?? '') : '';
            $header = ($provider === 'btcpay') ? 'HTTP_BTCPAY_SIG' : 'HTTP_X_COINSNAP_SIGNATURE';
            $statuses = [
                'settled' => (string) pmpro_getOption('coinsnap_settled_status'),
                'processing' => (string) pmpro_getOption('coinsnap_processing_status'),
                'expired' => (string) pmpro_getOption('coinsnap_expired_status'),
            ];

            try {
                $status = (new \CoinsnapPMPro\Webhook\WebhookHandler($provider, $secret, $statuses))->handle(
                    (string) file_get_contents('php://input'),
                    isset($_SERVER[$header]) ? (string) wp_unslash($_SERVER[$header]) : '',
                    time()
                );
            } catch (\Throwable $e) {
                // No trace or message in the reply; the provider retries.
                error_log('Coinsnap INFO webhook reason=retry: internal error invoice=-');
                $status = 503;
            }
            status_header($status);
            echo $status === 200 ? 'OK' : ($status === 401 ? 'Unauthorized' : 'Retry');
            exit;
	}
        
        // Throws when the API can't be asked; the caller must not treat that as "no webhook".
        public static function webhookExists(string $apiUrl, string $apiKey, string $storeId): bool {
            $whClient = new Webhook($apiUrl, $apiKey);
            $stored = get_option('pmpro_coinsnap_webhook');

            if (is_array($stored) && !empty($stored['id']) && !empty($stored['secret'])) {
                try {
                    $existing = $whClient->getWebhook($storeId, (string) $stored['id']);
                    if (strpos((string) ($existing->getData()['url'] ?? ''), self::get_webhook_url()) !== false) {
                        return true;
                    }
                } catch (\Coinsnap\Exception\RequestException $e) {
                    if (!in_array($e->getCode(), [400, 404], true)) {
                        throw $e;
                    }
                }
            }
            // Remove webhooks of ours whose secret this site does not hold.
            foreach ($whClient->getWebhooks($storeId) as $webhook) {
                if (strpos((string) $webhook->getData()['url'], self::get_webhook_url()) !== false) {
                    try {
                        $whClient->deleteWebhook($storeId, $webhook->getData()['id']);
                    } catch (\Throwable $e) {
                        error_log('Coinsnap: a stale webhook could not be deleted');
                    }
                }
            }
            return false;
        }

        public static function registerWebhook(string $apiUrl, $apiKey, $storeId): bool {
            try {
                $whClient = new Webhook($apiUrl, $apiKey);
                $webhook_events = (self::get_payment_provider() === 'btcpay')? self::BTCPAY_WEBHOOK_EVENTS : self::COINSNAP_WEBHOOK_EVENTS;
                $webhook = $whClient->createWebhook($storeId, self::get_webhook_url(), $webhook_events, null)->getData();
            }
            catch (\Throwable $e) {
                error_log(sprintf('Coinsnap: the webhook could not be registered (status %d)', (int) $e->getCode()));
                return false;
            }
            if (empty($webhook['id']) || empty($webhook['secret'])) {
                return false;
            }
            update_option('pmpro_coinsnap_webhook', ['id' => $webhook['id'], 'secret' => $webhook['secret'], 'url' => $webhook['url'] ?? self::get_webhook_url()], false);
            return true;
        }

        // The stored key is never printed; an empty field keeps it.
        public static function renderApiKeyField(string $id, string $stored) {
            $hint = ($stored !== '') ? sprintf(
                /* translators: 1: last characters of the stored API key */
                __('A key ending in %1$s is saved. Leave empty to keep it.', 'coinsnap-for-paid-memberships-pro'),
                substr($stored, -4)
            ) : '';
            printf(
                '<input type="password" id="%1$s" name="%1$s" value="" autocomplete="new-password" class="regular-text code" /><p class="description">%2$s</p>',
                esc_attr($id),
                esc_html($hint)
            );
        }

        // A pasted BTCPay key is saved only if BTCPay confirms it is limited to the configured store.
        public static function checkPastedBtcpayKey($value, $old) {
            $value = trim((string) $value);
            if (!is_admin() || !isset($_POST['btcpay_api_key']) || $value === '' || ($value === (string) $old && !self::$btcpayUrlChanged)) {
                return $value;
            }
            // PMPro saves the URL and the store ID before the key, from the same form.
            $url = (string) pmpro_getOption('btcpay_server_url');
            $storeId = (string) pmpro_getOption('btcpay_store_id');
            if ($storeId !== '' && \CoinsnapPMPro\BtcpayKey::verify($url, $value, $storeId) !== null) {
                return $value;
            }
            self::$btcpayKeyRefused = true;
            // A refused key is not saved, and the old key never goes to a new URL.
            return self::$btcpayUrlChanged ? '' : $old;
        }

        public static function noteBtcpayUrlChange($value, $old) {
            if ((string) $value !== (string) $old) {
                self::$btcpayUrlChanged = true;
            }
            return $value;
        }

        // Runs on the settings form save, where an empty key field means "keep the saved key".
        public static function keepStoredApiKey($value, $old, $option) {
            $field = substr((string) $option, strlen('pmpro_'));
            if (!is_admin() || !isset($_POST[$field]) || trim((string) $value) !== '') {
                return $value;
            }
            // A key is never sent to a server it was not created on.
            if ($field === 'btcpay_api_key' && self::$btcpayUrlChanged) {
                return '';
            }
            return $old;
        }
}