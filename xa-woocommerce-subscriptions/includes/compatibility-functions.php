<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function hf_doing_it_wrong( $function, $message, $version ) {

	if ( function_exists( 'wc_doing_it_wrong' ) ) {
		wc_doing_it_wrong( $function, $message, $version );
	} else {
		if ( is_ajax() ) {
			do_action( 'doing_it_wrong_run', $function, $message, $version );
			error_log( "{$function} was called incorrectly. {$message}. This message was added in version {$version}." );
		} else {
			_doing_it_wrong( esc_attr( $function ), esc_attr( $message ), esc_attr( $version ) );
		}
	}
}


function hf_deprecated_function( $function, $version, $replacement = null ) {

	if ( function_exists( 'wc_deprecated_function' ) ) {
		wc_deprecated_function( $function, $version, $replacement );
	} else {
		if ( is_ajax() ) {
			do_action( 'deprecated_function_run', $function, $replacement, $version );
			$log_string  = "The {$function} function is deprecated since version {$version}.";
			$log_string .= $replacement ? " Replace with {$replacement}." : '';
			error_log( $log_string );
		} else {
			_deprecated_function( esc_attr( $function ), esc_attr( $version ), esc_attr( $replacement ) );
		}
	}
}

function hf_deprecated_argument( $function, $version, $message = null ) {
	if ( is_ajax() ) {
		do_action( 'deprecated_argument_run', $function, $message, $version );
		error_log( "{$function} was called with an argument that is deprecated since version {$version}. {$message}" );
	} else {
		_deprecated_argument( esc_attr( $function ), esc_attr( $version ), esc_attr( $message ) );
	}
}


function hf_get_old_subscription_key( HF_Subscription $subscription ) {

	$order_id = ( false == $subscription->get_parent_id() ) ? $subscription->get_id() : $subscription->get_parent_id();
	$subscription_items = $subscription->get_items();
	$first_item         = reset( $subscription_items );

	return $order_id . '_' . HF_Subscriptions_Order::get_items_product_id( $first_item );
}

function hf_get_subscription_id_from_key( $subscription_key ) {
	global $wpdb;

	if ( ! is_string( $subscription_key ) && ! is_int( $subscription_key ) ) {
		return null;
	}
	$order_and_product_id = explode( '_', $subscription_key );
	$subscription_ids = array();

	if ( ! empty( $order_and_product_id[0] ) && ! empty( $order_and_product_id[1] ) ) {

		$subscription_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT DISTINCT order_items.order_id FROM {$wpdb->prefix}woocommerce_order_items as order_items
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta ON order_items.order_item_id = itemmeta.order_item_id
				LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
			WHERE posts.post_type = 'hf_shop_subscription'
				AND posts.post_parent = %d
				AND itemmeta.meta_value = %d
				AND itemmeta.meta_key IN ( '_variation_id', '_product_id' )",
		$order_and_product_id[0], $order_and_product_id[1] ) );

	} elseif ( ! empty( $order_and_product_id[0] ) ) {

		$subscription_ids = get_posts( array(
			'posts_per_page' => 1,
			'post_parent'    => $order_and_product_id[0],
			'post_status'    => 'any',
			'post_type'      => 'hf_shop_subscription',
			'fields'         => 'ids',
		) );

	}

	return ( ! empty( $subscription_ids ) ) ? $subscription_ids[0] : null;
}

function hf_get_subscription_from_key( $subscription_key ) {

	$subscription_id = hf_get_subscription_id_from_key( $subscription_key );

	if ( null !== $subscription_id && is_numeric( $subscription_id ) ) {
		$subscription = hf_get_subscription( $subscription_id );
	}

	if ( ! is_object( $subscription ) ) {
		throw new InvalidArgumentException( sprintf( __( 'Could not get subscription. Most likely the subscription key does not refer to a subscription. The key was: "%s".', HF_Subscriptions::TEXT_DOMAIN ), $subscription_key ) );
	}

	return $subscription;
}

function hf_get_subscription_in_deprecated_structure( HF_Subscription $subscription ) {

	$completed_payments = array();
	if ( $subscription->get_completed_payment_count() ) {
		$order = $subscription->get_parent();
		if ( ! empty( $order ) ) {
			$parent_order_created_date = hf_get_objects_property( $order, 'date_created' );

			if ( ! is_null( $parent_order_created_date ) ) {
				$completed_payments[] = hf_get_datetime_utc_string( $parent_order_created_date );
			}
		}

		$paid_renewal_order_ids = get_posts( array(
			'posts_per_page' => -1,
			'post_status'    => $subscription->get_paid_order_statuses(),
			'post_type'      => 'shop_order',
			'orderby'        => 'date',
			'order'          => 'desc',
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_subscription_renewal',
					'compare' => '=',
					'value'   => $subscription->get_id(),
					'type'    => 'numeric',
				),
			),
		) );

		foreach ( $paid_renewal_order_ids as $paid_renewal_order_id ) {
			$date_created = hf_get_objects_property( wc_get_order( $paid_renewal_order_id ), 'date_created' );
			if ( ! is_null( $date_created ) ) {
				$completed_payments[] = hf_get_datetime_utc_string( $date_created );
			}
		}
	}

	$items = $subscription->get_items();
	$item  = array_pop( $items );

	if ( ! empty( $item ) ) {

		$deprecated_subscription_object = array(
			'order_id'           => $subscription->get_parent_id(),
			'product_id'         => isset( $item['product_id'] ) ? $item['product_id'] : 0,
			'variation_id'       => isset( $item['variation_id'] ) ? $item['variation_id'] : 0,
			'status'             => $subscription->get_status(),

			'period'             => $subscription->get_billing_period(),
			'interval'           => $subscription->get_billing_interval(),
			'length'             => hf_estimate_periods_between(  $subscription->get_time( 'date_created' ), $subscription->get_time( 'end' ) + 120, $subscription->get_billing_period(), 'floor' ) / $subscription->get_billing_interval(),

			'start_date'         => $subscription->get_date( 'date_created' ),
			'expiry_date'        => $subscription->get_date( 'end' ),
			'end_date'           => $subscription->has_status( hf_get_subscription_ended_statuses() ) ? $subscription->get_date( 'end' ) : 0,

			'failed_payments'    => $subscription->get_failed_payment_count(),
			'completed_payments' => $completed_payments,
			'suspension_count'   => $subscription->get_suspension_count(),
			'last_payment_date'  => $subscription->get_date( 'last_order_date_created' ),
		);

	} else {

		$deprecated_subscription_object = array();

	}

	return $deprecated_subscription_object;
}