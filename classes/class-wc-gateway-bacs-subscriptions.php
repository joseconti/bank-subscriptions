<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Bank Transfer Payment Gateway.
 *
 * Provides a Bank Transfer Payment Gateway. Based on code by Mike Pepper.
 * Extndes Subscription functionality by Jose Conti
 *
 * @class       WC_Gateway_Bancs_Subscriptions
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_Gateway_Bancs_Subscriptions extends WC_Payment_Gateway {

	/**
	 * Array of locales
	 *
	 * @var array
	 */
	public $locale;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->id                 = 'bankssubscriptions';
		$this->icon               = apply_filters( 'woocommerce_bankssubscriptions_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = __( 'Direct bank transfer Subscriptions', 'woocommerce-bank-transfer-subscriptions' );
		$this->method_description = __( 'Take payments in person via BACS. More commonly known as direct bank/wire transfer', 'woocommerce-bank-transfer-subscriptions' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );
		$this->emailsend    = $this->get_option( 'emailsend' );
		$this->log          = new WC_Logger();
		
		// BACS account fields shown on the thanks page and in emails.
		$this->account_details = get_option(
			'woocommerce_bankssubscriptions_accounts',
			array(
				array(
					'account_name'   => $this->get_option( 'account_name' ),
					'account_number' => $this->get_option( 'account_number' ),
					'sort_code'      => $this->get_option( 'sort_code' ),
					'bank_name'      => $this->get_option( 'bank_name' ),
					'iban'           => $this->get_option( 'iban' ),
					'bic'            => $this->get_option( 'bic' ),
				),
			)
		);
		$this->supports         = array(
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
		add_action( 'woocommerce_thankyou_bankssubscriptions', array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'custom_subscription_action_status' ), 50, 1 );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'doing_scheduled_subscription_payment' ), 10, 2 );
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-bank-transfer-subscriptions' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable bank transfer', 'woocommerce-bank-transfer-subscriptions' ),
				'default' => 'no',
			),
			'emailsend'           => array(
				'title'       => __( 'Email', 'woocommerce-bank-transfer-subscriptions' ),
				'type'        => 'text',
				'description' => __( 'An email of your company for sending an email alerting about bank transfer (an email will be sent automatically to customer).', 'woocommerce-bank-transfer-subscriptions' ),
				'default'     => __( '', 'woocommerce-bank-transfer-subscriptions' ),
				'desc_tip'    => true,
			),
			'title'           => array(
				'title'       => __( 'Title', 'woocommerce-bank-transfer-subscriptions' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-bank-transfer-subscriptions' ),
				'default'     => __( 'Direct bank transfer', 'woocommerce-bank-transfer-subscriptions' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __( 'Description', 'woocommerce-bank-transfer-subscriptions' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce-bank-transfer-subscriptions' ),
				'default'     => __( 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'woocommerce-bank-transfer-subscriptions' ),
				'desc_tip'    => true,
			),
			'instructions'    => array(
				'title'       => __( 'Instructions', 'woocommerce-bank-transfer-subscriptions' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce-bank-transfer-subscriptions' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'account_details' => array(
				'type' => 'account_details',
			),
		);

	}

	/**
	 * Generate account details html.
	 *
	 * @return string
	 */
	public function generate_account_details_html() {

		ob_start();

		$country = WC()->countries->get_base_country();
		$locale  = $this->get_country_locale();

		// Get sortcode label in the $locale array and use appropriate one.
		$sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort code', 'woocommerce-bank-transfer-subscriptions' );

		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Account details:', 'woocommerce-bank-transfer-subscriptions' ); ?></th>
			<td class="forminp" id="bacs_accounts">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table sortable" cellspacing="0">
						<thead>
							<tr>
								<th class="sort">&nbsp;</th>
								<th><?php esc_html_e( 'Account name', 'woocommerce-bank-transfer-subscriptions' ); ?></th>
								<th><?php esc_html_e( 'Account number', 'woocommerce-bank-transfer-subscriptions' ); ?></th>
								<th><?php esc_html_e( 'Bank name', 'woocommerce-bank-transfer-subscriptions' ); ?></th>
								<th><?php echo esc_html( $sortcode ); ?></th>
								<th><?php esc_html_e( 'IBAN', 'woocommerce-bank-transfer-subscriptions' ); ?></th>
								<th><?php esc_html_e( 'BIC / Swift', 'woocommerce-bank-transfer-subscriptions' ); ?></th>
							</tr>
						</thead>
						<tbody class="accounts">
							<?php
							$i = -1;
							if ( $this->account_details ) {
								foreach ( $this->account_details as $account ) {
									$i++;

									echo '<tr class="account">
										<td class="sort"></td>
										<td><input type="text" value="' . esc_attr( wp_unslash( $account['account_name'] ) ) . '" name="bankssubscriptions_account_name[' . esc_attr( $i ) . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['account_number'] ) . '" name="bankssubscriptions_account_number[' . esc_attr( $i ) . ']" /></td>
										<td><input type="text" value="' . esc_attr( wp_unslash( $account['bank_name'] ) ) . '" name="bankssubscriptions_bank_name[' . esc_attr( $i ) . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['sort_code'] ) . '" name="bankssubscriptions_sort_code[' . esc_attr( $i ) . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['iban'] ) . '" name="bankssubscriptions_iban[' . esc_attr( $i ) . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['bic'] ) . '" name="bankssubscriptions_bic[' . esc_attr( $i ) . ']" /></td>
									</tr>';
								}
							}
							?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="7"><a href="#" class="add button"><?php esc_html_e( '+ Add account', 'woocommerce-bank-transfer-subscriptions' ); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected account(s)', 'woocommerce-bank-transfer-subscriptions' ); ?></a></th>
							</tr>
						</tfoot>
					</table>
				</div>
				<script type="text/javascript">
					jQuery(function() {
						jQuery('#bacs_accounts').on( 'click', 'a.add', function(){

							var size = jQuery('#bacs_accounts').find('tbody .account').length;

							jQuery('<tr class="account">\
									<td class="sort"></td>\
									<td><input type="text" name="bankssubscriptions_account_name[' + size + ']" /></td>\
									<td><input type="text" name="bankssubscriptions_account_number[' + size + ']" /></td>\
									<td><input type="text" name="bankssubscriptions_bank_name[' + size + ']" /></td>\
									<td><input type="text" name="bankssubscriptions_sort_code[' + size + ']" /></td>\
									<td><input type="text" name="bankssubscriptions_iban[' + size + ']" /></td>\
									<td><input type="text" name="bankssubscriptions_bic[' + size + ']" /></td>\
								</tr>').appendTo('#bacs_accounts table tbody');

							return false;
						});
					});
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();

	}

	/**
	 * Save account details table.
	 */
	public function save_account_details() {

		$accounts = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
		if ( isset( $_POST['bankssubscriptions_account_name'] ) && isset( $_POST['bankssubscriptions_account_number'] ) && isset( $_POST['bankssubscriptions_bank_name'] )
			 && isset( $_POST['bankssubscriptions_sort_code'] ) && isset( $_POST['bankssubscriptions_iban'] ) && isset( $_POST['bankssubscriptions_bic'] ) ) {

			$account_names   = wc_clean( wp_unslash( $_POST['bankssubscriptions_account_name'] ) );
			$account_numbers = wc_clean( wp_unslash( $_POST['bankssubscriptions_account_number'] ) );
			$bank_names      = wc_clean( wp_unslash( $_POST['bankssubscriptions_bank_name'] ) );
			$sort_codes      = wc_clean( wp_unslash( $_POST['bankssubscriptions_sort_code'] ) );
			$ibans           = wc_clean( wp_unslash( $_POST['bankssubscriptions_iban'] ) );
			$bics            = wc_clean( wp_unslash( $_POST['bankssubscriptions_bic'] ) );

			foreach ( $account_names as $i => $name ) {
				if ( ! isset( $account_names[ $i ] ) ) {
					continue;
				}

				$accounts[] = array(
					'account_name'   => $account_names[ $i ],
					'account_number' => $account_numbers[ $i ],
					'bank_name'      => $bank_names[ $i ],
					'sort_code'      => $sort_codes[ $i ],
					'iban'           => $ibans[ $i ],
					'bic'            => $bics[ $i ],
				);
			}
		}
		// phpcs:enable

		update_option( 'woocommerce_bankssubscriptions_accounts', $accounts );
	}

	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {

		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( wp_kses_post( $this->instructions ) ) ) );
		}
		$this->bank_details( $order_id );

	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

		if ( ! $sent_to_admin && 'bankssubscriptions' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
			if ( $this->instructions ) {
				echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
			}
			$this->bank_details( $order->get_id() );
		}

	}

	/**
	 * Get bank details and place into a list format.
	 *
	 * @param int $order_id Order ID.
	 */
	private function bank_details( $order_id = '' ) {

		if ( empty( $this->account_details ) ) {
			return;
		}

		// Get order and store in $order.
		$order = wc_get_order( $order_id );

		// Get the order country and country $locale.
		$country = $order->get_billing_country();
		$locale  = $this->get_country_locale();

		// Get sortcode label in the $locale array and use appropriate one.
		$sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort code', 'woocommerce-bank-transfer-subscriptions' );

		$bacs_accounts = apply_filters( 'woocommerce_bankssubscriptions_accounts', $this->account_details, $order_id );

		if ( ! empty( $bacs_accounts ) ) {
			$account_html = '';
			$has_details  = false;

			foreach ( $bacs_accounts as $bacs_account ) {
				$bacs_account = (object) $bacs_account;

				if ( $bacs_account->account_name ) {
					$account_html .= '<h3 class="wc-bacs-bank-details-account-name">' . wp_kses_post( wp_unslash( $bacs_account->account_name ) ) . ':</h3>' . PHP_EOL;
				}

				$account_html .= '<ul class="wc-bacs-bank-details order_details bacs_details">' . PHP_EOL;

				// BACS account fields shown on the thanks page and in emails.
				$account_fields = apply_filters(
					'woocommerce_bankssubscriptions_account_fields',
					array(
						'bank_name'      => array(
							'label' => __( 'Bank', 'woocommerce-bank-transfer-subscriptions' ),
							'value' => $bacs_account->bank_name,
						),
						'account_number' => array(
							'label' => __( 'Account number', 'woocommerce-bank-transfer-subscriptions' ),
							'value' => $bacs_account->account_number,
						),
						'sort_code'      => array(
							'label' => $sortcode,
							'value' => $bacs_account->sort_code,
						),
						'iban'           => array(
							'label' => __( 'IBAN', 'woocommerce-bank-transfer-subscriptions' ),
							'value' => $bacs_account->iban,
						),
						'bic'            => array(
							'label' => __( 'BIC', 'woocommerce-bank-transfer-subscriptions' ),
							'value' => $bacs_account->bic,
						),
					),
					$order_id
				);

				foreach ( $account_fields as $field_key => $field ) {
					if ( ! empty( $field['value'] ) ) {
						$account_html .= '<li class="' . esc_attr( $field_key ) . '">' . wp_kses_post( $field['label'] ) . ': <strong>' . wp_kses_post( wptexturize( $field['value'] ) ) . '</strong></li>' . PHP_EOL;
						$has_details   = true;
					}
				}

				$account_html .= '</ul>';
			}

			if ( $has_details ) {
				echo '<section class="woocommerce-bacs-bank-details"><h2 class="wc-bacs-bank-details-heading">' . esc_html__( 'Our bank details', 'woocommerce-bank-transfer-subscriptions' ) . '</h2>' . wp_kses_post( PHP_EOL . $account_html ) . '</section>';
			}
		}

	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( $order->get_total() > 0 ) {
			// Mark as on-hold (we're awaiting the payment).
			$order->update_status( apply_filters( 'woocommerce_bankssubscriptions_process_payment_order_status', 'on-hold', $order ), __( 'Awaiting BACS payment', 'woocommerce-bank-transfer-subscriptions' ) );
		} else {
			$order->payment_complete();
		}

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);

	}

	/**
	 * Get country locale if localized.
	 *
	 * @return array
	 */
	public function get_country_locale() {

		if ( empty( $this->locale ) ) {

			// Locale information to be used - only those that are not 'Sort Code'.
			$this->locale = apply_filters(
				'woocommerce_get_bankssubscriptions_locale',
				array(
					'AU' => array(
						'sortcode' => array(
							'label' => __( 'BSB', 'woocommerce-bank-transfer-subscriptions' ),
						),
					),
					'CA' => array(
						'sortcode' => array(
							'label' => __( 'Bank transit number', 'woocommerce-bank-transfer-subscriptions' ),
						),
					),
					'IN' => array(
						'sortcode' => array(
							'label' => __( 'IFSC', 'woocommerce-bank-transfer-subscriptions' ),
						),
					),
					'IT' => array(
						'sortcode' => array(
							'label' => __( 'Branch sort', 'woocommerce-bank-transfer-subscriptions' ),
						),
					),
					'NZ' => array(
						'sortcode' => array(
							'label' => __( 'Bank code', 'woocommerce-bank-transfer-subscriptions' ),
						),
					),
					'SE' => array(
						'sortcode' => array(
							'label' => __( 'Bank code', 'woocommerce-bank-transfer-subscriptions' ),
						),
					),
					'US' => array(
						'sortcode' => array(
							'label' => __( 'Routing number', 'woocommerce-bank-transfer-subscriptions' ),
						),
					),
					'ZA' => array(
						'sortcode' => array(
							'label' => __( 'Branch code', 'woocommerce-bank-transfer-subscriptions' ),
						),
					),
				)
			);

		}

		return $this->locale;

	}

	public function doing_scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

		$order_id    = $renewal_order->get_id();
		//$redsys_done = get_post_meta( $order_id, '_redsys_done', true );
		
			$this->log->add( 'bankssubscriptions', ' ' );
			$this->log->add( 'bankssubscriptions', '/****************************/' );
			$this->log->add( 'bankssubscriptions', '       Once upon a time       ' );
			$this->log->add( 'bankssubscriptions', '/****************************/' );
			$this->log->add( 'bankssubscriptions', ' ' );
			$this->log->add( 'bankssubscriptions', '/***************************************/' );
			$this->log->add( 'bankssubscriptions', '  Doing scheduled_subscription_payment   ' );
			$this->log->add( 'bankssubscriptions', '/***************************************/' );
			$this->log->add( 'bankssubscriptions', ' ' );
		
		$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
		$renewal_order->update_status( 'bank-transfersubs');
		
		foreach ( $subscriptions as $subscription_id => $subscription ) {
			$this->log->add( 'bankssubscriptions', '$subscription_id:' . $subscription_id );
			$subscription->update_status( 'bank-transfersubs' );
			WC_Subscriptions_Email::send_renewal_order_email( $subscription_id );
			WC_Subscriptions_Email::send_renewal_order_email( $order_id );
		}
	}
	
	function custom_subscription_action_status( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		
		$order = wc_get_order( $order_id ); // Get an instance of the WC_Order object
		
		// If the order has a 'processing' status and contains a subscription 
		
		if ( wcs_order_contains_subscription( $order ) && $order->has_status( 'on-hold' ) ) {
			$order->update_status( 'bank-transfersubs' );
			// Get an array of WC_Subscription objects
			$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
			foreach ( $subscriptions as $subscription_id => $subscription ) {
				$subscription->update_status( 'bank-transfersubs' );
			}
		}
	}
}
/**
* Copyright: (C) 2013 - 2021 José Conti
*/
function woocommerce_add_gateway_bank_transfer_subscription( $methods ) {
		$methods[] = 'WC_Gateway_Bancs_Subscriptions';
		return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_gateway_bank_transfer_subscription' );
