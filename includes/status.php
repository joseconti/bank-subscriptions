<?php

	/*
	* Copyright: (C) 2013 - 2021 José Conti
	*/	
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly}
	}

	/*
	* Copyright: (C) 2013 - 2021 José Conti
	*/
	function bank_trans_sub_register_pending_bank_transfer_payment_status() {
	
		register_post_status(
			'wc-bank-transfersubs',
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
				$new_statuses_arr['wc-bank-transfersubs'] = __( 'Pending Bank Transfer', 'woocommerce-redsys' );
			}
		}
		return $new_statuses_arr;
	}
	add_filter( 'wc_order_statuses', 'bank_trans_sub_add_pending_bank_transfer_payment_status' );
	
	function bank_trans_sub_order_actions( $actions, $order ) {
	    // Display the "complete" action button for orders that have a 'shipped' status
	    if ( $order->has_status('bank-transfersubs') ) {
	        $actions['processing'] = array(
	            'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=processing&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
	            'name'   => __( 'Processing', 'woocommerce-bank-transfer-subscriptions' ),
	            'action' => 'processing',
	        );
	        $actions['complete'] = array(
	            'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
	            'name'   => __( 'Complete', 'woocommerce-bank-transfer-subscriptions' ),
	            'action' => 'complete',
	        );
	    }
	    return $actions;
	}
	add_filter( 'woocommerce_admin_order_actions', 'bank_trans_sub_order_actions', 10, 2 );
 
	function bank_trans_sub_post_status_subs( $registered_statuses ) {
		
		$registered_statuses['wc-bank-transfersubs'] = _n_noop( __( 'Pending Bank Transfer <span class="count">(%s)</span>', 'woocommerce-redsys' ), __( 'Pending Bank Transfer <span class="count">(%s)</span>', 'woocommerce-redsys' ) );
		return $registered_statuses;
		
	}
	add_filter( 'woocommerce_subscriptions_registered_statuses','bank_trans_sub_post_status_subs', 100, 1 );
	
	function bank_trans_sub_add_new_subscription_statuses($subscription_statuses) {
		$subscription_statuses['wc-bank-transfersubs'] = _x( 'Pending Bank Transfer', 'Subscription status', 'custom-wcs-status-texts');
		return $subscription_statuses;
	}
	add_filter( 'wcs_subscription_statuses', 'bank_trans_sub_add_new_subscription_statuses', 100, 1 );
 
	function bank_trans_sub_extends_can_be_updated( $can_be_updated, $new_status, $subscription ) {
		
		if ( $new_status === 'bank-transfersubs' ) {
			if ( $subscription->payment_method_supports( 'subscription_suspension' ) && $subscription->has_status( array( 'active', 'pending', 'on-hold', 'bank-transfersubs' ) ) ) {
				$can_be_updated = true;
			} else {
				$can_be_updated = false;
			}
		}
		if ( $new_status === 'active' ) {
			if ( $subscription->payment_method_supports( 'subscription_suspension' ) && $subscription->has_status( array( 'on-hold', 'bank-transfersubs' ) ) ) {
				$can_be_updated = true;
			} else {
				$can_be_updated = false;
			}
		}
		return $can_be_updated;
	}
	add_filter( 'woocommerce_can_subscription_be_updated_to', 'bank_trans_sub_extends_can_be_updated', 100, 3 );
 
	function bank_trans_sub_extends_update_status( $subscription, $new_status, $old_status ) {
		if ( $new_status == 'bank-transfersubs' ) {
			$subscription->update_suspension_count( $subscription->suspension_count + 1 );
			wcs_maybe_make_user_inactive( $subscription->customer_user );
		}
	}
	add_action( 'woocommerce_subscription_status_updated', 'bank_trans_sub_extends_update_status', 100, 3 );
	