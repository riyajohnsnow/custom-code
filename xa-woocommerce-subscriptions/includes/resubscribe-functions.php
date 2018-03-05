<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function hf_order_contains_resubscribe( $order ) {

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( hf_get_objects_property( $order, 'subscription_resubscribe' ) ) {
		$is_resubscribe_order = true;
	} else {
		$is_resubscribe_order = false;
	}

	return apply_filters( 'hf_subscriptions_is_resubscribe_order', $is_resubscribe_order, $order );
}


function hf_create_resubscribe_order( $subscription ) {

	$resubscribe_order = hf_create_order_from_subscription( $subscription, 'resubscribe_order' );
	if ( is_wp_error( $resubscribe_order ) ) {
		return new WP_Error( 'resubscribe-order-error', $renewal_order->get_error_message() );
	}
	hf_set_objects_property( $resubscribe_order, 'subscription_resubscribe', $subscription->get_id(), true );
	do_action( 'hf_resubscribe_order_created', $resubscribe_order, $subscription );
	return $resubscribe_order;
}

function hf_get_users_resubscribe_link( $subscription ) {

	$subscription_id  = ( is_object( $subscription ) ) ? $subscription->get_id() : $subscription;
	$resubscribe_link = add_query_arg( array( 'resubscribe' => $subscription_id ), get_permalink( wc_get_page_id( 'myaccount' ) ) );
	$resubscribe_link = wp_nonce_url( $resubscribe_link, $subscription_id );
	return apply_filters( 'hf_users_resubscribe_link', $resubscribe_link, $subscription_id );
}

function hf_get_users_resubscribe_link_for_product( $product_id ) {

	$renewal_url = '';
	if ( is_user_logged_in() ) {
		foreach ( hf_get_users_subscriptions() as $subscription ) {
			foreach ( $subscription->get_items() as $line_item ) {
				if ( ( $line_item['product_id'] == $product_id || $line_item['variation_id'] == $product_id ) && hf_can_user_resubscribe_to( $subscription ) ) {
					$renewal_url = hf_get_users_resubscribe_link( $subscription );
					break;
				}
			}
		}
	}
	return apply_filters( 'hf_users_resubscribe_link_for_product', $renewal_url, $product_id );
}

function hf_cart_contains_resubscribe( $cart = '' ) {

	$contains_resubscribe = false;
	if ( empty( $cart ) ) {
		$cart = WC()->cart;
	}
	if ( ! empty( $cart->cart_contents ) ) {
		foreach ( $cart->cart_contents as $cart_item ) {
			if ( isset( $cart_item['subscription_resubscribe'] ) ) {
				$contains_resubscribe = $cart_item;
				break;
			}
		}
	}
	return apply_filters( 'hf_cart_contains_resubscribe', $contains_resubscribe, $cart );
}

function hf_get_subscriptions_for_resubscribe_order( $order ) {

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		$order = wc_get_order( $order );
	}
	$subscriptions    = array();
	$subscription_ids = hf_get_objects_property( $order, 'subscription_resubscribe', 'multiple' );
	foreach ( $subscription_ids as $subscription_id ) {
		if ( hf_is_subscription( $subscription_id ) ) {
			$subscriptions[ $subscription_id ] = hf_get_subscription( $subscription_id );
		}
	}
	return apply_filters( 'hf_subscriptions_for_resubscribe_order', $subscriptions, $order );
}

function hf_can_user_resubscribe_to( $subscription, $user_id = '' ) {

	if ( ! is_object( $subscription ) ) {
		$subscription = hf_get_subscription( $subscription );
	}
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	if ( empty( $subscription ) ) {
		$can_user_resubscribe = false;

	} elseif ( ! user_can( $user_id, 'subscribe_again', $subscription->get_id() ) ) {
		$can_user_resubscribe = false;
	} elseif ( ! $subscription->has_status( array( 'pending-cancel', 'cancelled', 'expired', 'trash' ) ) ) {
		$can_user_resubscribe = false;
	} elseif ( $subscription->get_total() <= 0 ) {
		$can_user_resubscribe = false;
	} else {
		$resubscribe_orders = get_posts( array(
			'meta_query'  => array(
				array(
					'key'     => '_subscription_resubscribe',
					'compare' => '=',
					'value'   => $subscription->get_id(),
					'type'    => 'numeric',
				),
			),
			'post_type'   => 'shop_order',
			'post_status' => 'any',
		) );

		$all_line_items_exist = true;
		$has_active_limited_subscription = false;

		foreach ( $subscription->get_items() as $line_item ) {
			$product = ( ! empty( $line_item['variation_id'] ) ) ? wc_get_product( $line_item['variation_id'] ) : wc_get_product( $line_item['product_id'] );
			if ( false === $product ) {
				$all_line_items_exist = false;
				break;
			}

			if ( 'active' == hf_get_product_limitation( $product ) && ( hf_user_has_subscription( $user_id, $product->get_id(), 'on-hold' ) || hf_user_has_subscription( $user_id, $product->get_id(), 'active' ) ) ) {
				$has_active_limited_subscription = true;
				break;
			}
		}

		if ( empty( $resubscribe_orders ) && $subscription->get_completed_payment_count() > 0 && true === $all_line_items_exist && false === $has_active_limited_subscription ) {
			$can_user_resubscribe = true;
		} else {
			$can_user_resubscribe = false;
		}
	}

	return apply_filters( 'hf_can_user_resubscribe_to_subscription', $can_user_resubscribe, $subscription, $user_id );
}