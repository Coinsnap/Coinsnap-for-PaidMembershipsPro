<?php
/*
 * Plugin Name:     Coinsnap for Paid Memberships Pro
 * Plugin URI:      https://www.coinsnap.io
 * Description:     Provides a <a href="https://coinsnap.io">Coinsnap</a>  - Bitcoin + Lightning Payment Gateway for <a href="https://wordpress.org/plugins/paid-memberships-pro/">Paid Memberships Pro</a>.
 * Version:         1.0.0
 * Author:          Coinsnap
 * Author URI:      https://coinsnap.io/
 * Text Domain:     coinsnap-for-paidmembershipspro
 * Domain Path:     /languages
 * Requires PHP:    7.4
 * Tested up to:    6.4.3
 * Requires at least: 5.2
 * License:         GPL2
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Network:         true
 */ 

if (!defined( 'ABSPATH' )) exit;
if (!class_exists('PMProGateway')) return;

define( 'SERVER_PHP_VERSION', '7.4' );
define( 'COINSNAP_VERSION', '1.0.0' );
define( 'COINSNAP_REFERRAL_CODE', 'D12876' );
define( 'COINSNAP_PLUGIN_ID', 'coinsnap-for-paidmembershipspro' );
define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );

add_action('init', array('PMProGateway_coinsnap', 'init'));	
add_action('init', array('PMProGateway_coinsnap', 'process_webhook'));	
add_filter('plugin_action_links', array('PMProGateway_coinsnap', 'plugin_action_links'), 10, 2 );


require_once(dirname(__FILE__) . "/library/autoload.php");

		
class PMProGateway_coinsnap extends PMProGateway
{
		
			public const WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];	 
			
			function __construct($gateway = null)
			{
				$this->gateway = $gateway;
				return $this->gateway;
			}
		
			
			public static function init()
			{
				
				add_filter('pmpro_gateways', array('PMProGateway_coinsnap', 'pmpro_gateways'));						
				add_filter('pmpro_payment_options', array('PMProGateway_coinsnap', 'pmpro_payment_options'));
				add_filter('pmpro_payment_option_fields', array('PMProGateway_coinsnap', 'pmpro_payment_option_fields'), 10, 2);
					
				
				$gateway = pmpro_getGateway();
				
				if($gateway == "coinsnap")
				{					
					add_filter('pmpro_include_billing_address_fields', '__return_false');
					add_filter('pmpro_include_payment_information_fields', '__return_false');
					add_filter('pmpro_required_billing_fields', array('PMProGateway_coinsnap', 'pmpro_required_billing_fields'));
					add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_coinsnap', 'pmpro_checkout_before_change_membership_level'), 1, 2);
					add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_coinsnap', 'pmpro_checkout_default_submit_button'));					
				}
							
				
			}

			static function pmpro_checkout_default_submit_button($show)		{
				global $gateway, $pmpro_requirebilling;

			//show our submit buttons
			?>
			<span id="pmpro_submit_span">
				<input type="hidden" name="submit-checkout" value="1" />
				<input type="submit" class="<?php echo pmpro_get_element_class( 'pmpro_btn pmpro_btn-submit-checkout', 'pmpro_btn-submit-checkout' ); ?>" value="<?php if($pmpro_requirebilling) { _e('Coinsnap - Bitcoin + Lightning', 'paid-memberships-pro' ); } else { _e('Submit and Confirm', 'paid-memberships-pro' );}?> &raquo;" />
			</span>
			<?php
			
			return false;
		}

			public static function process_webhook() {
				
				if ( ! isset( $_GET['pmp-listener'] ) || $_GET['pmp-listener'] !== 'coinsnap' ) {
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
					
			
				}catch (\Throwable $e) {									
					
					echo "Error";
					exit;
				}
						
				$order_status = 'pending';
				if ($status == 'Expired') $order_status = pmpro_getOption('coinsnap_expired_status');
				else if ($status == 'Processing') $order_status = pmpro_getOption('coinsnap_processing_status');
				else if ($status == 'Settled') $order_status = pmpro_getOption('coinsnap_settled_status');
				
				if (isset($order_id)){
					$morder = new MemberOrder();
					$morder->getMemberOrderByID( $order_id );
					$morder->getMembershipLevel();					
					$morder->status = $order_status;	
					$morder->saveOrder();
				
					if ($order_status == 'success'){

						
						// Get discount code.
						$morder->getDiscountCode();
						if ( ! empty( $morder->discount_code ) ) {
						// Update membership level
							$morder->getMembershipLevel(true);
							$discount_code_id = $morder->discount_code->id;
						} else {
							$discount_code_id = "";
						}
						
						$morder->membership_level = apply_filters("pmpro_inshandler_level", $morder->membership_level, $morder->user_id);		
						$startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time('mysql') . "'", $morder->user_id, $morder->membership_level);
						//fix expiration date
						if(!empty($morder->membership_level->expiration_number))
						{
							$enddate = "'" . date_i18n("Y-m-d", strtotime("+ " . $morder->membership_level->expiration_number . " " . $morder->membership_level->expiration_period, current_time("timestamp"))) . "'";
						}
						else
						{
							$enddate = "NULL";
						}
						//filter the enddate (documented in preheaders/checkout.php)
						$enddate = apply_filters("pmpro_checkout_end_date", $enddate, $morder->user_id, $morder->membership_level, $startdate);

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
					}
					pmpro_changeMembershipLevel($custom_level, $morder->user_id);
				}
				
				echo "OK";
				exit;
				
			}       
		
			
			
			public static function plugin_action_links($links, $file)
			{
				static $this_plugin;
			
				if (false === isset($this_plugin) || true === empty($this_plugin)) {
					$this_plugin = plugin_basename(__FILE__);
				}
			
				if ($file == $this_plugin) {
					$settings_link = '<a href="'.admin_url('admin.php?page=pmpro-paymentsettings').'">'.__( 'Settings', 'paid-memberships-pro' ).'</a>';
					array_unshift($links, $settings_link);
				}
			
				return $links;
			}
			
				
			
			public static function pmpro_gateways($gateways)
			{
				if(empty($gateways['coinsnap']))
				$gateways = array_slice($gateways, 0, 1) + array("coinsnap" => __('Coinsnap', 'paid-memberships-pro')) + array_slice($gateways, 1);
		
				return $gateways;
			}
		
			public static function getGatewayOptions()
			{
				$options = array(
						'coinsnap_store_id',
						'coinsnap_api_key',
						'coinsnap_expired_status',
						'coinsnap_settled_status',
						'coinsnap_processing_status',
						'currency'
				);
		
				return $options;
			}
		
			
			public static function pmpro_payment_options($options)
			{
				
				$coinsnap_options = self::getGatewayOptions();					
				$options = array_merge($coinsnap_options, $options);		
				return $options;
			}
							
			
			static function pmpro_payment_option_fields($values, $gateway)
			{
				$statuses = pmpro_getOrderStatuses();
				
				$coinsnap_expired_status = !empty($values['coinsnap_expired_status']) ? $values['coinsnap_expired_status'] : 'cancelled';
				$coinsnap_settled_status = !empty($values['coinsnap_settled_status']) ? $values['coinsnap_settled_status'] : 'success';				
				$coinsnap_processing_status = !empty($values['coinsnap_processing_status']) ? $values['coinsnap_processing_status'] : 'success';					
				

			?>
				<tr class="pmpro_settings_divider gateway gateway_coinsnap" <?php if($gateway != "coinsnap") { ?>style="display: none;"<?php } ?>>
				<td colspan="2">
					<hr />
					<h2 class="title"><?php esc_html_e('Coinsnap Settings', 'paid-memberships-pro' ); ?></h2>
				</td>
				</tr>
				<tr class="gateway gateway_coinsnap" <?php if($gateway != "coinsnap") { ?>style="display: none;"<?php } ?>>
				<th scope="row" valign="top">
					<label for="coinsnap_store_id"><?php esc_html_e('Store ID', 'paid-memberships-pro' );?>:</label>
				</th>
				<td>
					<input type="text" id="coinsnap_store_id" name="coinsnap_store_id" value="<?php echo esc_attr($values['coinsnap_store_id'])?>" class="regular-text code" />
				</td>
				</tr>
				<tr class="gateway gateway_coinsnap" <?php if($gateway != "coinsnap") { ?>style="display: none;"<?php } ?>>
				<th scope="row" valign="top">
					<label for="coinsnap_api_key"><?php esc_html_e('API Key', 'paid-memberships-pro' );?>:</label>
				</th>
				<td>
					<input type="text" id="coinsnap_api_key" name="coinsnap_api_key" value="<?php echo esc_attr($values['coinsnap_api_key'])?>" class="regular-text code" />
				</td>
				</tr>

				<tr class="gateway gateway_coinsnap" <?php if($gateway != "coinsnap") { ?>style="display: none;"<?php } ?>>
				<th scope="row" valign="top"><label for="coinsnap_expired_status"><?php esc_html_e( 'Expired Status', 'paid-memberships-pro' ); ?>:</label></th>
				<td>
					
						<select id="coinsnap_expired_status" name="coinsnap_expired_status">
							<?php foreach ( $statuses as $status ) { ?>
								<option
									value="<?php echo esc_attr( $status ); ?>" <?php selected( $coinsnap_expired_status, $status ); ?>><?php echo esc_html( $status ); ?></option>
							<?php } ?>
						</select>						
				</td>
				</tr>

				<tr class="gateway gateway_coinsnap" <?php if($gateway != "coinsnap") { ?>style="display: none;"<?php } ?>>
				<th scope="row" valign="top"><label for="coinsnap_settled_status"><?php esc_html_e( 'Settled Status', 'paid-memberships-pro' ); ?>:</label></th>
				<td>
					
						<select id="coinsnap_settled_status" name="coinsnap_settled_status">
							<?php foreach ( $statuses as $status ) { ?>
								<option
									value="<?php echo esc_attr( $status ); ?>" <?php selected( $coinsnap_settled_status, $status ); ?>><?php echo esc_html( $status ); ?></option>
							<?php } ?>
						</select>						
				</td>
				</tr>

				<tr class="gateway gateway_coinsnap" <?php if($gateway != "coinsnap") { ?>style="display: none;"<?php } ?>>
				<th scope="row" valign="top"><label for="coinsnap_processing_status"><?php esc_html_e( 'Processing Status', 'paid-memberships-pro' ); ?>:</label></th>
				<td>
					
						<select id="coinsnap_processing_status" name="coinsnap_processing_status">
							<?php foreach ( $statuses as $status ) { ?>
								<option
									value="<?php echo esc_attr( $status ); ?>" <?php selected( $coinsnap_processing_status, $status ); ?>><?php echo esc_html( $status ); ?></option>
							<?php } ?>
						</select>						
				</td>
				</tr>
			<?php
						
			
					
				return;
			}
		
			
			public static function pmpro_required_billing_fields($fields)
			{
				
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
		
		
			function process(&$order)
			{
				return true;
			}
			
			
				
			
			
			static function pmpro_checkout_before_change_membership_level($user_id, $morder)
			{
				global $wpdb, $discount_code_id;
				
				
				if(empty($morder))
					return;
				
				$morder->user_id = $user_id;				
				$morder->saveOrder();
				
				
				if(!empty($discount_code_id))
					$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())");	
				
							
				$morder->Gateway->sendToCoinsnap($morder);
			}

			public function sendToCoinsnap($order){
				global $pmpro_currency;	

				$webhook_url = self::get_webhook_url();
        
        		if (! self::webhookExists(self::getStoreId(), self::getApiKey(), $webhook_url)){
            		if (! self::registerWebhook(self::getStoreId(), self::getApiKey(),$webhook_url)) {
             		echo "unable to set Webhook url";
             		exit;
            		}
		        }      

				$amount =  $order->InitialPayment;
		    	$redirectUrl = esc_url(site_url().'/membership-confirmation/?level=').$order->membership_id;
            
		    	$amount = round($amount, 2);
		    	$buyerEmail = $order->Email;

				$current_user = wp_get_current_user();
		    	$buyerName =  $current_user->user_firstname . ' ' . $current_user->user_lastname;
						    	

	        	$metadata = [];
    			$metadata['orderNumber'] = $order->id;
		    	$metadata['customerName'] = $buyerName;

		    	$checkoutOptions = new \Coinsnap\Client\InvoiceCheckoutOptions();
		    	$checkoutOptions->setRedirectURL( $redirectUrl );
		    	$client =new \Coinsnap\Client\Invoice(self::getApiUrl(), self::getApiKey());
		    	$camount = \Coinsnap\Util\PreciseNumber::parseFloat($amount,2);
		    	$invoice = $client->createInvoice(
                            self::getStoreId(),  
			    	$pmpro_currency,
			    	$camount,
			    	$order->id,
			    	$buyerEmail,
			    	$buyerName, 
			    	$redirectUrl,
			    	COINSNAP_REFERRAL_CODE,     
			    	$metadata,
			    	$checkoutOptions
		    	);
		
    			$payurl = $invoice->getData()['checkoutLink'] ;	
				wp_redirect($payurl);
				exit;

			}
		

		
		public  static function get_webhook_url() {
			return esc_url_raw( add_query_arg( array( 'pmp-listener' => 'coinsnap' ), home_url( 'index.php' ) ) );
		}
		public static function getApiKey() {
			return pmpro_getOption( 'coinsnap_api_key');
		}
		public static function getStoreId() {
			return pmpro_getOption( 'coinsnap_store_id');
		}
		public static function getApiUrl() {
			return 'https://app.coinsnap.io';
		}	
	
		public static function webhookExists(string $storeId, string $apiKey, string $webhook): bool {	
			try {		
				$whClient = new \Coinsnap\Client\Webhook( self::getApiUrl(), $apiKey );		
				$Webhooks = $whClient->getWebhooks( $storeId );			
				
				foreach ($Webhooks as $Webhook){					
					//self::deleteWebhook($storeId,$apiKey, $Webhook->getData()['id']);
					if ($Webhook->getData()['url'] == $webhook) return true;	
				}
			}catch (\Throwable $e) {			
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
			} catch (\Throwable $e) {
				return false;	
			}
	
			return false;
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

                                                                           