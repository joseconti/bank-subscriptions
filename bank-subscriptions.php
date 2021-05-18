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
 * Text Domain: wooCommerce-bank-transfer-subscription
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

add_action( 'plugins_loaded', 'woocommerce_gateway_bank_transfer_subscriptions_init', 11 );

function woocommerce_gateway_bank_transfer_subscriptions_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	require_once WOO_BANK_TRA_SUB_PLUGIN_PATH_P . 'classes/class-wc-gateway-bacs-subscriptions.php';
}
