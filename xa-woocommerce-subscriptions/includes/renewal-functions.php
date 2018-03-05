<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function hf_create_renewal_order( $subscription ) {

	$renewal_order = hf_create_order_from_subscription( $subscription, 'renewal_order' );
	if ( is_wp_error( $renewal_order ) ) {
		return new WP_Error( 'renewal-order-error', $renewal_order->get_error_message() );
	}
	hf_set_objects_property( $renewal_order, 'subscription_renewal', $subscription->get_id(), 'save' );
	return apply_filters( 'hf_renewal_order_created', $renewal_order, $subscription );
}

 
function hf_order_contains_renewal( $order ) {

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( hf_is_order( $order ) && hf_get_objects_property( $order, 'subscription_renewal' ) ) {
		$is_renewal = true;
	} else {
		$is_renewal = false;
	}

	return apply_filters( 'hf_subscriptions_is_renewal_order', $is_renewal, $order );
}

 
function hf_cart_contains_renewal() {

	$contains_renewal = false;

	if ( ! empty( WC()->cart->cart_contents ) ) {
		foreach ( WC()->cart->cart_contents as $cart_item ) {
			if ( isset( $cart_item['subscription_renewal'] ) ) {
				$contains_renewal = $cart_item;
				break;
			}
		}
	}

	return apply_filters( 'hf_cart_contains_renewal', $contains_renewal );
}

function hf_cart_contains_failed_renewal_order_payment() {

	$contains_renewal = false;
	$cart_item        = hf_cart_contains_renewal();

	if ( false !== $cart_item && isset( $cart_item['subscription_renewal']['renewal_order_id'] ) ) {
		$renewal_order           = wc_get_order( $cart_item['subscription_renewal']['renewal_order_id'] );
		$is_failed_renewal_order = apply_filters( 'hf_subscriptions_is_failed_renewal_order', $renewal_order->has_status( 'failed' ), $cart_item['subscription_renewal']['renewal_order_id'], $renewal_order->get_status() );

		if ( $is_failed_renewal_order ) {
			$contains_renewal = $cart_item;
		}
	}

	return apply_filters( 'hf_cart_contains_failed_renewal_order_payment', $contains_renewal );
}


function hf_get_subscriptions_for_renewal_order( $order ) {

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		$order = wc_get_order( $order );
	}

	$subscriptions = array();

	if ( is_a( $order, 'WC_Abstract_Order' ) ) {
		$subscription_ids = hf_get_objects_property( $order, 'subscription_renewal', 'multiple' );

		foreach ( $subscription_ids as $subscription_id ) {
			if ( hf_is_subscription( $subscription_id ) ) {
				$subscriptions[ $subscription_id ] = hf_get_subscription( $subscription_id );
			}
		}
	}

	return apply_filters( 'hf_subscriptions_for_renewal_order', $subscriptions, $order );
}