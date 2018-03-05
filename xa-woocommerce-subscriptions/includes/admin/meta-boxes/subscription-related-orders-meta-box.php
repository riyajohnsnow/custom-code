<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HF_Meta_Box_Related_Orders {


	public static function output( $post ) {

		if ( hf_is_subscription( $post->ID ) ) {
			$subscription = hf_get_subscription( $post->ID );
			$order = ( false == $subscription->get_parent_id() ) ? $subscription : $subscription->get_parent();
		} else {
			$order = wc_get_order( $post->ID );
		}

		add_action( 'hf_subscription_related_orders_meta_box_rows', __CLASS__ . '::output_rows', 10 );
		include_once( 'related-orders-table.php' );
		do_action( 'hf_subscription_related_orders_meta_box', $order, $post );
	}


	public static function output_rows( $post ) {

		$subscriptions = array();
		$orders        = array();

		if ( hf_is_subscription( $post->ID ) ) {
			$subscriptions[] = hf_get_subscription( $post->ID );
		} elseif ( hf_order_contains_subscription( $post->ID, array( 'parent', 'renewal' ) ) ) {
			$subscriptions = hf_get_subscriptions_for_order( $post->ID, array( 'order_type' => array( 'parent', 'renewal' ) ) );
		}

		foreach ( $subscriptions as $subscription ) {
			hf_set_objects_property( $subscription, 'relationship', __( 'Subscription', HF_Subscriptions::TEXT_DOMAIN ), 'set_prop_only' );
			$orders[] = $subscription;
		}

		$initial_subscriptions = array();

		if ( hf_is_subscription( $post->ID ) ) {

			$initial_subscriptions = hf_get_subscriptions_for_resubscribe_order( $post->ID );

			$resubscribed_subscriptions = get_posts( array(
				'meta_key'       => '_subscription_resubscribe',
				'meta_value'     => $post->ID,
				'post_type'      => 'hf_shop_subscription',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			) );

			foreach ( $resubscribed_subscriptions as $subscription ) {
				$subscription = hf_get_subscription( $subscription );
				hf_set_objects_property( $subscription, 'relationship', __( 'Resubscribed Subscription', HF_Subscriptions::TEXT_DOMAIN ), 'set_prop_only' );
				$orders[] = $subscription;
			}
		} else if ( hf_order_contains_subscription( $post->ID, array( 'resubscribe' ) ) ) {
			$initial_subscriptions = hf_get_subscriptions_for_order( $post->ID, array( 'order_type' => array( 'resubscribe' ) ) );
		}

		foreach ( $initial_subscriptions as $subscription ) {
			hf_set_objects_property( $subscription, 'relationship', __( 'Initial Subscription', HF_Subscriptions::TEXT_DOMAIN ), 'set_prop_only' );
			$orders[] = $subscription;
		}

		if ( 1 == count( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->get_parent_id() ) {
					$order = $subscription->get_parent();
					hf_set_objects_property( $order, 'relationship', __( 'Parent Order', HF_Subscriptions::TEXT_DOMAIN ), 'set_prop_only' );
					$orders[] = $order;
				}
			}
		}

		foreach ( $subscriptions as $subscription ) {

			foreach ( $subscription->get_related_orders( 'all', 'renewal' ) as $order ) {
				hf_set_objects_property( $order, 'relationship', __( 'Renewal Order', HF_Subscriptions::TEXT_DOMAIN ), 'set_prop_only' );
				$orders[] = $order;
			}
		}

		$orders = apply_filters( 'hf_subscription_admin_related_orders_to_display', $orders, $subscriptions, $post );

		foreach ( $orders as $order ) {

			if ( hf_get_objects_property( $order, 'id' ) == $post->ID ) {
				continue;
			}
			include( 'related-orders-row.php' );
		}
	}
}