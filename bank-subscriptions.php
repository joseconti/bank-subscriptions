<?php

/**
 * Plugin Name: WooCommerce Bank Transfer Subscription
 * Plugin URI: https://www.joseconti.com
 * Description: Extends WooCommerce Bank Transfer with Subscription compatibility
 * Version: 1.0.0
 * Author: José Conti
 * Author URI: https://www.joseconti.com/
 * Tested up to: 5.6
 * WC requires at least: 3.0
 * WC tested up to: 5.1
 * Woo: 187871:50392593e834002d8bee386333d1ed3c
 * Text Domain: woocommerce-bank-transfer-subscription
 * Domain Path: /languages/
 * Copyright: (C) 2013 - 2021 José Conti
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'WOO_BANK_TRA_SUB' ) ) {
	define( 'WOO_BANK_TRA_SUB', '1.0.0' );
}

if ( ! defined( 'WOO_BANK_TRA_SUB_PLUGIN_URL_P' ) ) {
	define( 'WOO_BANK_TRA_SUB_PLUGIN_URL_P', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WOO_BANK_TRA_SUB_PLUGIN_PATH_P' ) ) {
	define( 'WOO_BANK_TRA_SUB_PLUGIN_PATH_P', plugin_dir_path( __FILE__ ) );
}

add_action( 'plugins_loaded', 'woocommerce_gateway_bank_transfer_subscriptions_init' );

function woocommerce_gateway_bank_transfer_subscriptions_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	
	/*
	* Copyright: (C) 2013 - 2021 José Conti
	*/
	function bank_trans_sub_register_pending_bank_transfer_payment_status() {
	
		register_post_status(
			'wc-bank-transfer-subs',
			array(
				'label'                     => 'Pending Bank Transfer',
				'public'                    => true,
				'show_in_admin_status_list' => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true, // show count All (12) , Completed (9) , Awaiting shipment (2) ...
				'label_count'               => _n_noop( __( 'Pending Bank Transfer <span class="count">(%s)</span>', 'woocommerce-redsys' ), __( 'Pending Bank Transfer <span class="count">(%s)</span>', 'woocommerce-redsys' ) ),
			)
		);
	}
	add_action( 'init', 'bank_trans_sub_register_pending_bank_transfer_payment_status' );
	
	/*
	* Copyright: (C) 2013 - 2021 José Conti
	*/
	function bank_trans_sub_add_pending_bank_transfer_payment_status( $wc_statuses_arr ) {
	
		$new_statuses_arr = array();
	
		// add new order status after processing
		foreach ( $wc_statuses_arr as $id => $label ) {
			$new_statuses_arr[ $id ] = $label;
	
			if ( 'wc-processing' === $id ) { // after "Completed" status
				$new_statuses_arr['wc-bank-transfer-subs'] = __( 'Pending Bank Transfer', 'woocommerce-redsys' );
			}
		}
		return $new_statuses_arr;
	}
	add_filter( 'wc_order_statuses', 'bank_trans_sub_add_pending_bank_transfer_payment_status' );

	require_once WOO_BANK_TRA_SUB_PLUGIN_PATH_P . 'classes/class-wc-gateway-bacs-subscriptions.php';
}

