<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


add_action( 'hf_subscription_status_failed', 'hf_maybe_make_user_inactive_for', 10, 1 );
add_action( 'hf_subscription_status_on-hold', 'hf_maybe_make_user_inactive_for', 10, 1 );
add_action( 'hf_subscription_status_cancelled', 'hf_maybe_make_user_inactive_for', 10, 1 );
add_action( 'hf_subscription_status_expired', 'hf_maybe_make_user_inactive_for', 10, 1 );

function hf_maybe_make_user_inactive_for( $subscription ) {
	hf_maybe_make_user_inactive( $subscription->get_user_id() );
}

function hf_update_users_role( $user_id, $role_new ) {

	$user = new WP_User( $user_id );

	if ( ! empty( $user->roles ) && in_array( 'administrator', $user->roles ) ) {
		return;
	}

	if ( ! apply_filters( 'hf_subscription_update_users_role', true, $user, $role_new ) ) {
		return;
	}

	$roles = hf_get_new_user_role_names( $role_new );

	$role_new = $roles['new'];
	$role_old = $roles['old'];

	if ( ! empty( $role_old ) ) {
		$user->remove_role( $role_old );
	}

	$user->add_role( $role_new );

	do_action( 'hf_subscription_updated_users_role', $role_new, $user, $role_old );
	return $user;
}

 
function hf_get_new_user_role_names( $role_new ) {
    
	$default_subscriber_role = 'subscriber';
	$default_cancelled_role = 'customer';
	$role_old = '';

	if ( 'default_subscriber_role' == $role_new ) {
		$role_old = $default_cancelled_role;
		$role_new = $default_subscriber_role;
	} elseif ( in_array( $role_new, array( 'default_inactive_role', 'default_cancelled_role' ) ) ) {
		$role_old = $default_subscriber_role;
		$role_new = $default_cancelled_role;
	}

	return array(
		'new' => $role_new,
		'old' => $role_old,
	);
}


function hf_user_has_subscription( $user_id = 0, $product_id = '', $status = 'any' ) {

	$subscriptions = hf_get_users_subscriptions( $user_id );

	$has_subscription = false;

	if ( empty( $product_id ) ) { 

		if ( ! empty( $status ) && 'any' != $status ) { 
			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->has_status( $status ) ) {
					$has_subscription = true;
					break;
				}
			}
		} elseif ( ! empty( $subscriptions ) ) {
			$has_subscription = true;
		}
	} else {

		foreach ( $subscriptions as $subscription ) {
			if ( $subscription->has_product( $product_id ) && ( empty( $status ) || 'any' == $status || $subscription->has_status( $status ) ) ) {
				$has_subscription = true;
				break;
			}
		}
	}

	return apply_filters( 'hf_user_has_subscription', $has_subscription, $user_id, $product_id, $status );
}

function hf_get_users_subscriptions( $user_id = 0 ) {

	if ( 0 === $user_id || empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$subscriptions = apply_filters( 'hf_pre_get_users_subscriptions', array(), $user_id );

	if ( empty( $subscriptions ) && 0 !== $user_id && ! empty( $user_id ) ) {

		$post_ids = get_posts( array(
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'post_type'      => 'hf_shop_subscription',
			'orderby'        => 'date',
			'order'          => 'desc',
			'meta_key'       => '_customer_user',
			'meta_value'     => $user_id,
			'meta_compare'   => '=',
			'fields'         => 'ids',
		) );

		foreach ( $post_ids as $post_id ) {
			$subscription = hf_get_subscription( $post_id );

			if ( $subscription ) {
				$subscriptions[ $post_id ] = $subscription;
			}
		}
	}

	return apply_filters( 'hf_get_users_subscriptions', $subscriptions, $user_id );
}

function hf_get_users_change_status_link( $subscription_id, $status, $current_status = '' ) {

	if ( '' === $current_status ) {
		$subscription = hf_get_subscription( $subscription_id );

		if ( $subscription instanceof HF_Subscription ) {
			$current_status = $subscription->get_status();
		}
	}

	$action_link = add_query_arg( array( 'subscription_id' => $subscription_id, 'change_subscription_to' => $status ) );
	$action_link = wp_nonce_url( $action_link, $subscription_id . $current_status );

	return apply_filters( 'hf_users_change_status_link', $action_link, $subscription_id, $status );
}

function hf_can_user_put_subscription_on_hold( $subscription, $user = '' ) {

	$user_can_suspend = false;

	if ( empty( $user ) ) {
		$user = wp_get_current_user();
	} elseif ( is_int( $user ) ) {
		$user = get_user_by( 'id', $user );
	}

	if ( user_can( $user, 'manage_woocommerce' ) ) {

		$user_can_suspend = true;

	} else { 

		if ( ! is_object( $subscription ) ) {
			$subscription = hf_get_subscription( $subscription );
		}

		if ( $user->ID == $subscription->get_user_id() ) {

			$suspension_count    = intval( $subscription->get_suspension_count() );
			$allowed_suspensions = 0;

			if ( 'unlimited' === $allowed_suspensions || $allowed_suspensions > $suspension_count ) { 
				$user_can_suspend = true;
			}
		}
	}

	return apply_filters( 'hf_can_user_put_subscription_on_hold', $user_can_suspend, $subscription );
}

function hf_get_all_user_actions_for_subscription( $subscription, $user_id ) {

	$actions = array();
	if ( user_can( $user_id, 'edit_hf_shop_subscription_status', $subscription->get_id() ) ) {
		$admin_with_suspension_disallowed =  false;
		$current_status = $subscription->get_status();
                
                if ( $subscription->can_be_updated_to( 'active' ) && ! $subscription->needs_payment() ) {
			$actions['reactivate'] = array(
				'url'  => hf_get_users_change_status_link( $subscription->get_id(), 'active', $current_status ),
				'name' => __( 'Reactivate', HF_Subscriptions::TEXT_DOMAIN ),
			);
		}
		// if ( hf_can_user_resubscribe_to( $subscription, $user_id ) ) {
		// 	$actions['resubscribe'] = array(
		// 		'url'  => hf_get_users_resubscribe_link( $subscription ),
		// 		'name' => __( 'Resubscribe', HF_Subscriptions::TEXT_DOMAIN ),
		// 	);
		// }
		$next_payment = $subscription->get_time( 'next_payment' );
		if ( $subscription->can_be_updated_to( 'cancelled' ) && ( ! $subscription->is_one_payment() && ( $subscription->has_status( 'on-hold' ) && empty( $next_payment ) ) || $next_payment > 0 ) ) {
			$actions['cancel'] = array(
				'url'  => hf_get_users_change_status_link( $subscription->get_id(), 'cancelled', $current_status ),
				'name' => __( 'Cancel', HF_Subscriptions::TEXT_DOMAIN ),
			);
		}
	}

	return apply_filters( 'hf_view_subscription_actions', $actions, $subscription );
}


function hf_make_user_active( $user_id ) {
	hf_update_users_role( $user_id, 'default_subscriber_role' );
}

function hf_make_user_inactive( $user_id ) {
	hf_update_users_role( $user_id, 'default_inactive_role' );
}

function hf_maybe_make_user_inactive( $user_id ) {
	if ( ! hf_user_has_subscription( $user_id, '', 'active' ) ) {
		hf_update_users_role( $user_id, 'default_inactive_role' );
	}
}

function hf_user_has_capability( $allcaps, $caps, $args ) {
	if ( isset( $caps[0] ) ) {
		switch ( $caps[0] ) {
			case 'edit_hf_shop_subscription_payment_method' :
				$user_id  = $args[1];
				$subscription = hf_get_subscription( $args[2] );

				if ( $subscription && $user_id === $subscription->get_user_id() ) {
					$allcaps['edit_hf_shop_subscription_payment_method'] = true;
				}
			break;
			case 'edit_hf_shop_subscription_status' :
				$user_id  = $args[1];
				$subscription = hf_get_subscription( $args[2] );

				if ( $user_id === $subscription->get_user_id() ) {
					$allcaps['edit_hf_shop_subscription_status'] = true;
				}
			break;
			case 'edit_hf_shop_subscription_line_items' :
				$user_id  = $args[1];
				$subscription = hf_get_subscription( $args[2] );

				if ( $user_id === $subscription->get_user_id() ) {
					$allcaps['edit_hf_shop_subscription_line_items'] = true;
				}
			break;
			case 'switch_hf_shop_subscription' :
				$user_id  = $args[1];
				$subscription = hf_get_subscription( $args[2] );

				if ( $user_id === $subscription->get_user_id() ) {
					$allcaps['switch_hf_shop_subscription'] = true;
				}
			break;
			case 'subscribe_again' :
				$user_id  = $args[1];
				$subscription = hf_get_subscription( $args[2] );

				if ( $user_id === $subscription->get_user_id() ) {
					$allcaps['subscribe_again'] = true;
				}
			break;
			case 'pay_for_order' :
				$user_id = $args[1];
				$order   = wc_get_order( $args[2] );

				if ( $order && hf_order_contains_subscription( $order, 'any' ) ) {

					if ( $user_id === $order->get_user_id() ) {
						$allcaps['pay_for_order'] = true;
					} else {
						unset( $allcaps['pay_for_order'] );
					}
				}
			break;
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'hf_user_has_capability', 15, 3 );